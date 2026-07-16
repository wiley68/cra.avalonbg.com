<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Organization::query()->updateOrCreate(
            ['slug' => 'avalon'],
            [
                'name' => 'Avalon',
                'is_active' => true,
                'subscription_plan' => null,
                'trial_ends_at' => null,
                'billing_email' => env('ADMIN_EMAIL', 'home@avalonbg.com'),
            ],
        );
    }
}

