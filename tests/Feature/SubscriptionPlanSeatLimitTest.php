<?php

use App\Enums\BillingStatus;
use App\Enums\RoleSlug;
use App\Enums\SubscriptionPlan;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{0: Organization, 1: User, 2: int}
 */
function makeOrgWithSeats(string $plan, int $extraSeats = 0): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Seat Org',
        'slug' => 'seat-org-' . uniqid(),
        'is_active' => true,
        'subscription_plan' => $plan,
        'billing_status' => BillingStatus::Active->value,
    ]);

    $ownerRole = Role::query()->where('slug', RoleSlug::OrganizationOwner->value)->firstOrFail();
    $memberRole = Role::query()->where('slug', RoleSlug::Developer->value)->firstOrFail();

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $organization->users()->attach($owner->id, [
        'role_id' => $ownerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    for ($i = 0; $i < $extraSeats; $i++) {
        $member = User::factory()->create([
            'email_verified_at' => now(),
            'is_platform_admin' => false,
            'must_change_password' => false,
            'two_factor_confirmed_at' => now(),
        ]);

        $organization->users()->attach($member->id, [
            'role_id' => $memberRole->id,
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    return [$organization, $owner, (int) $memberRole->id];
}

function createUserPayload(int $roleId, string $email): array
{
    return [
        'name' => 'New Seat User',
        'email' => $email,
        'password' => 'Password1!x',
        'password_confirmation' => 'Password1!x',
        'role_id' => $roleId,
        'must_change_password' => true,
    ];
}

test('subscription plan catalog includes seat limits', function () {
    expect(SubscriptionPlan::Free->maxSeats())->toBe(2)
        ->and(SubscriptionPlan::Small->maxSeats())->toBe(5)
        ->and(SubscriptionPlan::Standard->maxSeats())->toBe(15)
        ->and(SubscriptionPlan::Enterprise->maxSeats())->toBeNull();
});

test('free plan blocks creating a third user seat', function () {
    [$organization, $owner, $roleId] = makeOrgWithSeats(SubscriptionPlan::Free->value, extraSeats: 1);

    expect($organization->fresh()->canAddUser())->toBeFalse();

    $this->actingAs($owner)
        ->post(route('users.store'), createUserPayload($roleId, 'third@seat.test'))
        ->assertSessionHasErrors('email');

    expect($organization->fresh()->seatsCount())->toBe(2);
});

test('free plan allows a second user seat', function () {
    [$organization, $owner, $roleId] = makeOrgWithSeats(SubscriptionPlan::Free->value);

    $this->actingAs($owner)
        ->post(route('users.store'), createUserPayload($roleId, 'second@seat.test'))
        ->assertRedirect(route('users.index'));

    expect($organization->fresh()->seatsCount())->toBe(2);
});

test('create user page redirects when seat limit is reached', function () {
    [$organization, $owner] = makeOrgWithSeats(SubscriptionPlan::Free->value, extraSeats: 1);

    $this->actingAs($owner)
        ->get(route('users.create'))
        ->assertRedirect(route('users.index'));
});

test('users index includes seat quota payload', function () {
    [$organization, $owner] = makeOrgWithSeats(SubscriptionPlan::Free->value, extraSeats: 1);

    $this->actingAs($owner)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('users/Index')
            ->where('seatQuota.plan', 'free')
            ->where('seatQuota.max_seats', 2)
            ->where('seatQuota.used', 2)
            ->where('seatQuota.can_create', false));
});

test('billing settings includes usage dashboard payload', function () {
    [$organization, $owner] = makeOrgWithSeats(SubscriptionPlan::Small->value, extraSeats: 1);

    $this->actingAs($owner)
        ->get(route('settings.billing.edit'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('settings/Billing')
            ->where('usage.plan', 'small')
            ->where('usage.seats.max', 5)
            ->where('usage.seats.used', 2)
            ->where('usage.products.max', 3)
            ->where('usage.products.used', 0));
});

test('pending payment allows first seat but blocks additional seats', function () {
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Pending Seats',
        'slug' => 'pending-seats-' . uniqid(),
        'is_active' => true,
        'subscription_plan' => SubscriptionPlan::Small->value,
        'billing_status' => BillingStatus::PendingPayment->value,
    ]);

    expect($organization->canAddUser())->toBeTrue();

    $ownerRole = Role::query()->where('slug', RoleSlug::OrganizationOwner->value)->firstOrFail();
    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $organization->users()->attach($owner->id, [
        'role_id' => $ownerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($organization->fresh()->canAddUser())->toBeFalse();
});
