<?php

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function makeOrganizationApiAdmin(): User
{
    return User::factory()->create([
        'email_verified_at' => now(),
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
        'is_platform_admin' => true,
    ]);
}

test('platform admin can load organizations index shell without table data', function () {
    $admin = makeOrganizationApiAdmin();

    $this->actingAs($admin)
        ->get(route('admin.organizations.index'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('admin/organizations/Index')
            ->missing('organizations'));
});

test('platform admin can fetch paginated organizations from internal api', function () {
    $admin = makeOrganizationApiAdmin();

    Organization::query()->create([
        'name' => 'Alpha Org',
        'slug' => 'alpha-org',
        'is_active' => true,
    ]);
    Organization::query()->create([
        'name' => 'Beta Org',
        'slug' => 'beta-org',
        'is_active' => false,
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('admin.internal.organizations.index', [
            'page' => 1,
            'per_page' => 10,
            'sort_by' => 'name',
            'sort_desc' => '0',
            'search' => 'Alpha',
        ]));

    $response
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.name', 'Alpha Org')
        ->assertJsonPath('data.0.slug', 'alpha-org');
});

test('organization owner cannot access organizations internal api', function () {
    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
        'is_platform_admin' => false,
    ]);

    $this->actingAs($owner)
        ->getJson(route('admin.internal.organizations.index'))
        ->assertForbidden();
});
