<?php

use App\Enums\AuditEventType;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{0: Organization, 1: User, 2: User}
 */
function makeOrgOwnerAndMemberWithTwoFactor(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => '2FA Org',
        'slug' => '2fa-org',
        'is_active' => true,
    ]);

    $owner = User::factory()->withTwoFactor()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
    ]);

    $member = User::factory()->withTwoFactor()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
    ]);

    $ownerRole = Role::query()->where('slug', 'organization_owner')->firstOrFail();
    $developerRole = Role::query()->where('slug', 'developer')->firstOrFail();

    $organization->users()->attach($owner->id, [
        'role_id' => $ownerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $organization->users()->attach($member->id, [
        'role_id' => $developerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [$organization, $owner, $member];
}

test('organization owner can reset member two factor authentication', function () {
    [$organization, $owner, $member] = makeOrgOwnerAndMemberWithTwoFactor();

    expect($member->hasEnabledTwoFactorAuthentication())->toBeTrue();

    $this->actingAs($owner)
        ->post(route('users.reset-two-factor', $member))
        ->assertRedirect(route('users.edit', $member));

    $member->refresh();

    expect($member->two_factor_secret)->toBeNull()
        ->and($member->two_factor_recovery_codes)->toBeNull()
        ->and($member->two_factor_confirmed_at)->toBeNull()
        ->and($member->hasEnabledTwoFactorAuthentication())->toBeFalse();

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::TwoFactorReset->value)
        ->where('user_id', $owner->id)
        ->where('organization_id', $organization->id)
        ->exists())->toBeTrue();
});

test('member is forced to two factor setup after admin reset', function () {
    [$organization, $owner, $member] = makeOrgOwnerAndMemberWithTwoFactor();
    unset($organization);

    $this->actingAs($owner)
        ->post(route('users.reset-two-factor', $member))
        ->assertRedirect(route('users.edit', $member));

    $this->actingAs($member->fresh())
        ->get(route('dashboard'))
        ->assertRedirect(route('auth.two-factor.setup'));
});

test('developer cannot reset another users two factor', function () {
    [$organization, $owner, $member] = makeOrgOwnerAndMemberWithTwoFactor();
    unset($owner);

    $other = User::factory()->withTwoFactor()->create([
        'email_verified_at' => now(),
        'must_change_password' => false,
    ]);

    $developerRole = Role::query()->where('slug', 'developer')->firstOrFail();
    $organization->users()->attach($other->id, [
        'role_id' => $developerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($member)
        ->post(route('users.reset-two-factor', $other))
        ->assertForbidden();

    expect($other->fresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();
});

test('platform admin can reset organization user two factor', function () {
    test()->seed([RolePermissionSeeder::class]);

    $admin = User::factory()->withTwoFactor()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => true,
        'must_change_password' => false,
    ]);

    [$organization, $owner, $member] = makeOrgOwnerAndMemberWithTwoFactor();
    unset($owner);

    $this->actingAs($admin)
        ->post(route('admin.organizations.users.reset-two-factor', [
            $organization,
            $member,
        ]))
        ->assertRedirect(route('admin.organizations.users.edit', [
            $organization,
            $member,
        ]));

    expect($member->fresh()->hasEnabledTwoFactorAuthentication())->toBeFalse();

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::TwoFactorReset->value)
        ->where('user_id', $admin->id)
        ->where('organization_id', $organization->id)
        ->exists())->toBeTrue();
});

test('resetting two factor when already disabled does not write audit log', function () {
    [$organization, $owner, $memberWithTwoFactor] = makeOrgOwnerAndMemberWithTwoFactor();
    unset($memberWithTwoFactor);

    $member = User::factory()->create([
        'email_verified_at' => now(),
        'must_change_password' => false,
        'two_factor_secret' => null,
        'two_factor_recovery_codes' => null,
        'two_factor_confirmed_at' => null,
    ]);

    $developerRole = Role::query()->where('slug', 'developer')->firstOrFail();
    $organization->users()->attach($member->id, [
        'role_id' => $developerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($owner)
        ->post(route('users.reset-two-factor', $member))
        ->assertRedirect(route('users.edit', $member));

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::TwoFactorReset->value)
        ->where('organization_id', $organization->id)
        ->doesntExist())->toBeTrue();
});

test('user edit page exposes two factor enabled flag', function () {
    [$organization, $owner, $member] = makeOrgOwnerAndMemberWithTwoFactor();
    unset($organization);

    $this->actingAs($owner)
        ->get(route('users.edit', $member))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('users/Edit')
            ->where('user.two_factor_enabled', true));
});
