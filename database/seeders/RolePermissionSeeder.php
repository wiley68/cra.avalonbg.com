<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $permissions = [];

        foreach (config('cra.permissions', []) as $slug => $data) {
            $permissions[$slug] = Permission::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $data['name'],
                    'group' => $data['group'] ?? null,
                ],
            );
        }

        foreach (config('cra.roles', []) as $slug => $data) {
            $role = Role::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'scope' => $data['scope'],
                    'is_default' => $data['scope'] === 'organization',
                ],
            );

            $role->permissions()->sync(
                collect($data['permissions'])
                    ->map(fn (string $permissionSlug) => $permissions[$permissionSlug]->id ?? null)
                    ->filter()
                    ->values()
                    ->all(),
            );
        }
    }
}

