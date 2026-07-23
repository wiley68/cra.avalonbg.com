<?php

use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\SdlRunStatus;
use App\Enums\SdlStage;
use App\Enums\SdlStageStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Role;
use App\Models\SdlRun;
use App\Models\User;
use App\Support\SdlStageNoteTemplates;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User, product: Product, run: SdlRun}
 */
function makeSdlMonitoringFixture(bool $approve = true): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'SDL Monitoring Org',
        'slug' => 'sdl-monitoring-org-' . uniqid(),
        'is_active' => true,
        'locale' => 'en',
    ]);

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $ownerRole = Role::query()->where('slug', 'organization_owner')->firstOrFail();
    $organization->users()->attach($owner->id, [
        'role_id' => $ownerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'SDL Monitoring Product',
        'slug' => 'sdl-monitoring-product-' . uniqid(),
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => true,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
        'scope_reviewed_at' => now(),
        'scope_reviewed_by' => $owner->id,
        'classification_reviewed_at' => now(),
        'classification_reviewed_by' => $owner->id,
    ]);

    $run = SdlRun::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Monitoring checklist run',
        'status' => SdlRunStatus::InProgress,
        'current_stage' => SdlStage::ReleaseApproval,
        'owner_user_id' => $owner->id,
    ]);
    $run->ensureStageEntries();

    foreach (SdlStage::ordered() as $stage) {
        if ($stage->isPostRelease()) {
            continue;
        }

        $run->stageEntries()
            ->where('stage', $stage)
            ->update([
                'status' => SdlStageStatus::Done,
                'completed_at' => now(),
                'completed_by' => $owner->id,
                'notes' => $stage === SdlStage::ReleaseApproval
                    ? 'Release gate ready'
                    : 'Done',
            ]);
    }

    if ($approve) {
        $run->update([
            'status' => SdlRunStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $owner->id,
            'current_stage' => SdlStage::Publication,
        ]);
    }

    return compact('organization', 'owner', 'product', 'run');
}

test('monitoring and publication templates are available in EN and BG', function () {
    expect(SdlStageNoteTemplates::hasTemplate(SdlStage::Monitoring))->toBeTrue()
        ->and(SdlStageNoteTemplates::hasTemplate(SdlStage::Publication))->toBeTrue()
        ->and(SdlStageNoteTemplates::notesFor(SdlStage::Monitoring, 'en'))->toContain('Post-release monitoring')
        ->and(SdlStageNoteTemplates::notesFor(SdlStage::Monitoring, 'bg'))->toContain('Post-release мониторинг')
        ->and(SdlStageNoteTemplates::notesFor(SdlStage::Publication, 'en'))->toContain('Publication')
        ->and(SdlStageNoteTemplates::payload('en'))->toHaveKey('monitoring')
        ->and(SdlStageNoteTemplates::payload('en'))->toHaveKey('publication');
});

test('approved SDL run can update monitoring stage but not pre-gate stages', function () {
    ['owner' => $owner, 'product' => $product, 'run' => $run] = makeSdlMonitoringFixture();

    $this->actingAs($owner)
        ->put(route('products.sdl.stages.update', [
            'product' => $product,
            'sdlRun' => $run,
            'stage' => SdlStage::Monitoring->value,
        ]), [
            'status' => SdlStageStatus::Done->value,
            'notes' => "Post-release window: 14 days\n- health checks active",
            'evidence_ids' => [],
        ])
        ->assertRedirect(route('products.sdl.edit', [$product, $run]));

    $monitoring = $run->stageEntries()
        ->where('stage', SdlStage::Monitoring)
        ->firstOrFail();

    expect($monitoring->status)->toBe(SdlStageStatus::Done)
        ->and($monitoring->notes)->toContain('14 days');

    $this->actingAs($owner)
        ->from(route('products.sdl.edit', [$product, $run]))
        ->put(route('products.sdl.stages.update', [
            'product' => $product,
            'sdlRun' => $run,
            'stage' => SdlStage::CodeReview->value,
        ]), [
            'status' => SdlStageStatus::Na->value,
            'notes' => 'Should stay locked',
            'evidence_ids' => [],
        ])
        ->assertRedirect(route('products.sdl.edit', [$product, $run]))
        ->assertSessionHasErrors('status');

    expect(
        $run->stageEntries()
            ->where('stage', SdlStage::CodeReview)
            ->firstOrFail()
            ->status,
    )->toBe(SdlStageStatus::Done);
});

test('dashboard counts approved SDL runs with pending monitoring', function () {
    ['owner' => $owner] = makeSdlMonitoringFixture();

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('Dashboard')
            ->where('dashboard.counts.sdl_approved', 1)
            ->where('dashboard.counts.sdl_pending_monitoring', 1)
            ->where('dashboard.actions', function ($actions) {
                $action = collect($actions)->firstWhere('key', 'sdl_pending_monitoring');

                return $action !== null
                    && ($action['count'] ?? null) === 1
                    && str_contains((string) ($action['href'] ?? ''), '/sdl');
            }));
});

test('completing monitoring clears pending dashboard count', function () {
    ['owner' => $owner, 'product' => $product, 'run' => $run] = makeSdlMonitoringFixture();

    $this->actingAs($owner)
        ->put(route('products.sdl.stages.update', [
            'product' => $product,
            'sdlRun' => $run,
            'stage' => SdlStage::Monitoring->value,
        ]), [
            'status' => SdlStageStatus::Done->value,
            'notes' => 'Monitoring closed',
            'evidence_ids' => [],
        ])
        ->assertRedirect();

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->where('dashboard.counts.sdl_approved', 1)
            ->where('dashboard.counts.sdl_pending_monitoring', 0)
            ->where('dashboard.actions', function ($actions) {
                return collect($actions)->firstWhere('key', 'sdl_pending_monitoring') === null;
            }));
});
