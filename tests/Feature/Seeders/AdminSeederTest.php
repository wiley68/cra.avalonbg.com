<?php

use Database\Seeders\AdminUserSeeder;
use Database\Seeders\OrganizationSeeder;
use Database\Seeders\RolePermissionSeeder;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('admin seeder creates configured administrator', function () {
    config([
        'app.env' => 'testing',
    ]);

    $this->seed([
        RolePermissionSeeder::class,
        OrganizationSeeder::class,
        AdminUserSeeder::class,
    ]);

    $admin = \App\Models\User::query()->where('email', env('ADMIN_EMAIL', 'home@avalonbg.com'))->first();

    expect($admin)->not->toBeNull();
    expect($admin->is_system_admin)->toBeTrue();
    expect($admin->must_change_password)->toBeFalse();
});

