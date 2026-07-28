<?php

use App\Enums\AuditEventType;
use App\Enums\BankPaymentRequestStatus;
use App\Enums\BillingInterval;
use App\Enums\BillingStatus;
use App\Enums\PaymentMethod;
use App\Enums\SubscriptionPlan;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationBankPaymentRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\StripeCheckoutGateway;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User}
 */
function makeBillingPortalOrg(
    string $plan = 'free',
    string $status = 'active',
    ?string $interval = null,
): array {
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Portal Org',
        'slug' => 'portal-org-' . uniqid(),
        'is_active' => true,
        'subscription_plan' => $plan,
        'billing_status' => $status,
        'billing_interval' => $interval,
        'billing_email' => 'billing@portal.test',
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

test('billing portal page shows interval payment method and change plan for free org', function () {
    ['owner' => $owner] = makeBillingPortalOrg();
    $freePlan = SubscriptionPlan::Free->value;

    $this->actingAs($owner)
        ->get(route('settings.billing.edit'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('settings/Billing')
            ->where('organization.subscription_plan', $freePlan)
            ->where('organization.billing_interval', null)
            ->where('canChangePlan', true)
            ->where('canManageStripe', false)
            ->has('subscriptionPlans', 4));
});

test('owner can change plan from free to paid pending', function () {
    ['organization' => $organization, 'owner' => $owner] = makeBillingPortalOrg();

    $this->actingAs($owner)
        ->post(route('settings.billing.change-plan'), [
            'subscription_plan' => SubscriptionPlan::Standard->value,
            'billing_interval' => BillingInterval::Year->value,
        ])
        ->assertRedirect(route('settings.billing.edit'));

    $organization->refresh();

    expect($organization->subscription_plan)->toBe(SubscriptionPlan::Standard->value)
        ->and($organization->billing_interval)->toBe(BillingInterval::Year)
        ->and($organization->billing_status)->toBe(BillingStatus::PendingPayment)
        ->and($organization->billing_activated_at)->toBeNull();

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::SubscriptionPlanChanged->value)
        ->exists())->toBeTrue();
});

test('owner can change paid pending plan and refresh bank request amounts', function () {
    ['organization' => $organization, 'owner' => $owner] = makeBillingPortalOrg(
        plan: 'small',
        status: 'pending_payment',
        interval: 'month',
    );

    OrganizationBankPaymentRequest::query()->create([
        'organization_id' => $organization->id,
        'subscription_plan' => SubscriptionPlan::Small->value,
        'billing_interval' => BillingInterval::Month->value,
        'amount_eur' => 29,
        'currency' => 'EUR',
        'payment_reference' => 'CRA-PORTAL-1',
        'status' => BankPaymentRequestStatus::Pending->value,
        'requested_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->post(route('settings.billing.change-plan'), [
            'subscription_plan' => SubscriptionPlan::Enterprise->value,
            'billing_interval' => BillingInterval::Year->value,
        ])
        ->assertRedirect(route('settings.billing.edit'));

    $organization->refresh();
    $pending = $organization->bankPaymentRequests()->where('status', BankPaymentRequestStatus::Pending->value)->first();

    expect($organization->subscription_plan)->toBe(SubscriptionPlan::Enterprise->value)
        ->and($organization->billing_interval)->toBe(BillingInterval::Year)
        ->and($pending)->not->toBeNull()
        ->and((float) $pending->amount_eur)->toBe(566.40);
});

test('stripe managed org cannot change plan in app and can open portal', function () {
    Config::set('billing.stripe.secret', 'sk_test_dummy');
    ['organization' => $organization, 'owner' => $owner] = makeBillingPortalOrg(
        plan: 'small',
        status: 'active',
        interval: 'month',
    );
    $organization->forceFill([
        'payment_method' => PaymentMethod::Stripe->value,
        'stripe_customer_id' => 'cus_portal_test',
        'stripe_subscription_id' => 'sub_portal_test',
        'billing_activated_at' => now(),
    ])->save();

    $this->actingAs($owner)
        ->get(route('settings.billing.edit'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->where('canManageStripe', true)
            ->where('canChangePlan', false));

    $this->actingAs($owner)
        ->post(route('settings.billing.change-plan'), [
            'subscription_plan' => SubscriptionPlan::Standard->value,
            'billing_interval' => BillingInterval::Month->value,
        ])
        ->assertSessionHasErrors('subscription_plan');

    $this->mock(StripeCheckoutGateway::class, function ($mock) {
        $mock->shouldReceive('createBillingPortalSession')
            ->once()
            ->with('cus_portal_test', Mockery::type('string'))
            ->andReturn([
                'id' => 'bps_test',
                'url' => 'https://billing.stripe.test/session/test',
            ]);
    });

    $this->actingAs($owner)
        ->post(route('settings.billing.stripe.portal'))
        ->assertRedirect('https://billing.stripe.test/session/test');
});

test('stripe portal requires linked customer', function () {
    Config::set('billing.stripe.secret', 'sk_test_dummy');
    ['organization' => $organization, 'owner' => $owner] = makeBillingPortalOrg(
        plan: 'small',
        status: 'active',
        interval: 'month',
    );
    $organization->forceFill([
        'payment_method' => PaymentMethod::Stripe->value,
        'billing_activated_at' => now(),
    ])->save();

    $this->actingAs($owner)
        ->post(route('settings.billing.stripe.portal'))
        ->assertSessionHasErrors('stripe');
});
