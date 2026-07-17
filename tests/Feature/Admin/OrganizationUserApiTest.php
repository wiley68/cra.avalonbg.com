<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function makeUsersApiPlatformAdmin(): User
{
    return User::factory()->create([
        'email_verified_at' => now(),
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
        'is_platform_admin' => true,
    ]);
}

function makeUsersApiOrganizationWithOwner(): array
{
    $organization = Organization::query()->create([
        'name' => 'Users Api Org',
        'slug' => 'users-api-org',
        'is_active' => true,
    ]);

    $ownerRole = Role::query()->where('slug', 'organization_owner')->firstOrFail();

    $owner = User::factory()->create([
        'name' => 'Org Owner',
        'email' => 'owner@users-api.test',
        'email_verified_at' => now(),
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
        'is_platform_admin' => false,
    ]);

    $organization->users()->attach($owner->id, [
        'role_id' => $ownerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $developerRole = Role::query()->where('slug', 'developer')->firstOrFail();

    $developer = User::factory()->create([
        'name' => 'Dev User',
        'email' => 'dev@users-api.test',
        'email_verified_at' => now(),
        'must_change_password' => true,
        'two_factor_confirmed_at' => now(),
        'is_platform_admin' => false,
    ]);

    $organization->users()->attach($developer->id, [
        'role_id' => $developerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [$organization, $owner, $developer];
}

test('platform admin can load organization users index shell without table data', function () {
    $admin = makeUsersApiPlatformAdmin();
    [$organization] = makeUsersApiOrganizationWithOwner();

    $this->actingAs($admin)
        ->get(route('admin.organizations.users.index', $organization))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('admin/organizations/users/Index')
            ->has('organization')
            ->missing('users'));
});

test('platform admin can fetch paginated organization users from internal api', function () {
    $admin = makeUsersApiPlatformAdmin();
    [$organization] = makeUsersApiOrganizationWithOwner();

    $response = $this->actingAs($admin)
        ->getJson(route('admin.internal.organizations.users.index', [
            'organization' => $organization,
            'page' => 1,
            'per_page' => 10,
            'sort_by' => 'name',
            'sort_desc' => '0',
            'search' => 'Dev',
        ]));

    $response
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.name', 'Dev User')
        ->assertJsonPath('data.0.email', 'dev@users-api.test')
        ->assertJsonPath('data.0.role_slug', 'developer');
});

test('organization owner can fetch workspace users from internal api', function () {
    [$organization, $owner] = makeUsersApiOrganizationWithOwner();

    $response = $this->actingAs($owner)
        ->getJson(route('internal.users.index', [
            'page' => 1,
            'per_page' => 10,
            'sort_by' => 'email',
            'sort_desc' => '0',
            'search' => 'owner@',
        ]));

    $response
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.email', 'owner@users-api.test');
});

test('workspace users index shell does not include users prop', function () {
    [, $owner] = makeUsersApiOrganizationWithOwner();

    $this->actingAs($owner)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('users/Index')
            ->has('organization')
            ->missing('users'));
});
