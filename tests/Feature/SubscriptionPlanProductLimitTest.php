<?php

use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\SubscriptionPlan;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{0: Organization, 1: User}
 */
function makeOrgWithPlan(string $plan): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Quota Org',
        'slug' => 'quota-org-' . uniqid(),
        'is_active' => true,
        'subscription_plan' => $plan,
    ]);

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $ownerRole = Role::query()->where('slug', 'organization_owner')->firstOrFail();

    $organization->users()->attach($owner->id, [
        'role_id' => $ownerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [$organization, $owner];
}

function seedProduct(Organization $organization, string $slug): Product
{
    return Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Product ' . $slug,
        'slug' => $slug,
        'product_type' => ProductType::Software->value,
        'licensing_model' => LicensingModel::Paid->value,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => false,
        'scope_status' => ScopeStatus::LikelyInScope->value,
        'classification_status' => ClassificationStatus::Unclassified->value,
    ]);
}

function createProductPayload(string $slug): array
{
    return [
        'name' => 'New ' . $slug,
        'slug' => $slug,
        'product_type' => ProductType::Software->value,
        'licensing_model' => LicensingModel::Paid->value,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => false,
        'scope_status' => ScopeStatus::LikelyInScope->value,
        'classification_status' => ClassificationStatus::Unclassified->value,
        'skip_scope_wizard' => true,
        'skip_classification_wizard' => true,
    ];
}

test('subscription plan catalog matches freeze limits', function () {
    expect(SubscriptionPlan::Free->maxProducts())->toBe(1)
        ->and(SubscriptionPlan::Small->maxProducts())->toBe(3)
        ->and(SubscriptionPlan::Standard->maxProducts())->toBe(10)
        ->and(SubscriptionPlan::Enterprise->maxProducts())->toBeNull()
        ->and(SubscriptionPlan::Free->maxSeats())->toBe(2)
        ->and(SubscriptionPlan::Small->maxSeats())->toBe(5)
        ->and(SubscriptionPlan::Standard->maxSeats())->toBe(15)
        ->and(SubscriptionPlan::Enterprise->maxSeats())->toBeNull();
});

test('missing subscription plan defaults to free', function () {
    $organization = new Organization(['subscription_plan' => null]);

    expect($organization->resolvedSubscriptionPlan())->toBe(SubscriptionPlan::Free)
        ->and($organization->maxProducts())->toBe(1);
});

test('unknown subscription plan defaults to free', function () {
    expect(SubscriptionPlan::tryFromStored('solo'))->toBeNull()
        ->and(SubscriptionPlan::fromStoredOrDefault('solo'))->toBe(SubscriptionPlan::Free);
});

test('free plan blocks creating a second product', function () {
    [$organization, $owner] = makeOrgWithPlan(SubscriptionPlan::Free->value);
    seedProduct($organization, 'only-one');

    $this->actingAs($owner)
        ->post(route('products.store'), createProductPayload('second'))
        ->assertSessionHasErrors('name');

    expect(Product::query()->where('organization_id', $organization->id)->count())->toBe(1);
});

test('free plan allows the first product', function () {
    [$organization, $owner] = makeOrgWithPlan(SubscriptionPlan::Free->value);

    $this->actingAs($owner)
        ->post(route('products.store'), createProductPayload('first-free'))
        ->assertRedirect();

    expect(Product::query()->where('organization_id', $organization->id)->count())->toBe(1);
});

test('small plan allows three products then blocks the fourth', function () {
    [$organization, $owner] = makeOrgWithPlan(SubscriptionPlan::Small->value);

    foreach (['a', 'b', 'c'] as $slug) {
        seedProduct($organization, $slug);
    }

    expect($organization->fresh()->canAddProduct())->toBeFalse();

    $this->actingAs($owner)
        ->post(route('products.store'), createProductPayload('d'))
        ->assertSessionHasErrors('name');

    expect(Product::query()->where('organization_id', $organization->id)->count())->toBe(3);
});

test('create product page redirects when plan limit is reached', function () {
    [$organization, $owner] = makeOrgWithPlan(SubscriptionPlan::Free->value);
    seedProduct($organization, 'only-one');

    $this->actingAs($owner)
        ->get(route('products.create'))
        ->assertRedirect(route('products.index'));
});

test('products index includes product quota payload', function () {
    [$organization, $owner] = makeOrgWithPlan(SubscriptionPlan::Free->value);
    seedProduct($organization, 'only-one');

    $this->actingAs($owner)
        ->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/Index')
            ->where('productQuota.plan', 'free')
            ->where('productQuota.max_products', 1)
            ->where('productQuota.used', 1)
            ->where('productQuota.can_create', false));
});

test('admin organization create accepts canonical subscription plans', function () {
    test()->seed([RolePermissionSeeder::class]);

    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => true,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.organizations.store'), [
            'name' => 'Plan Org',
            'slug' => 'plan-org',
            'subscription_plan' => SubscriptionPlan::Standard->value,
            'is_active' => true,
            'locale' => 'en',
            'create_owner' => false,
            'seed_starter_controls' => false,
        ])
        ->assertRedirect();

    expect(Organization::query()->where('slug', 'plan-org')->value('subscription_plan'))
        ->toBe(SubscriptionPlan::Standard->value);
});

test('admin organization create rejects unknown subscription plans', function () {
    test()->seed([RolePermissionSeeder::class]);

    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => true,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.organizations.store'), [
            'name' => 'Bad Plan Org',
            'slug' => 'bad-plan-org',
            'subscription_plan' => 'solo',
            'is_active' => true,
            'locale' => 'en',
            'create_owner' => false,
            'seed_starter_controls' => false,
        ])
        ->assertSessionHasErrors('subscription_plan');
});
