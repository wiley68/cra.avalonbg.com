<?php

use App\Enums\AuditEventType;
use App\Enums\IntegrationAuthType;
use App\Enums\IntegrationCategory;
use App\Enums\IntegrationConnectionStatus;
use App\Enums\IntegrationProvider;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User}
 */
function makeJiraIntegrationsFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Jira Integrations Org',
        'slug' => 'jira-integrations-org-' . uniqid(),
        'is_active' => true,
        'locale' => 'en',
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

test('owner can view integrations settings with empty integrations list', function () {
    ['owner' => $owner] = makeJiraIntegrationsFixture();

    $this->actingAs($owner)
        ->get(route('settings.integrations.edit'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('settings/Integrations')
            ->where('canManage', true)
            ->has('connections', 0)
            ->has('integrations', 0));
});

test('owner can connect jira with valid credentials and audit is recorded', function () {
    ['organization' => $organization, 'owner' => $owner] = makeJiraIntegrationsFixture();

    Http::fake([
        'https://acme.atlassian.net/rest/api/3/myself' => Http::response([
            'accountId' => 'abc',
            'displayName' => 'Owner',
        ], 200),
    ]);

    $this->actingAs($owner)
        ->post(route('settings.integrations.jira.store'), [
            'base_url' => 'https://acme.atlassian.net',
            'email' => 'owner@acme.example',
            'api_token' => 'jira_valid_api_token',
            'label' => 'Work Jira',
        ])
        ->assertRedirect();

    $integration = OrganizationIntegration::query()->first();

    expect($integration)->not->toBeNull();
    expect($integration->organization_id)->toBe($organization->id);
    expect($integration->provider)->toBe(IntegrationProvider::Jira);
    expect($integration->category)->toBe(IntegrationCategory::Alm);
    expect($integration->auth_type)->toBe(IntegrationAuthType::ApiToken);
    expect($integration->status)->toBe(IntegrationConnectionStatus::Active);
    expect($integration->label)->toBe('Work Jira');
    expect($integration->credentials['api_token'] ?? null)->toBe('jira_valid_api_token');
    expect($integration->toArray())->not->toHaveKey('credentials');

    expect(AuditLog::query()->where('event_type', AuditEventType::IntegrationConnected)->count())->toBe(1);

    $description = AuditLog::query()
        ->where('event_type', AuditEventType::IntegrationConnected)
        ->value('description');
    expect($description)->not->toContain('jira_valid_api_token');
});

test('invalid jira credentials are rejected', function () {
    ['owner' => $owner] = makeJiraIntegrationsFixture();

    Http::fake([
        'https://acme.atlassian.net/rest/api/3/myself' => Http::response([
            'message' => 'Unauthorized',
        ], 401),
    ]);

    $this->actingAs($owner)
        ->from(route('settings.integrations.edit'))
        ->post(route('settings.integrations.jira.store'), [
            'base_url' => 'https://acme.atlassian.net',
            'email' => 'owner@acme.example',
            'api_token' => 'jira_bad_token',
        ])
        ->assertRedirect(route('settings.integrations.edit'))
        ->assertSessionHasErrors('api_token');

    expect(OrganizationIntegration::query()->count())->toBe(0);
});

test('owner can disconnect jira integration', function () {
    ['organization' => $organization, 'owner' => $owner] = makeJiraIntegrationsFixture();

    $integration = OrganizationIntegration::query()->create([
        'organization_id' => $organization->id,
        'provider' => IntegrationProvider::Jira,
        'category' => IntegrationCategory::Alm,
        'auth_type' => IntegrationAuthType::ApiToken,
        'credentials' => [
            'base_url' => 'https://acme.atlassian.net',
            'email' => 'owner@acme.example',
            'api_token' => 'to_delete',
        ],
        'label' => 'Jira',
        'status' => IntegrationConnectionStatus::Active,
        'last_verified_at' => now(),
    ]);

    $this->actingAs($owner)
        ->delete(route('settings.integrations.providers.destroy', $integration))
        ->assertRedirect();

    expect(OrganizationIntegration::query()->count())->toBe(0);
    expect(AuditLog::query()->where('event_type', AuditEventType::IntegrationDisconnected)->count())->toBe(1);
});

test('updating existing jira connection records update audit', function () {
    ['organization' => $organization, 'owner' => $owner] = makeJiraIntegrationsFixture();

    OrganizationIntegration::query()->create([
        'organization_id' => $organization->id,
        'provider' => IntegrationProvider::Jira,
        'category' => IntegrationCategory::Alm,
        'auth_type' => IntegrationAuthType::ApiToken,
        'credentials' => [
            'base_url' => 'https://acme.atlassian.net',
            'email' => 'old@acme.example',
            'api_token' => 'old_token',
        ],
        'label' => 'Jira',
        'status' => IntegrationConnectionStatus::Active,
        'last_verified_at' => now()->subDay(),
    ]);

    Http::fake([
        'https://acme.atlassian.net/rest/api/3/myself' => Http::response(['accountId' => 'abc'], 200),
    ]);

    $this->actingAs($owner)
        ->post(route('settings.integrations.jira.store'), [
            'base_url' => 'https://acme.atlassian.net',
            'email' => 'owner@acme.example',
            'api_token' => 'new_jira_token_value',
            'label' => 'Rotated Jira',
        ])
        ->assertRedirect();

    expect(OrganizationIntegration::query()->count())->toBe(1);
    expect(OrganizationIntegration::query()->first()->credentials['api_token'] ?? null)
        ->toBe('new_jira_token_value');
    expect(OrganizationIntegration::query()->first()->label)->toBe('Rotated Jira');
    expect(AuditLog::query()->where('event_type', AuditEventType::IntegrationUpdated)->count())->toBe(1);
});

test('jira connect verification uses basic auth get myself', function () {
    ['owner' => $owner] = makeJiraIntegrationsFixture();

    Http::fake([
        'https://acme.atlassian.net/rest/api/3/myself' => Http::response(['accountId' => 'abc'], 200),
    ]);

    $this->actingAs($owner)
        ->post(route('settings.integrations.jira.store'), [
            'base_url' => 'acme.atlassian.net',
            'email' => 'owner@acme.example',
            'api_token' => 'jira_verify_token',
        ])
        ->assertRedirect();

    Http::assertSentCount(1);
    Http::assertSent(function ($request) {
        return $request->method() === 'GET'
            && $request->url() === 'https://acme.atlassian.net/rest/api/3/myself'
            && $request->hasHeader('Authorization');
    });
});

test('read-only user cannot connect jira', function () {
    ['organization' => $organization] = makeJiraIntegrationsFixture();

    $viewer = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);
    $role = Role::query()->where('slug', 'read_only')->firstOrFail();
    $organization->users()->attach($viewer->id, [
        'role_id' => $role->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Http::fake();

    $this->actingAs($viewer)
        ->post(route('settings.integrations.jira.store'), [
            'base_url' => 'https://acme.atlassian.net',
            'email' => 'viewer@acme.example',
            'api_token' => 'should_not_work',
        ])
        ->assertForbidden();

    expect(OrganizationIntegration::query()->count())->toBe(0);
    Http::assertNothingSent();
});

test('edit page exposes jira integration without api token', function () {
    ['organization' => $organization, 'owner' => $owner] = makeJiraIntegrationsFixture();

    OrganizationIntegration::query()->create([
        'organization_id' => $organization->id,
        'provider' => IntegrationProvider::Jira,
        'category' => IntegrationCategory::Alm,
        'auth_type' => IntegrationAuthType::ApiToken,
        'credentials' => [
            'base_url' => 'https://acme.atlassian.net',
            'email' => 'owner@acme.example',
            'api_token' => 'secret_token_value',
        ],
        'label' => 'Jira',
        'status' => IntegrationConnectionStatus::Active,
        'last_verified_at' => now(),
    ]);

    $expectedProvider = 'jira';
    $expectedBaseUrl = 'https://acme.atlassian.net';
    $expectedEmail = 'owner@acme.example';

    $this->actingAs($owner)
        ->get(route('settings.integrations.edit'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('settings/Integrations')
            ->has('integrations', 1)
            ->where('integrations.0.provider', $expectedProvider)
            ->where('integrations.0.base_url', $expectedBaseUrl)
            ->where('integrations.0.email', $expectedEmail)
            ->missing('integrations.0.credentials')
            ->missing('integrations.0.api_token'));
});
