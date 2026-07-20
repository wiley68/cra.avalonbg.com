<?php

use App\Enums\AuditEventType;
use App\Enums\VcsAuthType;
use App\Enums\VcsConnectionStatus;
use App\Enums\VcsProvider;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationVcsConnection;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User}
 */
function makeIntegrationsFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Integrations Org',
        'slug' => 'integrations-org',
        'is_active' => true,
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

test('owner can view integrations settings', function () {
    ['owner' => $owner] = makeIntegrationsFixture();

    $this->actingAs($owner)
        ->get(route('settings.integrations.edit'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('settings/Integrations')
            ->where('canManage', true)
            ->has('connections', 0));
});

test('owner can connect github with valid pat and audit is recorded', function () {
    ['organization' => $organization, 'owner' => $owner] = makeIntegrationsFixture();

    Http::fake([
        'api.github.com/user' => Http::response(['login' => 'octocat'], 200),
    ]);

    $this->actingAs($owner)
        ->post(route('settings.integrations.github.store'), [
            'token' => 'ghp_valid_token_value',
            'label' => 'Work GitHub',
        ])
        ->assertRedirect();

    $connection = OrganizationVcsConnection::query()->first();

    expect($connection)->not->toBeNull()
        ->and($connection->organization_id)->toBe($organization->id)
        ->and($connection->provider)->toBe(VcsProvider::Github)
        ->and($connection->auth_type)->toBe(VcsAuthType::Pat)
        ->and($connection->status)->toBe(VcsConnectionStatus::Active)
        ->and($connection->label)->toBe('Work GitHub')
        ->and($connection->token)->toBe('ghp_valid_token_value')
        ->and($connection->toArray())->not->toHaveKey('token');

    expect(AuditLog::query()->where('event_type', AuditEventType::VcsConnectionCreated)->count())->toBe(1);

    $description = AuditLog::query()->where('event_type', AuditEventType::VcsConnectionCreated)->value('description');
    expect($description)->not->toContain('ghp_valid_token_value');
});

test('invalid github token is rejected', function () {
    ['owner' => $owner] = makeIntegrationsFixture();

    Http::fake([
        'api.github.com/user' => Http::response(['message' => 'Bad credentials'], 401),
    ]);

    $this->actingAs($owner)
        ->from(route('settings.integrations.edit'))
        ->post(route('settings.integrations.github.store'), [
            'token' => 'ghp_bad_token',
        ])
        ->assertRedirect(route('settings.integrations.edit'))
        ->assertSessionHasErrors('token');

    expect(OrganizationVcsConnection::query()->count())->toBe(0);
});

test('owner can disconnect github connection', function () {
    ['organization' => $organization, 'owner' => $owner] = makeIntegrationsFixture();

    $connection = OrganizationVcsConnection::query()->create([
        'organization_id' => $organization->id,
        'provider' => VcsProvider::Github,
        'auth_type' => VcsAuthType::Pat,
        'token' => 'ghp_to_delete',
        'label' => 'GitHub',
        'status' => VcsConnectionStatus::Active,
        'last_verified_at' => now(),
    ]);

    $this->actingAs($owner)
        ->delete(route('settings.integrations.destroy', $connection))
        ->assertRedirect();

    expect(OrganizationVcsConnection::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('event_type', AuditEventType::VcsConnectionDeleted)->count())->toBe(1);
});

test('updating existing github connection records update audit', function () {
    ['organization' => $organization, 'owner' => $owner] = makeIntegrationsFixture();

    OrganizationVcsConnection::query()->create([
        'organization_id' => $organization->id,
        'provider' => VcsProvider::Github,
        'auth_type' => VcsAuthType::Pat,
        'token' => 'ghp_old_token',
        'label' => 'GitHub',
        'status' => VcsConnectionStatus::Active,
        'last_verified_at' => now()->subDay(),
    ]);

    Http::fake([
        'api.github.com/user' => Http::response(['login' => 'octocat'], 200),
    ]);

    $this->actingAs($owner)
        ->post(route('settings.integrations.github.store'), [
            'token' => 'ghp_new_token_value',
            'label' => 'Rotated',
        ])
        ->assertRedirect();

    expect(OrganizationVcsConnection::query()->count())->toBe(1)
        ->and(OrganizationVcsConnection::query()->first()->token)->toBe('ghp_new_token_value')
        ->and(OrganizationVcsConnection::query()->first()->label)->toBe('Rotated')
        ->and(AuditLog::query()->where('event_type', AuditEventType::VcsConnectionUpdated)->count())->toBe(1);
});
