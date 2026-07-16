<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\OrganizationSeeder;
use Database\Seeders\RolePermissionSeeder;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeAdminWithOrganization(): User
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

test('admin can view users index', function () {
    $admin = makeAdminWithOrganization();

    $response = $this->actingAs($admin)->get(route('admin.users.index'));

    $response->assertOk();
});

test('admin can create user', function () {
    $admin = makeAdminWithOrganization();
    $role = Role::query()->where('slug', 'developer')->firstOrFail();

    $response = $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'New Team User',
        'email' => 'new.user@example.com',
        'password' => 'NewUserPassword!123',
        'password_confirmation' => 'NewUserPassword!123',
        'role_id' => $role->id,
        'must_change_password' => true,
    ]);

    $response->assertRedirect(route('admin.users.index'));
    $this->assertDatabaseHas('users', ['email' => 'new.user@example.com']);
});

