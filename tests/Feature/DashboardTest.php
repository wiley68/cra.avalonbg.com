<?php

use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\TaskApprovalStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated organization owner sees action dashboard', function () {
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Acme Soft',
        'slug' => 'acme-soft',
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'two_factor_confirmed_at' => now(),
        'must_change_password' => false,
    ]);

    $ownerRole = Role::query()->where('slug', 'organization_owner')->firstOrFail();
    $organization->users()->attach($user->id, [
        'role_id' => $ownerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $organizationId = $organization->id;
    $expectedMode = 'organization';

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('dashboard', fn (Assert $dashboard) => $dashboard
                ->where('mode', $expectedMode)
                ->has('organization', fn (Assert $org) => $org
                    ->where('id', $organizationId)
                    ->etc())
                ->has('actions')
                ->has('counts')
                ->etc()));
});

test('platform admin without org sees platform dashboard', function () {
    test()->seed([RolePermissionSeeder::class]);

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'two_factor_confirmed_at' => now(),
        'must_change_password' => false,
        'is_platform_admin' => true,
    ]);

    $expectedMode = 'platform';

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('dashboard', fn (Assert $dashboard) => $dashboard
                ->where('mode', $expectedMode)
                ->etc()));
});

test('open tasks action links to tasks index and previews up to three tasks', function () {
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Acme Soft',
        'slug' => 'acme-soft',
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'two_factor_confirmed_at' => now(),
        'must_change_password' => false,
    ]);

    $ownerRole = Role::query()->where('slug', 'organization_owner')->firstOrFail();
    $organization->users()->attach($user->id, [
        'role_id' => $ownerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Gateway',
        'slug' => 'gateway',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => true,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
        'scope_reviewed_at' => now(),
        'scope_reviewed_by' => $user->id,
        'classification_reviewed_at' => now(),
        'classification_reviewed_by' => $user->id,
    ]);

    foreach (['Fix auth', 'Patch TLS', 'Rotate keys', 'Update docs'] as $title) {
        Task::query()->create([
            'organization_id' => $organization->id,
            'product_id' => $product->id,
            'title' => $title,
            'status' => TaskStatus::Open,
            'priority' => TaskPriority::Medium,
            'approval_status' => TaskApprovalStatus::NotRequired,
            'created_by' => $user->id,
        ]);
    }

    $firstTaskId = Task::query()->where('title', 'Fix auth')->value('id');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('dashboard.counts.open_tasks', 4)
            ->has('dashboard.actions')
            ->where('dashboard.actions', function ($actions) use ($product, $firstTaskId): bool {
                $openTasks = collect($actions)->firstWhere('key', 'open_tasks');

                expect($openTasks)->not->toBeNull()
                    ->and($openTasks['count'])->toBe(4)
                    ->and($openTasks['href'])->toBe(route('products.tasks.index', $product))
                    ->and($openTasks['items'])->toHaveCount(3)
                    ->and($openTasks['items'][0]['title'])->toBe('Fix auth')
                    ->and($openTasks['items'][0]['href'])->toBe(route('products.tasks.edit', [
                        $product->id,
                        $firstTaskId,
                    ]));

                return true;
            }));
});
