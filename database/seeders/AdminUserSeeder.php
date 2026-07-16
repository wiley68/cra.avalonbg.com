<?php

namespace Database\Seeders;

use App\Enums\RoleSlug;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'home@avalonbg.com')],
            [
                'name' => env('ADMIN_NAME', 'Илко Администратор'),
                'password' => env('ADMIN_PASSWORD', '1Nikola@Stefanov9'),
                'is_system_admin' => true,
                'must_change_password' => false,
                'password_changed_at' => Carbon::now(),
                'email_verified_at' => Carbon::now(),
            ],
        );

        $organization = Organization::query()->where('slug', 'avalon')->first();
        $ownerRole = Role::query()->where('slug', RoleSlug::OrganizationOwner->value)->first();

        if ($organization && $ownerRole) {
            $admin->organizations()->syncWithoutDetaching([
                $organization->id => [
                    'role_id' => $ownerRole->id,
                    'invited_by' => $admin->id,
                    'joined_at' => Carbon::now(),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ],
            ]);
        }
    }
}

