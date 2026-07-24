<?php

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('roles and permissions are seeded from config', function () {
    $this->seed(RolePermissionSeeder::class);

    $roles = Role::query()->pluck('slug');
    $permissions = Permission::query()->pluck('slug');

    expect($roles)->toContain('platform_admin', 'organization_owner', 'developer');
    expect($roles)->not->toContain('administrator');
    expect($permissions)->toContain(
        'platform.admin',
        'users.create',
        'audit.view',
        'incidents.view',
        'incidents.manage',
        'sdl.view',
        'sdl.manage',
        'technical_documentation.view',
        'technical_documentation.manage',
    );

    $platformAdmin = Role::query()
        ->where('slug', 'platform_admin')
        ->with('permissions')
        ->firstOrFail();

    $slugs = $platformAdmin->permissions->pluck('slug');

    expect($slugs)->toContain('platform.admin', 'organizations.manage', 'users.create', 'audit.view');
    expect($slugs)->not->toContain('products.view');
});
