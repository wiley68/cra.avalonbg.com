<?php

use App\Enums\BillingInterval;
use App\Enums\BillingStatus;
use App\Enums\PaymentMethod;
use App\Enums\SubscriptionPlan;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schedule;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('billing.promo_codes', [
        'TRIAL14' => [
            'trial_days' => 14,
            'plans' => null,
            'active' => true,
        ],
        'STANDARD30' => [
            'trial_days' => 30,
            'plans' => ['standard'],
            'active' => true,
        ],
    ]);
});

/**
 * @return array{organization: Organization, owner: User}
 */
function makePromoOrg(
    string $plan = 'small',
    string $status = 'pending_payment',
): array {
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Promo Org',
        'slug' => 'promo-org-' . uniqid(),
        'is_active' => true,
        'subscription_plan' => $plan,
        'billing_status' => $status,
        'billing_interval' => 'month',
        'billing_email' => 'billing@promo.test',
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

test('paid registration with promo starts active trial without bank request', function () {
    test()->skipUnlessFortifyHas(Features::registration());
    test()->seed(RolePermissionSeeder::class);

    $this->post(route('register.store'), [
        'name' => 'Trial User',
        'email' => 'trial@example.com',
        'password' => 'Password1!x',
        'password_confirmation' => 'Password1!x',
        'organization_name' => 'Trial Co',
        'subscription_plan' => SubscriptionPlan::Small->value,
        'billing_interval' => BillingInterval::Month->value,
        'promo_code' => 'trial14',
        'locale' => 'en',
    ])->assertRedirect();

    $organization = Organization::query()->where('slug', 'trial-co')->first();

    expect($organization)->not->toBeNull()
        ->and($organization->billing_status)->toBe(BillingStatus::Active)
        ->and($organization->promo_code)->toBe('TRIAL14')
        ->and($organization->trial_ends_at)->not->toBeNull()
        ->and($organization->trial_ends_at?->isFuture())->toBeTrue()
        ->and($organization->isOnTrial())->toBeTrue()
        ->and($organization->canAddProduct())->toBeTrue()
        ->and($organization->bankPaymentRequests()->count())->toBe(0);
});

test('registration rejects promo for free plan', function () {
    test()->skipUnlessFortifyHas(Features::registration());
    test()->seed(RolePermissionSeeder::class);

    $this->post(route('register.store'), [
        'name' => 'Free Promo',
        'email' => 'freepromo@example.com',
        'password' => 'Password1!x',
        'password_confirmation' => 'Password1!x',
        'organization_name' => 'Free Promo Co',
        'subscription_plan' => SubscriptionPlan::Free->value,
        'promo_code' => 'TRIAL14',
        'locale' => 'en',
    ])->assertSessionHasErrors('promo_code');
});

test('registration rejects promo for disallowed plan', function () {
    test()->skipUnlessFortifyHas(Features::registration());
    test()->seed(RolePermissionSeeder::class);

    $this->post(route('register.store'), [
        'name' => 'Wrong Plan',
        'email' => 'wrongpromo@example.com',
        'password' => 'Password1!x',
        'password_confirmation' => 'Password1!x',
        'organization_name' => 'Wrong Promo Co',
        'subscription_plan' => SubscriptionPlan::Small->value,
        'billing_interval' => BillingInterval::Month->value,
        'promo_code' => 'STANDARD30',
        'locale' => 'en',
    ])->assertSessionHasErrors('promo_code');
});

test('owner can apply promo on pending billing', function () {
    ['organization' => $organization, 'owner' => $owner] = makePromoOrg();

    $this->actingAs($owner)
        ->post(route('settings.billing.promo'), [
            'promo_code' => 'TRIAL14',
        ])
        ->assertRedirect(route('settings.billing.edit'));

    $organization->refresh();

    expect($organization->isOnTrial())->toBeTrue()
        ->and($organization->promo_code)->toBe('TRIAL14')
        ->and($organization->canAddProduct())->toBeTrue();
});

test('trial notice is shared while trial is active', function () {
    ['organization' => $organization, 'owner' => $owner] = makePromoOrg(
        status: BillingStatus::Active->value,
    );

    $promoCode = 'TRIAL14';

    $organization->forceFill([
        'trial_ends_at' => now()->addDays(10),
        'promo_code' => $promoCode,
        'billing_activated_at' => null,
        'payment_method' => null,
    ])->save();

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->where('trial_notice.promo_code', $promoCode)
            ->where('billing_notice', null)
            ->has('trial_notice.days_remaining'));
});

test('expired unpaid trial becomes pending and locks products', function () {
    ['organization' => $organization] = makePromoOrg(
        status: BillingStatus::Active->value,
    );

    $organization->forceFill([
        'trial_ends_at' => now()->subDay(),
        'promo_code' => 'TRIAL14',
        'billing_activated_at' => null,
        'payment_method' => null,
    ])->save();

    expect($organization->fresh()->syncExpiredTrial())->toBeTrue();

    $organization->refresh();

    expect($organization->billing_status)->toBe(BillingStatus::PendingPayment)
        ->and($organization->isOnTrial())->toBeFalse()
        ->and($organization->canAddProduct())->toBeFalse()
        ->and($organization->exists)->toBeTrue();
});

test('billing expire-trials command converts expired trials', function () {
    ['organization' => $organization] = makePromoOrg(
        status: BillingStatus::Active->value,
    );

    $organization->forceFill([
        'trial_ends_at' => now()->subHour(),
        'billing_activated_at' => null,
        'payment_method' => null,
    ])->save();

    Artisan::call('billing:expire-trials');

    expect($organization->fresh()->billing_status)->toBe(BillingStatus::PendingPayment);
});

test('billing expire-trials is scheduled hourly', function () {
    $match = collect(Schedule::events())->first(
        fn($event) => str_contains((string) ($event->command ?? ''), 'billing:expire-trials'),
    );

    expect($match)->not->toBeNull()
        ->and($match->expression)->toBe('0 * * * *');
});

test('confirmed payment clears trial_ends_at', function () {
    ['organization' => $organization] = makePromoOrg(
        status: BillingStatus::Active->value,
    );

    $organization->forceFill([
        'trial_ends_at' => now()->addDays(5),
        'promo_code' => 'TRIAL14',
        'billing_activated_at' => null,
    ])->save();

    $organization->forceFill([
        'billing_activated_at' => now(),
        'payment_method' => PaymentMethod::Bank->value,
    ])->save();

    expect($organization->fresh()->syncExpiredTrial())->toBeFalse();
    expect($organization->fresh()->trial_ends_at)->toBeNull()
        ->and($organization->fresh()->isOnTrial())->toBeFalse()
        ->and($organization->fresh()->canAddProduct())->toBeTrue();
});
