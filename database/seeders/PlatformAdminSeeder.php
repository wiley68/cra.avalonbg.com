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
        User::query()->updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'home@avalonbg.com')],
            [
                'name' => env('ADMIN_NAME', 'Илко Администратор'),
                'password' => env('ADMIN_PASSWORD', '1Nikola@Stefanov9'),
                'is_platform_admin' => true,
                'must_change_password' => false,
                'password_changed_at' => Carbon::now(),
                'email_verified_at' => Carbon::now(),
            ],
        );
    }
}
