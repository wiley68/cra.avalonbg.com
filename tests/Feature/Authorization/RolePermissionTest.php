<?php

use Database\Seeders\RolePermissionSeeder;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('roles and permissions are seeded from config', function () {
    $this->seed(RolePermissionSeeder::class);

    $roles = \App\Models\Role::query()->pluck('slug');
    $permissions = \App\Models\Permission::query()->pluck('slug');

    expect($roles)->toContain('administrator', 'organization_owner', 'developer');
    expect($permissions)->toContain('platform.admin', 'users.create', 'audit.view');
});

