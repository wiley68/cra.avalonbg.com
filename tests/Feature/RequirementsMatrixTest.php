<?php

use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\RequirementApplicabilityStatus;
use App\Enums\ScopeStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductRequirement;
use App\Models\ProductRequirementHistory;
use App\Models\Requirement;
use App\Models\Role;
use App\Models\User;
use App\Services\ProductRequirementService;
use Database\Seeders\RequirementCatalogueSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{0: Organization, 1: User}
 */
function makeRequirementsOrgWithOwner(): array
{
    test()->seed([RolePermissionSeeder::class, RequirementCatalogueSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Req Org',
        'slug' => 'req-org',
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

function makeRequirementsOrgDeveloper(Organization $organization): User
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

function makeProductForRequirements(Organization $organization, User $owner): Product
{
    return Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Payments Module',
        'slug' => 'payments-module-req',
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

test('requirement catalogue seeder creates current versions', function () {
    test()->seed([RequirementCatalogueSeeder::class]);

    expect(Requirement::query()->count())->toBeGreaterThanOrEqual(15);
    expect(Requirement::query()->whereHas('currentVersion')->count())
        ->toBe(Requirement::query()->count());
});

test('ensure matrix creates not assessed rows for current requirements', function () {
    [$organization, $owner] = makeRequirementsOrgWithOwner();
    $product = makeProductForRequirements($organization, $owner);

    app(ProductRequirementService::class)->ensureMatrix($product);

    $expected = Requirement::query()->where('is_active', true)->whereHas('currentVersion')->count();

    expect(ProductRequirement::query()->where('product_id', $product->id)->count())->toBe($expected);
    expect(
        ProductRequirement::query()
            ->where('product_id', $product->id)
            ->where('status', RequirementApplicabilityStatus::NotAssessed)
            ->count(),
    )->toBe($expected);
});

test('owner can view matrix and update applicability with history', function () {
    [$organization, $owner] = makeRequirementsOrgWithOwner();
    $product = makeProductForRequirements($organization, $owner);

    $this->actingAs($owner)
        ->get(route('products.requirements.index', $product))
        ->assertOk();

    $row = ProductRequirement::query()->where('product_id', $product->id)->firstOrFail();

    $this->actingAs($owner)
        ->put(route('products.requirements.update', [$product, $row]), [
            'status' => RequirementApplicabilityStatus::Applicable->value,
            'rationale' => 'Networked commercial software',
            'owner_user_id' => $owner->id,
        ])
        ->assertRedirect(route('products.requirements.edit', [$product, $row]));

    $row->refresh();

    expect($row->status)->toBe(RequirementApplicabilityStatus::Applicable);
    expect($row->rationale)->toBe('Networked commercial software');
    expect($row->owner_user_id)->toBe($owner->id);
    expect($row->reviewed_by)->toBe($owner->id);
    expect(ProductRequirementHistory::query()->where('product_requirement_id', $row->id)->count())->toBe(1);
});

test('developer can view matrix but cannot update applicability', function () {
    [$organization, $owner] = makeRequirementsOrgWithOwner();
    $developer = makeRequirementsOrgDeveloper($organization);
    $product = makeProductForRequirements($organization, $owner);

    app(ProductRequirementService::class)->ensureMatrix($product);
    $row = ProductRequirement::query()->where('product_id', $product->id)->firstOrFail();

    $this->actingAs($developer)
        ->get(route('products.requirements.index', $product))
        ->assertOk();

    $this->actingAs($developer)
        ->put(route('products.requirements.update', [$product, $row]), [
            'status' => RequirementApplicabilityStatus::Implemented->value,
            'rationale' => 'Should fail',
        ])
        ->assertForbidden();
});

test('platform admin can open requirements catalogue', function () {
    test()->seed([RolePermissionSeeder::class, RequirementCatalogueSeeder::class]);

    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => true,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.requirements.index'))
        ->assertOk();

    $this->actingAs($admin)
        ->getJson(route('admin.internal.requirements.index'))
        ->assertOk()
        ->assertJsonStructure(['data', 'current_page', 'total']);
});

test('owner can list product requirements via internal api', function () {
    [$organization, $owner] = makeRequirementsOrgWithOwner();
    $product = makeProductForRequirements($organization, $owner);

    $this->actingAs($owner)
        ->getJson(route('internal.products.requirements.index', $product))
        ->assertOk()
        ->assertJsonPath('total', Requirement::query()->where('is_active', true)->count());
});
