<?php

use App\Enums\RoleSlug;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\ControlService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User}
 */
function makeOnboardingOrgWithOwner(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Onboard Co',
        'slug' => 'onboard-co',
        'is_active' => true,
    ]);

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'two_factor_confirmed_at' => now(),
        'must_change_password' => false,
    ]);

    $ownerRole = Role::query()->where('slug', RoleSlug::OrganizationOwner->value)->firstOrFail();
    $organization->users()->attach($owner->id, [
        'role_id' => $ownerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(ControlService::class)->seedStarterCatalogue($organization, refreshExisting: false);

    return compact('organization', 'owner');
}

test('organization dashboard shows post-signup onboarding checklist', function () {
    ['organization' => $organization, 'owner' => $owner] = makeOnboardingOrgWithOwner();

    $dismissHref = route('dashboard.onboarding.dismiss');
    $itemKeys = ['settings', 'users', 'controls', 'policies', 'customers'];

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('Dashboard')
            ->where('dashboard.onboarding.visible', true)
            ->where('dashboard.onboarding.can_dismiss', true)
            ->where('dashboard.onboarding.dismiss_href', $dismissHref)
            ->has('dashboard.onboarding.items', 5)
            ->where('dashboard.onboarding.items.0.key', $itemKeys[0])
            ->where('dashboard.onboarding.items.0.done', false)
            ->where('dashboard.onboarding.items.0.optional', false)
            ->where('dashboard.onboarding.items.1.key', $itemKeys[1])
            ->where('dashboard.onboarding.items.1.done', false)
            ->where('dashboard.onboarding.items.2.key', $itemKeys[2])
            ->where('dashboard.onboarding.items.2.done', true)
            ->where('dashboard.onboarding.items.3.key', $itemKeys[3])
            ->where('dashboard.onboarding.items.3.done', false)
            ->where('dashboard.onboarding.items.4.key', $itemKeys[4])
            ->where('dashboard.onboarding.items.4.optional', true)
            ->where('dashboard.onboarding.items.4.done', false));
});

test('owner can dismiss onboarding checklist', function () {
    ['organization' => $organization, 'owner' => $owner] = makeOnboardingOrgWithOwner();

    $this->actingAs($owner)
        ->post(route('dashboard.onboarding.dismiss'))
        ->assertRedirect();

    expect($organization->fresh()->onboarding_checklist_dismissed_at)->not->toBeNull();

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('Dashboard')
            ->where('dashboard.onboarding.visible', false)
            ->where('dashboard.onboarding.items', []));
});

test('member without org manage cannot dismiss onboarding checklist', function () {
    ['organization' => $organization, 'owner' => $owner] = makeOnboardingOrgWithOwner();

    $member = User::factory()->create([
        'email_verified_at' => now(),
        'two_factor_confirmed_at' => now(),
        'must_change_password' => false,
    ]);

    $viewerRole = Role::query()->where('slug', RoleSlug::ReadOnly ->value)->firstOrFail();
    $organization->users()->attach($member->id, [
        'role_id' => $viewerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($member)
        ->post(route('dashboard.onboarding.dismiss'))
        ->assertForbidden();

    expect($organization->fresh()->onboarding_checklist_dismissed_at)->toBeNull();
});
