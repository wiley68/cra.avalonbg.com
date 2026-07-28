<?php

use App\Enums\BankPaymentRequestStatus;
use App\Enums\BillingInterval;
use App\Enums\BillingStatus;
use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\PaymentMethod;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\SubscriptionPlan;
use App\Models\Organization;
use App\Models\OrganizationBankPaymentRequest;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{0: Organization, 1: User}
 */
function makePendingPaidOrg(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Pending Soft',
        'slug' => 'pending-soft-' . uniqid(),
        'is_active' => true,
        'subscription_plan' => SubscriptionPlan::Small->value,
        'billing_status' => BillingStatus::PendingPayment->value,
        'billing_interval' => BillingInterval::Month->value,
        'billing_email' => 'billing@pending.test',
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

function makePlatformAdminForBilling(): User
{
    test()->seed([RolePermissionSeeder::class]);

    return User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => true,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);
}

test('tenant can request bank payment when pending without existing request', function () {
    [$organization, $owner] = makePendingPaidOrg();

    $this->actingAs($owner)
        ->post(route('settings.billing.bank-payment.store'))
        ->assertRedirect(route('settings.billing.edit'));

    $request = OrganizationBankPaymentRequest::query()
        ->where('organization_id', $organization->id)
        ->first();

    expect($request)->not->toBeNull()
        ->and($request->status)->toBe(BankPaymentRequestStatus::Pending)
        ->and((float) $request->amount_eur)->toBe(29.0)
        ->and($request->payment_reference)->toStartWith('CRA-');
});

test('tenant cannot create a second pending bank payment request', function () {
    [$organization, $owner] = makePendingPaidOrg();

    $this->actingAs($owner)
        ->post(route('settings.billing.bank-payment.store'))
        ->assertRedirect();

    $this->actingAs($owner)
        ->post(route('settings.billing.bank-payment.store'))
        ->assertSessionHasErrors('bank_payment');

    expect(OrganizationBankPaymentRequest::query()
        ->where('organization_id', $organization->id)
        ->count())->toBe(1);
});

test('billing settings page shows pending bank instructions', function () {
    [$organization, $owner] = makePendingPaidOrg();

    $this->actingAs($owner)
        ->post(route('settings.billing.bank-payment.store'))
        ->assertRedirect();

    $this->actingAs($owner)
        ->get(route('settings.billing.edit'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('settings/Billing')
            ->where('organization.billing_status', BillingStatus::PendingPayment->value)
            ->where('canRequestBankPayment', false)
            ->has('pendingRequest.payment_reference')
            ->where('pendingRequest.amount_eur', 29));
});

test('admin activates billing on payment and unlocks products', function () {
    [$organization, $owner] = makePendingPaidOrg();

    $this->actingAs($owner)
        ->post(route('settings.billing.bank-payment.store'))
        ->assertRedirect();

    expect($organization->fresh()->canAddProduct())->toBeFalse();

    $admin = makePlatformAdminForBilling();

    $this->actingAs($admin)
        ->post(route('admin.organizations.activate-billing', $organization))
        ->assertRedirect(route('admin.organizations.edit', $organization));

    $organization->refresh();
    $request = $organization->bankPaymentRequests()->latest('id')->first();

    expect($organization->billing_status)->toBe(BillingStatus::Active)
        ->and($organization->payment_method)->toBe(PaymentMethod::Bank)
        ->and($organization->billing_activated_at)->not->toBeNull()
        ->and($organization->canAddProduct())->toBeTrue()
        ->and($request)->not->toBeNull()
        ->and($request->status)->toBe(BankPaymentRequestStatus::Paid)
        ->and($request->activated_by)->toBe($admin->id);

    $this->actingAs($owner)
        ->post(route('products.store'), [
            'name' => 'Unlocked Product',
            'slug' => 'unlocked-product',
            'product_type' => ProductType::Software->value,
            'licensing_model' => LicensingModel::Paid->value,
            'has_remote_data_processing' => false,
            'has_network_connectivity' => false,
            'scope_status' => ScopeStatus::LikelyInScope->value,
            'classification_status' => ClassificationStatus::Unclassified->value,
            'skip_scope_wizard' => true,
            'skip_classification_wizard' => true,
        ])
        ->assertRedirect();

    expect(Product::query()->where('organization_id', $organization->id)->count())->toBe(1);
});

test('admin can activate billing without a pending request', function () {
    [$organization] = makePendingPaidOrg();
    $admin = makePlatformAdminForBilling();

    $this->actingAs($admin)
        ->post(route('admin.organizations.activate-billing', $organization))
        ->assertRedirect();

    expect($organization->fresh()->billing_status)->toBe(BillingStatus::Active)
        ->and($organization->fresh()->payment_method)->toBe(PaymentMethod::Bank);
});

test('tenant cannot request bank payment for free plan', function () {
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Free Soft',
        'slug' => 'free-soft-' . uniqid(),
        'is_active' => true,
        'subscription_plan' => SubscriptionPlan::Free->value,
        'billing_status' => BillingStatus::Active->value,
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

    $this->actingAs($owner)
        ->post(route('settings.billing.bank-payment.store'))
        ->assertSessionHasErrors('subscription_plan');
});
