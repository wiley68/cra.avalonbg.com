<?php

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\PlatformAdminSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('platform admin seeder creates configured admin without organization', function () {
    $this->seed([
        RolePermissionSeeder::class,
        PlatformAdminSeeder::class,
    ]);

    $admin = User::query()
        ->where('email', 'ilko@avalonbg.com')
        ->first();

    expect($admin)->not->toBeNull();
    expect($admin->is_platform_admin)->toBeTrue();
    expect($admin->must_change_password)->toBeFalse();
    expect($admin->email_verified_at)->not->toBeNull();
    expect($admin->organizations()->count())->toBe(0);
    expect(Organization::query()->count())->toBe(0);
});
