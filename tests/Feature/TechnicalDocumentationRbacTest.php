<?php

use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\PermissionSlug;
use App\Enums\ProductType;
use App\Enums\RoleScope;
use App\Enums\ScopeStatus;
use App\Enums\TechnicalDocumentationStatus;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\TechnicalDocumentationPackage;
use App\Models\User;
use App\Services\TechnicalDocumentationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     organization: Organization,
 *     owner: User,
 *     viewer: User,
 *     product: Product,
 *     package: TechnicalDocumentationPackage
 * }
 */
function makeTechDocRbacFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Tech Doc RBAC Org',
        'slug' => 'tech-doc-rbac-org-' . uniqid(),
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
        'name' => 'Tech Doc RBAC Product',
        'slug' => 'tech-doc-rbac-product-' . uniqid(),
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => true,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    $package = app(TechnicalDocumentationService::class)->create($product, [
        'title' => 'RBAC technical documentation',
        'version_label' => '1.0',
        'locale' => 'en',
        'notes' => null,
        'inherit_from_previous' => false,
        'product_version_id' => null,
        'user_security_instruction_id' => null,
        'sdl_run_id' => null,
    ], $owner);

    return compact('organization', 'owner', 'viewer', 'product', 'package');
}

test('owner can open technical documentation index and create pages with manage access', function () {
    ['owner' => $owner, 'product' => $product] = makeTechDocRbacFixture();

    $this->actingAs($owner)
        ->get(route('products.technical-documentation.index', $product))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/technical-documentation/Index')
            ->where('canManage', true));

    $this->actingAs($owner)
        ->get(route('products.technical-documentation.create', $product))
        ->assertOk()
        ->assertInertia(fn($page) => $page->component('products/technical-documentation/Create'));
});

test('viewer can view technical documentation index and edit but cannot manage', function () {
    [
        'viewer' => $viewer,
        'product' => $product,
        'package' => $package,
    ] = makeTechDocRbacFixture();

    $this->actingAs($viewer)
        ->get(route('products.technical-documentation.index', $product))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/technical-documentation/Index')
            ->where('canManage', false));

    $this->actingAs($viewer)
        ->get(route('products.technical-documentation.edit', [$product, $package]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/technical-documentation/Edit')
            ->where('canManage', false)
            ->where('package.id', $package->id));

    $this->actingAs($viewer)
        ->getJson(route('internal.products.technical-documentation.index', $product))
        ->assertOk()
        ->assertJsonPath('total', 1);

    $this->actingAs($viewer)
        ->get(route('products.technical-documentation.create', $product))
        ->assertForbidden();

    $this->actingAs($viewer)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => 'Forbidden create',
            'version_label' => '2.0',
            'locale' => 'en',
        ])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->put(route('products.technical-documentation.update', [$product, $package]), [
            'title' => 'Hacked title',
            'version_label' => '9.9',
            'locale' => 'en',
            'sections' => [],
        ])
        ->assertForbidden();

    expect($package->fresh()->title)->toBe('RBAC technical documentation')
        ->and($package->fresh()->status)->toBe(TechnicalDocumentationStatus::Draft);

    $this->actingAs($viewer)
        ->delete(route('products.technical-documentation.destroy', [$product, $package]))
        ->assertForbidden();

    expect(TechnicalDocumentationPackage::query()->whereKey($package->id)->exists())->toBeTrue();
});

test('seeded roles include dedicated technical documentation permissions', function () {
    test()->seed([RolePermissionSeeder::class]);

    expect(Permission::query()->pluck('slug'))
        ->toContain(
            PermissionSlug::TechnicalDocumentationView->value,
            PermissionSlug::TechnicalDocumentationManage->value,
        );

    $owner = Role::query()->where('slug', 'organization_owner')->with('permissions')->firstOrFail();
    $viewer = Role::query()->where('slug', 'read_only')->with('permissions')->firstOrFail();
    $securityOwner = Role::query()->where('slug', 'security_owner')->with('permissions')->firstOrFail();

    expect($owner->permissions->pluck('slug'))
        ->toContain(
            PermissionSlug::TechnicalDocumentationView->value,
            PermissionSlug::TechnicalDocumentationManage->value,
        )
        ->and($viewer->permissions->pluck('slug'))
        ->toContain(PermissionSlug::TechnicalDocumentationView->value)
        ->not->toContain(PermissionSlug::TechnicalDocumentationManage->value)
        ->and($securityOwner->permissions->pluck('slug'))
        ->not->toContain(
            PermissionSlug::TechnicalDocumentationView->value,
            PermissionSlug::TechnicalDocumentationManage->value,
        );
});

test('products manage alone does not grant technical documentation manage', function () {
    [
        'organization' => $organization,
        'product' => $product,
        'package' => $package,
    ] = makeTechDocRbacFixture();

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $role = Role::query()->create([
        'slug' => 'products-manage-no-tech-doc-' . uniqid(),
        'name' => 'Products manage without technical documentation',
        'description' => 'Products access without dedicated technical documentation permissions',
        'scope' => RoleScope::Organization,
        'is_default' => false,
    ]);

    $role->permissions()->sync(
        Permission::query()
            ->whereIn('slug', [
                PermissionSlug::ProductsView->value,
                PermissionSlug::ProductsManage->value,
            ])
            ->pluck('id'),
    );

    $organization->users()->attach($user->id, [
        'role_id' => $role->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('products.technical-documentation.index', $product))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('products.technical-documentation.edit', [$product, $package]))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => 'Should fail',
            'version_label' => '1.0',
            'locale' => 'en',
        ])
        ->assertForbidden();
});

test('shared auth exposes can_view_technical_documentation and can_manage_technical_documentation', function () {
    ['owner' => $owner, 'viewer' => $viewer, 'product' => $product] = makeTechDocRbacFixture();

    $this->actingAs($owner)
        ->get(route('products.technical-documentation.index', $product))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->where('auth.user.can_view_technical_documentation', true)
            ->where('auth.user.can_manage_technical_documentation', true));

    $this->actingAs($viewer)
        ->get(route('products.technical-documentation.index', $product))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->where('auth.user.can_view_technical_documentation', true)
            ->where('auth.user.can_manage_technical_documentation', false));
});

test('technical documentation view alone does not grant manage', function () {
    [
        'organization' => $organization,
        'product' => $product,
        'package' => $package,
    ] = makeTechDocRbacFixture();

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $role = Role::query()->create([
        'slug' => 'tech-doc-view-only-' . uniqid(),
        'name' => 'Technical documentation view only',
        'description' => 'View products and technical documentation without manage',
        'scope' => RoleScope::Organization,
        'is_default' => false,
    ]);

    $role->permissions()->sync(
        Permission::query()
            ->whereIn('slug', [
                PermissionSlug::ProductsView->value,
                PermissionSlug::TechnicalDocumentationView->value,
            ])
            ->pluck('id'),
    );

    $organization->users()->attach($user->id, [
        'role_id' => $role->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('products.technical-documentation.index', $product))
        ->assertOk()
        ->assertInertia(fn($page) => $page->where('canManage', false));

    $this->actingAs($user)
        ->get(route('products.technical-documentation.edit', [$product, $package]))
        ->assertOk()
        ->assertInertia(fn($page) => $page->where('canManage', false));

    $this->actingAs($user)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => 'Should fail',
            'version_label' => '1.0',
            'locale' => 'en',
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->put(route('products.technical-documentation.update', [$product, $package]), [
            'title' => 'Should fail',
            'version_label' => '1.0',
            'locale' => 'en',
            'sections' => [],
        ])
        ->assertForbidden();

    expect($package->fresh()->title)->toBe('RBAC technical documentation');
});
