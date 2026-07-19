<?php

use App\Enums\ControlAutomationLevel;
use App\Enums\ControlFrequency;
use App\Enums\ProductControlStatus;
use App\Models\Control;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductControl;
use App\Models\Requirement;
use App\Models\Role;
use App\Models\User;
use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use Database\Seeders\ControlCatalogueSeeder;
use Database\Seeders\RequirementCatalogueSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{0: Organization, 1: User}
 */
function makeControlsOrgWithOwner(): array
{
    test()->seed([RolePermissionSeeder::class, RequirementCatalogueSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Controls Org',
        'slug' => 'controls-org',
        'is_active' => true,
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

    return [$organization, $owner];
}

function makeControlsOrgDeveloper(Organization $organization): User
{
    $developer = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $role = Role::query()->where('slug', 'developer')->firstOrFail();

    $organization->users()->attach($developer->id, [
        'role_id' => $role->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $developer;
}

function makeProductForControls(Organization $organization, User $owner): Product
{
    return Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Payments Module Controls',
        'slug' => 'payments-module-controls',
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
}

test('control catalogue seeder creates controls linked to requirements', function () {
    [$organization] = makeControlsOrgWithOwner();

    (new ControlCatalogueSeeder)->seedForOrganization($organization);

    expect(Control::query()->where('organization_id', $organization->id)->count())->toBeGreaterThanOrEqual(10);

    $control = Control::query()
        ->where('organization_id', $organization->id)
        ->where('code', 'CTL-DEP-SCAN')
        ->firstOrFail();

    expect($control->requirements()->count())->toBeGreaterThan(0)
        ->and($control->source->value)->toBe('starter_template')
        ->and($control->name_bg)->not->toBeNull();
});

test('control list resolves bulgarian names when locale is bg', function () {
    [$organization, $owner] = makeControlsOrgWithOwner();
    (new ControlCatalogueSeeder)->seedForOrganization($organization);

    $english = $this->actingAs($owner)
        ->withSession(['locale' => 'en'])
        ->getJson(route('internal.controls.index') . '?per_page=100')
        ->assertOk()
        ->json('data');

    $bulgarian = $this->actingAs($owner)
        ->withSession(['locale' => 'bg'])
        ->getJson(route('internal.controls.index') . '?per_page=100')
        ->assertOk()
        ->json('data');

    $enItem = collect($english)->firstWhere('code', 'CTL-DEP-SCAN');
    $bgItem = collect($bulgarian)->firstWhere('code', 'CTL-DEP-SCAN');

    expect($enItem)->not->toBeNull()
        ->and($bgItem)->not->toBeNull()
        ->and($enItem['name'])->not->toBe($bgItem['name'])
        ->and($bgItem['name'])->toContain('Сканиране');
});

test('refresh starter controls updates template rows and skips custom', function () {
    [$organization, $owner] = makeControlsOrgWithOwner();
    (new ControlCatalogueSeeder)->seedForOrganization($organization);

    $template = Control::query()
        ->where('organization_id', $organization->id)
        ->where('code', 'CTL-DEP-SCAN')
        ->firstOrFail();

    $template->update(['name' => 'Changed template name']);

    $custom = Control::query()->create([
        'organization_id' => $organization->id,
        'code' => 'CTL-CUSTOM',
        'name' => 'Custom control',
        'automation_level' => ControlAutomationLevel::Manual,
        'frequency' => ControlFrequency::OnDemand,
        'is_active' => true,
        'source' => \App\Enums\ControlSource::Custom,
    ]);

    $this->actingAs($owner)
        ->post(route('controls.refresh-starter'))
        ->assertRedirect(route('controls.index'));

    expect($template->fresh()->name)->toBe('Dependency scanning before release')
        ->and($custom->fresh()->name)->toBe('Custom control');
});

test('organization owner can create control with requirement links', function () {
    [$organization, $owner] = makeControlsOrgWithOwner();
    $requirement = Requirement::query()->where('code', 'CRA-AI-02')->firstOrFail();

    $this->actingAs($owner)
        ->post(route('controls.store'), [
            'code' => 'CTL-TEST',
            'name' => 'Test control',
            'description' => 'Desc',
            'implementation_guidance' => 'Guide',
            'automation_level' => ControlAutomationLevel::Manual->value,
            'frequency' => ControlFrequency::PerRelease->value,
            'is_active' => true,
            'requirement_ids' => [$requirement->id],
        ])
        ->assertRedirect();

    $control = Control::query()
        ->where('organization_id', $organization->id)
        ->where('code', 'CTL-TEST')
        ->firstOrFail();

    expect($control->requirements()->pluck('requirements.id')->all())->toContain($requirement->id);
});

test('developer cannot manage controls but can view index', function () {
    [$organization, $owner] = makeControlsOrgWithOwner();
    $developer = makeControlsOrgDeveloper($organization);
    (new ControlCatalogueSeeder)->seedForOrganization($organization);

    $this->actingAs($developer)
        ->get(route('controls.index'))
        ->assertOk();

    $this->actingAs($developer)
        ->post(route('controls.store'), [
            'code' => 'CTL-FORBIDDEN',
            'name' => 'Forbidden',
            'automation_level' => ControlAutomationLevel::Manual->value,
            'frequency' => ControlFrequency::AdHoc->value,
            'is_active' => true,
        ])
        ->assertForbidden();
});

test('owner can assign control to product and update status', function () {
    [$organization, $owner] = makeControlsOrgWithOwner();
    $product = makeProductForControls($organization, $owner);
    (new ControlCatalogueSeeder)->seedForOrganization($organization);

    $control = Control::query()
        ->where('organization_id', $organization->id)
        ->where('code', 'CTL-DEP-SCAN')
        ->firstOrFail();

    $this->actingAs($owner)
        ->post(route('products.controls.store', $product), [
            'control_id' => $control->id,
            'status' => ProductControlStatus::Planned->value,
            'notes' => 'Queued for next release',
        ])
        ->assertRedirect();

    $productControl = ProductControl::query()
        ->where('product_id', $product->id)
        ->where('control_id', $control->id)
        ->firstOrFail();

    expect($productControl->status)->toBe(ProductControlStatus::Planned);

    $this->actingAs($owner)
        ->put(route('products.controls.update', [$product, $productControl]), [
            'status' => ProductControlStatus::InPlace->value,
            'notes' => 'Live in CI',
        ])
        ->assertRedirect();

    expect($productControl->fresh()->status)->toBe(ProductControlStatus::InPlace);
});

test('requirements edit exposes linked controls for product', function () {
    [$organization, $owner] = makeControlsOrgWithOwner();
    $product = makeProductForControls($organization, $owner);
    (new ControlCatalogueSeeder)->seedForOrganization($organization);

    $control = Control::query()
        ->where('organization_id', $organization->id)
        ->where('code', 'CTL-DEP-SCAN')
        ->firstOrFail();

    $requirementId = $control->requirements()->value('requirements.id');
    expect($requirementId)->not->toBeNull();

    app(\App\Services\ProductRequirementService::class)->ensureMatrix($product);

    $productRequirement = \App\Models\ProductRequirement::query()
        ->where('product_id', $product->id)
        ->where('requirement_id', $requirementId)
        ->firstOrFail();

    $this->actingAs($owner)
        ->get(route('products.requirements.edit', [$product, $productRequirement]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/requirements/Edit')
            ->has('linkedControls')
            ->where('linkedControls.0.code', 'CTL-DEP-SCAN'));
});

test('internal api lists org controls', function () {
    [$organization, $owner] = makeControlsOrgWithOwner();
    (new ControlCatalogueSeeder)->seedForOrganization($organization);

    $this->actingAs($owner)
        ->getJson(route('internal.controls.index'))
        ->assertOk()
        ->assertJsonStructure(['data', 'current_page', 'per_page', 'total']);
});
