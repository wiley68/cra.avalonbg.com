<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\OrganizationSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeSystemAdmin(): User
{
    test()->seed([RolePermissionSeeder::class, OrganizationSeeder::class]);

    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'is_system_admin' => true,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $organization = Organization::query()->firstOrFail();
    $ownerRole = Role::query()->where('slug', 'organization_owner')->firstOrFail();

    $admin->organizations()->attach($organization->id, [
        'role_id' => $ownerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $admin;
}

function makeOrganizationOwner(): User
{
    test()->seed([RolePermissionSeeder::class, OrganizationSeeder::class]);

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'is_system_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $organization = Organization::query()->firstOrFail();
    $ownerRole = Role::query()->where('slug', 'organization_owner')->firstOrFail();

    $owner->organizations()->attach($organization->id, [
        'role_id' => $ownerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $owner;
}

function makeDeveloper(): User
{
    test()->seed([RolePermissionSeeder::class, OrganizationSeeder::class]);

    $developer = User::factory()->create([
        'email_verified_at' => now(),
        'is_system_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $organization = Organization::query()->firstOrFail();
    $role = Role::query()->where('slug', 'developer')->firstOrFail();

    $developer->organizations()->attach($organization->id, [
        'role_id' => $role->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $developer;
}

test('system admin can view organizations index', function () {
    $admin = makeSystemAdmin();

    $this->actingAs($admin)
        ->get(route('admin.organizations.index'))
        ->assertOk();
});

test('system admin can create organization with owner', function () {
    $admin = makeSystemAdmin();

    $response = $this->actingAs($admin)->post(route('admin.organizations.store'), [
        'name' => 'Acme Soft',
        'slug' => 'acme-soft',
        'billing_email' => 'billing@acme.test',
        'is_active' => true,
        'create_owner' => true,
        'owner_name' => 'Acme Owner',
        'owner_email' => 'owner@acme.test',
        'owner_password' => 'OwnerPassword!123',
        'owner_password_confirmation' => 'OwnerPassword!123',
    ]);

    $organization = Organization::query()->where('slug', 'acme-soft')->first();

    expect($organization)->not->toBeNull();
    $response->assertRedirect(route('admin.organizations.edit', $organization));
    $this->assertDatabaseHas('users', ['email' => 'owner@acme.test']);
    expect($organization->users()->where('email', 'owner@acme.test')->exists())->toBeTrue();
});

test('organization owner cannot access admin organizations', function () {
    $owner = makeOrganizationOwner();

    $this->actingAs($owner)
        ->get(route('admin.organizations.index'))
        ->assertForbidden();
});

test('system admin can view nested organization users', function () {
    $admin = makeSystemAdmin();
    $organization = Organization::query()->firstOrFail();

    $this->actingAs($admin)
        ->get(route('admin.organizations.users.index', $organization))
        ->assertOk();
});

test('system admin can create nested organization user', function () {
    $admin = makeSystemAdmin();
    $organization = Organization::query()->firstOrFail();
    $role = Role::query()->where('slug', 'developer')->firstOrFail();

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
    $owner = makeOrganizationOwner();
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
    $developer = makeDeveloper();

    $this->actingAs($developer)
        ->get(route('users.index'))
        ->assertForbidden();
});

test('cannot remove the last organization owner', function () {
    $admin = makeSystemAdmin();
    $organization = Organization::query()->firstOrFail();

    $this->actingAs($admin)
        ->from(route('admin.organizations.users.index', $organization))
        ->delete(route('admin.organizations.users.destroy', [$organization, $admin]))
        ->assertRedirect()
        ->assertSessionHasErrors('user');

    expect($organization->users()->where('users.id', $admin->id)->exists())->toBeTrue();
});

test('can remove non-owner user from organization', function () {
    $owner = makeOrganizationOwner();
    $organization = Organization::query()->firstOrFail();
    $role = Role::query()->where('slug', 'developer')->firstOrFail();

    $member = User::factory()->create([
        'email_verified_at' => now(),
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $organization->users()->attach($member->id, [
        'role_id' => $role->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($owner)
        ->delete(route('users.destroy', $member))
        ->assertRedirect(route('users.index'));

    expect($organization->users()->where('users.id', $member->id)->exists())->toBeFalse();
});
