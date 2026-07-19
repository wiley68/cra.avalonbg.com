<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makePlatformAdmin(): User
{
    test()->seed([RolePermissionSeeder::class]);

    return User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => true,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);
}

function makeOrganizationWithOwner(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Acme Soft',
        'slug' => 'acme-soft',
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

function makeDeveloperInOrganization(Organization $organization): User
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

test('platform admin has no organization membership', function () {
    $admin = makePlatformAdmin();

    expect($admin->is_platform_admin)->toBeTrue();
    expect($admin->organizations()->count())->toBe(0);
    expect($admin->currentOrganization())->toBeNull();
});

test('platform admin can view organizations index', function () {
    $admin = makePlatformAdmin();

    $this->actingAs($admin)
        ->get(route('admin.organizations.index'))
        ->assertOk();
});

test('platform admin can create organization with owner', function () {
    $admin = makePlatformAdmin();
    test()->seed([\Database\Seeders\RequirementCatalogueSeeder::class]);

    $response = $this->actingAs($admin)->post(route('admin.organizations.store'), [
        'name' => 'New Tenant',
        'slug' => 'new-tenant',
        'billing_email' => 'billing@tenant.test',
        'is_active' => true,
        'create_owner' => true,
        'seed_starter_controls' => true,
        'owner_name' => 'Tenant Owner',
        'owner_email' => 'owner@tenant.test',
        'owner_password' => 'OwnerPassword!123',
        'owner_password_confirmation' => 'OwnerPassword!123',
    ]);

    $organization = Organization::query()->where('slug', 'new-tenant')->first();

    expect($organization)->not->toBeNull();
    $response->assertRedirect(route('admin.organizations.edit', $organization));
    $this->assertDatabaseHas('users', ['email' => 'owner@tenant.test', 'is_platform_admin' => false]);
    expect($organization->users()->where('email', 'owner@tenant.test')->exists())->toBeTrue();
    expect(User::query()->where('email', 'owner@tenant.test')->first()?->email_verified_at)->not->toBeNull();
    expect($admin->organizations()->count())->toBe(0);
    expect(\App\Models\Control::query()->where('organization_id', $organization->id)->count())
        ->toBe(count(\App\Support\StarterControlCatalogue::items()));
    expect($organization->locale)->toBe('en');
    expect(
        \App\Models\Control::query()
            ->where('organization_id', $organization->id)
            ->where('source', 'starter_template')
            ->where('name', 'Dependency scanning before release')
            ->exists(),
    )->toBeTrue();
});

test('platform admin can create organization with bulgarian locale and starter controls', function () {
    $admin = makePlatformAdmin();
    test()->seed([\Database\Seeders\RequirementCatalogueSeeder::class]);

    $this->actingAs($admin)->post(route('admin.organizations.store'), [
        'name' => 'BG Tenant',
        'slug' => 'bg-tenant',
        'is_active' => true,
        'locale' => 'bg',
        'create_owner' => false,
        'seed_starter_controls' => true,
    ])->assertRedirect();

    $organization = Organization::query()->where('slug', 'bg-tenant')->firstOrFail();

    expect($organization->locale)->toBe('bg');
    expect(
        \App\Models\Control::query()
            ->where('organization_id', $organization->id)
            ->where('code', 'CTL-DEP-SCAN')
            ->value('name'),
    )->toBe('Сканиране на зависимости преди release');
});

test('platform admin can create organization without starter controls', function () {
    $admin = makePlatformAdmin();

    $this->actingAs($admin)->post(route('admin.organizations.store'), [
        'name' => 'Empty Library Org',
        'slug' => 'empty-library-org',
        'is_active' => true,
        'create_owner' => false,
        'seed_starter_controls' => false,
    ])->assertRedirect();

    $organization = Organization::query()->where('slug', 'empty-library-org')->firstOrFail();

    expect(\App\Models\Control::query()->where('organization_id', $organization->id)->count())->toBe(0);
});

test('platform admin can permanently delete organization with members and products', function () {
    $admin = makePlatformAdmin();
    test()->seed([\Database\Seeders\RequirementCatalogueSeeder::class]);

    $this->actingAs($admin)->post(route('admin.organizations.store'), [
        'name' => 'Doomed Tenant',
        'slug' => 'doomed-tenant',
        'is_active' => true,
        'create_owner' => true,
        'seed_starter_controls' => true,
        'owner_name' => 'Doomed Owner',
        'owner_email' => 'doomed-owner@tenant.test',
        'owner_password' => 'OwnerPassword!123',
        'owner_password_confirmation' => 'OwnerPassword!123',
    ])->assertRedirect();

    $organization = Organization::query()->where('slug', 'doomed-tenant')->firstOrFail();
    $owner = User::query()->where('email', 'doomed-owner@tenant.test')->firstOrFail();

    $product = \App\Models\Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Doomed Product',
        'slug' => 'doomed-product',
        'product_type' => \App\Enums\ProductType::Software,
        'licensing_model' => \App\Enums\LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => false,
        'scope_status' => \App\Enums\ScopeStatus::InsufficientInformation,
        'classification_status' => \App\Enums\ClassificationStatus::Unclassified,
    ]);

    $organizationId = $organization->id;
    $productId = $product->id;
    $ownerId = $owner->id;
    $controlIds = \App\Models\Control::query()
        ->where('organization_id', $organizationId)
        ->pluck('id')
        ->all();

    expect($controlIds)->not->toBeEmpty();

    $this->actingAs($admin)
        ->delete(route('admin.organizations.destroy', $organization))
        ->assertRedirect(route('admin.organizations.index'));

    expect(Organization::query()->find($organizationId))->toBeNull()
        ->and(\App\Models\Product::query()->find($productId))->toBeNull()
        ->and(User::query()->find($ownerId))->toBeNull()
        ->and(
            \App\Models\Control::query()->whereIn('id', $controlIds)->count(),
        )->toBe(0)
        ->and(User::query()->find($admin->id))->not->toBeNull();
});

test('organization owner cannot delete organizations', function () {
    [, $owner] = makeOrganizationWithOwner();
    $organization = Organization::query()->firstOrFail();

    $this->actingAs($owner)
        ->delete(route('admin.organizations.destroy', $organization))
        ->assertForbidden();
});

test('organization owner can permanently delete their organization from profile settings', function () {
    [$organization, $owner] = makeOrganizationWithOwner();

    $product = \App\Models\Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Owner Product',
        'slug' => 'owner-product',
        'product_type' => \App\Enums\ProductType::Software,
        'licensing_model' => \App\Enums\LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => false,
        'scope_status' => \App\Enums\ScopeStatus::InsufficientInformation,
        'classification_status' => \App\Enums\ClassificationStatus::Unclassified,
    ]);

    $organizationId = $organization->id;
    $productId = $product->id;
    $ownerId = $owner->id;
    $organizationName = $organization->name;

    $this->actingAs($owner)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('settings/Profile')
            ->where('canDeleteOrganization', true)
            ->where('deletableOrganization.id', $organizationId));

    $this->actingAs($owner)
        ->delete(route('settings.organization.destroy'), [
            'password' => 'password',
            'confirmation' => $organizationName,
        ])
        ->assertRedirect(route('home'));

    $this->assertGuest();

    expect(Organization::query()->find($organizationId))->toBeNull()
        ->and(\App\Models\Product::query()->find($productId))->toBeNull()
        ->and(User::query()->find($ownerId))->toBeNull();
});

test('organization owner must confirm organization name to delete it', function () {
    [$organization, $owner] = makeOrganizationWithOwner();

    $this->actingAs($owner)
        ->from(route('profile.edit'))
        ->delete(route('settings.organization.destroy'), [
            'password' => 'password',
            'confirmation' => 'Wrong Name',
        ])
        ->assertSessionHasErrors('confirmation')
        ->assertRedirect(route('profile.edit'));

    expect($organization->fresh())->not->toBeNull()
        ->and($owner->fresh())->not->toBeNull();
});

test('developer cannot delete organization from profile settings', function () {
    [$organization] = makeOrganizationWithOwner();
    $developer = makeDeveloperInOrganization($organization);

    $this->actingAs($developer)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->where('canDeleteOrganization', false));

    $this->actingAs($developer)
        ->delete(route('settings.organization.destroy'), [
            'password' => 'password',
            'confirmation' => $organization->name,
        ])
        ->assertForbidden();
});

test('organization owner cannot access admin organizations', function () {
    [, $owner] = makeOrganizationWithOwner();

    $this->actingAs($owner)
        ->get(route('admin.organizations.index'))
        ->assertForbidden();
});

test('platform admin can manage nested organization users', function () {
    $admin = makePlatformAdmin();
    [$organization] = makeOrganizationWithOwner();
    $role = Role::query()->where('slug', 'developer')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('admin.organizations.users.index', $organization))
        ->assertOk();

    $response = $this->actingAs($admin)->post(
        route('admin.organizations.users.store', $organization),
        [
            'name' => 'Nested User',
            'email' => 'nested.user@example.com',
            'password' => 'NewUserPassword!123',
            'password_confirmation' => 'NewUserPassword!123',
            'role_id' => $role->id,
            'must_change_password' => true,
        ],
    );

    $response->assertRedirect(route('admin.organizations.users.index', $organization));
    $this->assertDatabaseHas('users', ['email' => 'nested.user@example.com']);
});

test('organization owner can manage workspace users', function () {
    [, $owner] = makeOrganizationWithOwner();
    $role = Role::query()->where('slug', 'developer')->firstOrFail();

    $this->actingAs($owner)
        ->get(route('users.index'))
        ->assertOk();

    $response = $this->actingAs($owner)->post(route('users.store'), [
        'name' => 'Workspace User',
        'email' => 'workspace.user@example.com',
        'password' => 'NewUserPassword!123',
        'password_confirmation' => 'NewUserPassword!123',
        'role_id' => $role->id,
        'must_change_password' => true,
    ]);

    $response->assertRedirect(route('users.index'));
    $this->assertDatabaseHas('users', ['email' => 'workspace.user@example.com']);
});

test('developer cannot manage users', function () {
    [$organization] = makeOrganizationWithOwner();
    $developer = makeDeveloperInOrganization($organization);

    $this->actingAs($developer)
        ->get(route('users.index'))
        ->assertForbidden();
});

test('platform admin cannot delete the last organization owner', function () {
    $admin = makePlatformAdmin();
    [$organization, $owner] = makeOrganizationWithOwner();

    $this->actingAs($admin)
        ->from(route('admin.organizations.users.index', $organization))
        ->delete(route('admin.organizations.users.destroy', [$organization, $owner]))
        ->assertRedirect()
        ->assertSessionHasErrors('user');

    expect($organization->users()->where('users.id', $owner->id)->exists())->toBeTrue();
    $this->assertDatabaseHas('users', ['id' => $owner->id]);
});

test('organization owner can delete a non-owner user', function () {
    [$organization, $owner] = makeOrganizationWithOwner();
    $member = makeDeveloperInOrganization($organization);

    $this->actingAs($owner)
        ->delete(route('users.destroy', $member))
        ->assertRedirect(route('users.index'));

    expect($organization->users()->where('users.id', $member->id)->exists())->toBeFalse();
    $this->assertDatabaseMissing('users', ['id' => $member->id]);
});

test('platform admin can delete a non-owner organization user', function () {
    $admin = makePlatformAdmin();
    [$organization] = makeOrganizationWithOwner();
    $member = makeDeveloperInOrganization($organization);

    $this->actingAs($admin)
        ->delete(route('admin.organizations.users.destroy', [$organization, $member]))
        ->assertRedirect(route('admin.organizations.users.index', $organization));

    expect($organization->users()->where('users.id', $member->id)->exists())->toBeFalse();
    $this->assertDatabaseMissing('users', ['id' => $member->id]);
});

test('platform admin does not receive product permissions', function () {
    $admin = makePlatformAdmin();

    expect($admin->hasPermission('products.view'))->toBeFalse();
    expect($admin->hasPermission('platform.admin'))->toBeTrue();
    expect($admin->hasPermission('organizations.manage'))->toBeTrue();
    expect($admin->hasPermission('users.create'))->toBeTrue();
    expect($admin->hasPermission('users.delete'))->toBeTrue();
    expect($admin->hasPermission('audit.view'))->toBeTrue();
});
