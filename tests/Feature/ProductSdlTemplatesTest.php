<?php

use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ProductVersionState;
use App\Enums\ScopeStatus;
use App\Enums\SdlRunStatus;
use App\Enums\SdlStage;
use App\Enums\SupportStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVersion;
use App\Models\Role;
use App\Models\SdlRun;
use App\Models\SdlStageEntry;
use App\Models\User;
use App\Support\SdlStageNoteTemplates;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     organization: Organization,
 *     owner: User,
 *     product: Product
 * }
 */
function makeSdlTemplateFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'SDL Template Org',
        'slug' => 'sdl-template-org-' . uniqid(),
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
        'name' => 'SDL Template Product',
        'slug' => 'sdl-template-product-' . uniqid(),
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

    ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '1.0.0',
        'state' => ProductVersionState::Draft,
        'support_status' => SupportStatus::Supported,
    ]);

    return compact('organization', 'owner', 'product');
}

test('creating sdl run without template leaves stage notes empty', function () {
    ['owner' => $owner, 'product' => $product] = makeSdlTemplateFixture();

    $this->actingAs($owner)
        ->post(route('products.sdl.store', $product), [
            'title' => 'No template',
            'status' => SdlRunStatus::Draft->value,
            'current_stage' => SdlStage::Requirement->value,
            'use_template' => false,
        ])
        ->assertRedirect();

    $run = SdlRun::query()->where('title', 'No template')->firstOrFail();

    expect(
        SdlStageEntry::query()
            ->where('sdl_run_id', $run->id)
            ->whereNotNull('notes')
            ->where('notes', '!=', '')
            ->count(),
    )->toBe(0);
});

test('creating sdl run with english templates prefills templated stages only', function () {
    ['owner' => $owner, 'product' => $product] = makeSdlTemplateFixture();

    $this->actingAs($owner)
        ->post(route('products.sdl.store', $product), [
            'title' => 'EN templates',
            'status' => SdlRunStatus::Draft->value,
            'current_stage' => SdlStage::Requirement->value,
            'use_template' => true,
            'locale' => 'en',
        ])
        ->assertRedirect();

    $run = SdlRun::query()->where('title', 'EN templates')->firstOrFail();
    $threat = SdlStageEntry::query()
        ->where('sdl_run_id', $run->id)
        ->where('stage', SdlStage::ThreatReview->value)
        ->firstOrFail();
    $design = SdlStageEntry::query()
        ->where('sdl_run_id', $run->id)
        ->where('stage', SdlStage::Design->value)
        ->firstOrFail();
    $development = SdlStageEntry::query()
        ->where('sdl_run_id', $run->id)
        ->where('stage', SdlStage::Development->value)
        ->firstOrFail();

    expect($threat->notes)->toContain('Threat considerations')
        ->and($development->notes)->toContain('Secure coding checklist')
        ->and($design->notes)->toBeNull()
        ->and(
            SdlStageEntry::query()
                ->where('sdl_run_id', $run->id)
                ->whereNotNull('notes')
                ->count(),
        )->toBe(count(SdlStageNoteTemplates::templatedStages()));
});

test('creating sdl run with bulgarian templates uses cyrillic checklist content', function () {
    ['owner' => $owner, 'product' => $product] = makeSdlTemplateFixture();

    $this->actingAs($owner)
        ->post(route('products.sdl.store', $product), [
            'title' => 'BG templates',
            'status' => SdlRunStatus::Draft->value,
            'current_stage' => SdlStage::Requirement->value,
            'use_template' => true,
            'locale' => 'bg',
        ])
        ->assertRedirect();

    $run = SdlRun::query()->where('title', 'BG templates')->firstOrFail();
    $threat = SdlStageEntry::query()
        ->where('sdl_run_id', $run->id)
        ->where('stage', SdlStage::ThreatReview->value)
        ->firstOrFail();

    expect($threat->notes)->toContain('Преглед на заплахи')
        ->and($threat->notes)->not->toContain('Threat considerations');
});

test('invalid template locale is rejected by validation', function () {
    ['owner' => $owner, 'product' => $product] = makeSdlTemplateFixture();

    $this->actingAs($owner)
        ->post(route('products.sdl.store', $product), [
            'title' => 'Fallback locale',
            'status' => SdlRunStatus::Draft->value,
            'current_stage' => SdlStage::Requirement->value,
            'use_template' => true,
            'locale' => 'de',
        ])
        ->assertSessionHasErrors('locale');
});

test('stage templates endpoint returns markdown payloads', function () {
    [
        'organization' => $organization,
        'owner' => $owner,
        'product' => $product,
    ] = makeSdlTemplateFixture();

    $this->actingAs($owner)
        ->getJson(route('products.sdl.stage-templates', $product) . '?locale=bg')
        ->assertOk()
        ->assertJsonPath('locale', 'bg')
        ->assertJsonPath(
            'stages.threat_review',
            SdlStageNoteTemplates::notesFor(SdlStage::ThreatReview, 'bg'),
        )
        ->assertJsonMissingPath('stages.design');

    $viewer = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);
    $viewerRole = Role::query()->where('slug', 'read_only')->firstOrFail();
    $organization->users()->attach($viewer->id, [
        'role_id' => $viewerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($viewer)
        ->getJson(route('products.sdl.stage-templates', $product))
        ->assertOk();

    $this->actingAs($owner)
        ->get(route('products.sdl.create', $product))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/sdl/Create')
            ->where('options.default_locale', 'en')
            ->has('options.template_stages', 6));

    $run = SdlRun::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Edit templates',
        'status' => SdlRunStatus::Draft,
        'current_stage' => SdlStage::Requirement,
    ]);
    $run->ensureStageEntries();

    $this->actingAs($owner)
        ->get(route('products.sdl.edit', [$product, $run]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/sdl/Edit')
            ->has('stage_note_templates.threat_review')
            ->missing('stage_note_templates.design'));
});
