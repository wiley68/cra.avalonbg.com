<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class PlatformAdminSeeder extends Seeder
{
    /**
     * Seed the platform admin user (no organization membership).
     */
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'platform_admin@avalonbg.com'],
            [
                'name' => 'Platform Администратор',
                'password' => '1Nikola@Stefanov9',
                'is_platform_admin' => true,
                'must_change_password' => false,
                'password_changed_at' => Carbon::now(),
            ],
        );

        // email_verified_at is not mass-assignable.
        $admin->forceFill(['email_verified_at' => Carbon::now()])->save();
    }
}
