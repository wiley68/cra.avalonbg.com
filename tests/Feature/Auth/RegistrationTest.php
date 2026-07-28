<?php

use App\Enums\BillingInterval;
use App\Enums\BillingStatus;
use App\Enums\RoleSlug;
use App\Enums\SubscriptionPlan;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
    $this->seed(RolePermissionSeeder::class);
});

function registrationPassword(): string
{
    return 'Password1!x';
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function registrationPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Test User',
        'email' => 'owner@example.com',
        'password' => registrationPassword(),
        'password_confirmation' => registrationPassword(),
        'organization_name' => 'Acme Compliance',
        'subscription_plan' => SubscriptionPlan::Free->value,
        'locale' => 'en',
    ], $overrides);
}

test('registration screen can be rendered', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('auth/Register')
            ->has('subscriptionPlans', 4));
});

test('new users can register on free plan with active billing', function () {
    $response = $this->post(route('register.store'), registrationPayload());

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::query()->where('email', 'owner@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->must_change_password)->toBeFalse()
        ->and($user->is_platform_admin)->toBeFalse();

    $organization = $user->organizations()->first();
    expect($organization)->not->toBeNull()
        ->and($organization->subscription_plan)->toBe(SubscriptionPlan::Free->value)
        ->and($organization->billing_status)->toBe(BillingStatus::Active)
        ->and($organization->canAddProduct())->toBeTrue()
        ->and($user->roleIn($organization)?->slug)->toBe(RoleSlug::OrganizationOwner->value)
        ->and($organization->controls()->count())->toBeGreaterThan(0);
});

test('paid plan registration creates pending payment organization', function () {
    $this->post(route('register.store'), registrationPayload([
        'email' => 'paid@example.com',
        'organization_name' => 'Paid Co',
        'subscription_plan' => SubscriptionPlan::Small->value,
        'billing_interval' => BillingInterval::Year->value,
    ]))->assertRedirect();

    $this->assertAuthenticated();

    $organization = Organization::query()->where('slug', 'paid-co')->first();
    expect($organization)->not->toBeNull()
        ->and($organization->subscription_plan)->toBe(SubscriptionPlan::Small->value)
        ->and($organization->billing_status)->toBe(BillingStatus::PendingPayment)
        ->and($organization->billing_interval)->toBe(BillingInterval::Year)
        ->and($organization->canAddProduct())->toBeFalse();
});

test('registration requires organization name and plan', function () {
    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'missing-org@example.com',
        'password' => registrationPassword(),
        'password_confirmation' => registrationPassword(),
    ])->assertSessionHasErrors(['organization_name', 'subscription_plan']);
});

test('registration rejects unknown subscription plan', function () {
    $this->post(route('register.store'), registrationPayload([
        'email' => 'badplan@example.com',
        'subscription_plan' => 'solo',
    ]))->assertSessionHasErrors('subscription_plan');
});

test('login screen shows register link when registration is enabled', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('auth/Login')
            ->where('canRegister', true));
});
