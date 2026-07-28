<?php

use App\Enums\BillingStatus;
use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\PaymentMethod;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\SubscriptionPlan;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\StripeBillingService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User}
 */
function makeDunningOrg(
    string $status = 'past_due',
    mixed $pastDueAt = null,
): array {
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Dunning Org',
        'slug' => 'dunning-org-' . uniqid(),
        'is_active' => true,
        'subscription_plan' => SubscriptionPlan::Small->value,
        'billing_status' => $status,
        'billing_interval' => 'month',
        'payment_method' => PaymentMethod::Stripe->value,
        'billing_email' => 'billing@dunning.test',
        'billing_activated_at' => now()->subMonths(2),
        'billing_past_due_at' => $pastDueAt,
        'stripe_customer_id' => 'cus_dunning',
        'stripe_subscription_id' => 'sub_dunning',
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

    return compact('organization', 'owner');
}

test('past due within grace shares soft billing notice', function () {
    Config::set('billing.dunning.grace_days', 14);
    Config::set('billing.stripe.secret', 'sk_test_dummy');

    ['owner' => $owner, 'organization' => $organization] = makeDunningOrg(
        pastDueAt: now()->subDays(2),
    );

    expect($organization->isInBillingGrace())->toBeTrue()
        ->and($organization->canAddProduct())->toBeFalse();

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->where('billing_notice.status', BillingStatus::PastDue->value)
            ->where('billing_notice.in_grace', true)
            ->where('billing_notice.read_only_hint', false)
            ->where('billing_notice.can_manage_stripe', true));
});

test('past due after grace shares read-only hint without deleting org', function () {
    Config::set('billing.dunning.grace_days', 14);

    ['owner' => $owner, 'organization' => $organization] = makeDunningOrg(
        pastDueAt: now()->subDays(20),
    );

    expect($organization->isInBillingGrace())->toBeFalse()
        ->and($organization->fresh()->exists)->toBeTrue()
        ->and($organization->canAddProduct())->toBeFalse();

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->where('billing_notice.status', BillingStatus::PastDue->value)
            ->where('billing_notice.in_grace', false)
            ->where('billing_notice.read_only_hint', true));
});

test('cancelled shares notice and retains organization data', function () {
    ['owner' => $owner, 'organization' => $organization] = makeDunningOrg(
        status: BillingStatus::Cancelled->value,
    );

    Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Kept Product',
        'slug' => 'kept-product',
        'product_type' => ProductType::Software->value,
        'licensing_model' => LicensingModel::Paid->value,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => false,
        'scope_status' => ScopeStatus::LikelyInScope->value,
        'classification_status' => ClassificationStatus::Unclassified->value,
    ]);

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->where('billing_notice.status', BillingStatus::Cancelled->value)
            ->where('billing_notice.read_only_hint', true));

    expect($organization->fresh()->exists)->toBeTrue()
        ->and($organization->products()->count())->toBe(1)
        ->and($organization->canAddProduct())->toBeFalse();
});

test('active billing clears shared billing notice', function () {
    ['owner' => $owner] = makeDunningOrg(status: BillingStatus::Active->value);

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->where('billing_notice', null));
});

test('stripe past due sets billing_past_due_at and reactivation clears it', function () {
    ['organization' => $organization] = makeDunningOrg(
        status: BillingStatus::Active->value,
        pastDueAt: null,
    );

    $service = app(StripeBillingService::class);

    $service->handleSubscriptionUpdated([
        'id' => 'sub_dunning',
        'customer' => 'cus_dunning',
        'status' => 'past_due',
        'metadata' => [
            'organization_id' => (string) $organization->id,
            'subscription_plan' => 'small',
            'billing_interval' => 'month',
        ],
    ]);

    $organization->refresh();

    expect($organization->billing_status)->toBe(BillingStatus::PastDue)
        ->and($organization->billing_past_due_at)->not->toBeNull();

    $firstPastDueAt = $organization->billing_past_due_at;

    $this->travel(3)->days();

    $service->handleSubscriptionUpdated([
        'id' => 'sub_dunning',
        'customer' => 'cus_dunning',
        'status' => 'past_due',
        'metadata' => [
            'organization_id' => (string) $organization->id,
            'subscription_plan' => 'small',
            'billing_interval' => 'month',
        ],
    ]);

    $organization->refresh();

    expect($organization->billing_past_due_at?->equalTo($firstPastDueAt))->toBeTrue();

    $service->handleSubscriptionUpdated([
        'id' => 'sub_dunning',
        'customer' => 'cus_dunning',
        'status' => 'active',
        'metadata' => [
            'organization_id' => (string) $organization->id,
            'subscription_plan' => 'small',
            'billing_interval' => 'month',
        ],
    ]);

    $organization->refresh();

    expect($organization->billing_status)->toBe(BillingStatus::Active)
        ->and($organization->billing_past_due_at)->toBeNull()
        ->and($organization->fresh()->exists)->toBeTrue();
});

test('products index exposes past due quota lock', function () {
    ['owner' => $owner] = makeDunningOrg(pastDueAt: now()->subDay());

    $pastDue = BillingStatus::PastDue->value;

    $this->actingAs($owner)
        ->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('products/Index')
            ->where('productQuota.billing_status', $pastDue)
            ->where('productQuota.can_create', false));
});
