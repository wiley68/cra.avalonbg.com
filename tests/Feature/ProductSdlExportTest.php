<?php

use App\Enums\AuditEventType;
use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\SdlRunStatus;
use App\Enums\SdlStage;
use App\Enums\SdlStageStatus;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Role;
use App\Models\SdlRun;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     organization: Organization,
 *     owner: User,
 *     viewer: User,
 *     product: Product,
 *     run: SdlRun
 * }
 */
function makeSdlExportFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'SDL Export Org',
        'slug' => 'sdl-export-org-' . uniqid(),
        'is_active' => true,
        'locale' => 'en',
    ]);

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $viewer = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $ownerRole = Role::query()->where('slug', 'organization_owner')->firstOrFail();
    $viewerRole = Role::query()->where('slug', 'read_only')->firstOrFail();

    $organization->users()->attach($owner->id, [
        'role_id' => $ownerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $organization->users()->attach($viewer->id, [
        'role_id' => $viewerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'SDL Export Product',
        'slug' => 'sdl-export-product-' . uniqid(),
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
        'title' => 'Exportable SDL run',
        'status' => SdlRunStatus::InProgress,
        'current_stage' => SdlStage::ThreatReview,
        'owner_user_id' => $owner->id,
        'notes' => 'Release package context notes.',
    ]);
    $run->ensureStageEntries();

    $entry = $run->stageEntries()
        ->where('stage', SdlStage::ThreatReview)
        ->firstOrFail();
    $entry->update([
        'status' => SdlStageStatus::Done,
        'notes' => "Threat considerations:\n- Auth boundary review",
        'completed_at' => now(),
        'completed_by' => $owner->id,
    ]);

    return compact('organization', 'owner', 'viewer', 'product', 'run');
}

test('owner can export SDL run as markdown and pdf with audit', function () {
    ['owner' => $owner, 'product' => $product, 'run' => $run] = makeSdlExportFixture();

    $markdown = $this->actingAs($owner)
        ->get(route('products.sdl.export', [
            'product' => $product,
            'sdlRun' => $run,
            'format' => 'markdown',
        ]))
        ->assertOk();

    expect($markdown->headers->get('content-type'))->toContain('text/markdown')
        ->and($markdown->getContent())->toContain('Exportable SDL run')
        ->and($markdown->getContent())->toContain('Threat considerations')
        ->and($markdown->getContent())->toContain('Threat review')
        ->and($markdown->getContent())->toContain('Release package context notes');

    $pdf = $this->actingAs($owner)
        ->get(route('products.sdl.export', [
            'product' => $product,
            'sdlRun' => $run,
            'format' => 'pdf',
        ]))
        ->assertOk();

    expect($pdf->headers->get('content-type'))->toContain('application/pdf')
        ->and($pdf->getContent())->toStartWith('%PDF');

    expect(
        AuditLog::query()
            ->where('event_type', AuditEventType::SdlRunExported->value)
            ->count(),
    )->toBe(2);
});

test('viewer can export SDL run summary', function () {
    ['viewer' => $viewer, 'product' => $product, 'run' => $run] = makeSdlExportFixture();

    $this->actingAs($viewer)
        ->get(route('products.sdl.export', [
            'product' => $product,
            'sdlRun' => $run,
            'format' => 'markdown',
        ]))
        ->assertOk();
});

test('invalid SDL export format is not found', function () {
    ['owner' => $owner, 'product' => $product, 'run' => $run] = makeSdlExportFixture();

    $this->actingAs($owner)
        ->get('/products/' . $product->id . '/sdl/' . $run->id . '/export/html')
        ->assertNotFound();
});
