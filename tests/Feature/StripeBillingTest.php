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
use App\Services\StripeBillingService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User}
 */
function makeStripeOrg(
    string $plan = 'small',
    string $status = 'pending_payment',
    string $interval = 'month',
): array {
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Stripe Org',
        'slug' => 'stripe-org-' . uniqid(),
        'is_active' => true,
        'subscription_plan' => $plan,
        'billing_status' => $status,
        'billing_interval' => $interval,
        'billing_email' => 'billing@stripe.test',
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

    return [
        'organization' => $organization,
        'owner' => $owner,
    ];
}

function stripeSignature(string $payload, string $secret): string
{
    $timestamp = time();
    $signed = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

    return "t={$timestamp},v1={$signed}";
}

test('checkout session completed webhook activates organization via stripe', function () {
    Config::set('billing.stripe.webhook_secret', 'whsec_test_secret');
    $organization = makeStripeOrg()['organization'];

    OrganizationBankPaymentRequest::query()->create([
        'organization_id' => $organization->id,
        'subscription_plan' => SubscriptionPlan::Small->value,
        'billing_interval' => BillingInterval::Month->value,
        'amount_eur' => 29,
        'currency' => 'EUR',
        'payment_reference' => 'CRA-TEST-1',
        'status' => BankPaymentRequestStatus::Pending->value,
        'requested_by' => User::query()->firstOrFail()->id,
    ]);

    $payload = json_encode([
        'id' => 'evt_test_checkout',
        'object' => 'event',
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_test_1',
                'object' => 'checkout.session',
                'mode' => 'subscription',
                'customer' => 'cus_test_1',
                'subscription' => 'sub_test_1',
                'client_reference_id' => (string) $organization->id,
                'metadata' => [
                    'organization_id' => (string) $organization->id,
                    'subscription_plan' => 'small',
                    'billing_interval' => 'month',
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    $this->call(
        'POST',
        route('api.webhooks.stripe'),
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Stripe-Signature' => stripeSignature($payload, 'whsec_test_secret'),
        ],
        $payload,
    )->assertOk()->assertJson(['received' => true]);

    $organization->refresh();

    expect($organization->billing_status)->toBe(BillingStatus::Active)
        ->and($organization->payment_method)->toBe(PaymentMethod::Stripe)
        ->and($organization->stripe_customer_id)->toBe('cus_test_1')
        ->and($organization->stripe_subscription_id)->toBe('sub_test_1')
        ->and($organization->canAddProduct())->toBeTrue()
        ->and($organization->bankPaymentRequests()->first()->status)
        ->toBe(BankPaymentRequestStatus::Cancelled);

    expect(AuditLog::query()->where('event_type', AuditEventType::BillingActivated->value)->exists())
        ->toBeTrue();
});

test('subscription deleted webhook marks organization cancelled', function () {
    Config::set('billing.stripe.webhook_secret', 'whsec_test_secret');
    $organization = makeStripeOrg(status: 'active')['organization'];
    $organization->forceFill([
        'payment_method' => PaymentMethod::Stripe->value,
        'stripe_customer_id' => 'cus_cancel',
        'stripe_subscription_id' => 'sub_cancel',
        'billing_activated_at' => now(),
    ])->save();

    $payload = json_encode([
        'id' => 'evt_test_deleted',
        'object' => 'event',
        'type' => 'customer.subscription.deleted',
        'data' => [
            'object' => [
                'id' => 'sub_cancel',
                'object' => 'subscription',
                'customer' => 'cus_cancel',
                'status' => 'canceled',
                'metadata' => [
                    'organization_id' => (string) $organization->id,
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    $this->call(
        'POST',
        route('api.webhooks.stripe'),
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Stripe-Signature' => stripeSignature($payload, 'whsec_test_secret'),
        ],
        $payload,
    )->assertOk();

    expect($organization->fresh()->billing_status)->toBe(BillingStatus::Cancelled)
        ->and($organization->fresh()->canAddProduct())->toBeFalse();
});

test('invoice payment failed webhook marks organization past due', function () {
    Config::set('billing.stripe.webhook_secret', 'whsec_test_secret');
    $organization = makeStripeOrg(status: 'active')['organization'];
    $organization->forceFill([
        'payment_method' => PaymentMethod::Stripe->value,
        'stripe_customer_id' => 'cus_past',
        'stripe_subscription_id' => 'sub_past',
        'billing_activated_at' => now(),
    ])->save();

    $payload = json_encode([
        'id' => 'evt_test_failed',
        'object' => 'event',
        'type' => 'invoice.payment_failed',
        'data' => [
            'object' => [
                'id' => 'in_failed',
                'object' => 'invoice',
                'customer' => 'cus_past',
                'subscription' => 'sub_past',
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    $this->call(
        'POST',
        route('api.webhooks.stripe'),
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Stripe-Signature' => stripeSignature($payload, 'whsec_test_secret'),
        ],
        $payload,
    )->assertOk();

    expect($organization->fresh()->billing_status)->toBe(BillingStatus::PastDue)
        ->and($organization->fresh()->canAddProduct())->toBeFalse();
});

test('stripe webhook rejects invalid signature', function () {
    Config::set('billing.stripe.webhook_secret', 'whsec_test_secret');

    $payload = json_encode([
        'id' => 'evt_bad',
        'object' => 'event',
        'type' => 'checkout.session.completed',
        'data' => ['object' => []],
    ], JSON_THROW_ON_ERROR);

    $this->call(
        'POST',
        route('api.webhooks.stripe'),
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Stripe-Signature' => 't=1,v1=invalid',
        ],
        $payload,
    )->assertStatus(400);
});

test('tenant cannot start stripe checkout when stripe is not configured', function () {
    Config::set('billing.stripe.secret', '');
    $fixture = makeStripeOrg();
    $organization = $fixture['organization'];
    $owner = $fixture['owner'];

    $this->actingAs($owner)
        ->post(route('settings.billing.stripe.checkout'))
        ->assertSessionHasErrors('stripe');

    expect($organization->fresh()->billing_status)->toBe(BillingStatus::PendingPayment);
});

test('billing settings exposes stripe checkout flag when configured', function () {
    Config::set('billing.stripe.secret', 'sk_test_dummy');
    $owner = makeStripeOrg()['owner'];

    $this->actingAs($owner)
        ->get(route('settings.billing.edit'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('settings/Billing')
            ->where('canCheckoutStripe', true)
            ->where('stripeConfigured', true));
});

test('stripe billing service maps subscription updated to past due', function () {
    $organization = makeStripeOrg(status: 'active')['organization'];
    $organization->forceFill([
        'payment_method' => PaymentMethod::Stripe->value,
        'stripe_subscription_id' => 'sub_upd',
        'stripe_customer_id' => 'cus_upd',
        'billing_activated_at' => now(),
    ])->save();

    app(StripeBillingService::class)->handleSubscriptionUpdated([
        'id' => 'sub_upd',
        'customer' => 'cus_upd',
        'status' => 'past_due',
        'metadata' => [
            'organization_id' => (string) $organization->id,
            'subscription_plan' => 'small',
            'billing_interval' => 'year',
        ],
    ]);

    $organization->refresh();

    expect($organization->billing_status)->toBe(BillingStatus::PastDue)
        ->and($organization->billing_interval)->toBe(BillingInterval::Year);
});
