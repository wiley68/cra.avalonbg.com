<?php

use App\Enums\AuditEventType;
use App\Enums\BillingStatus;
use App\Enums\SsoProvider;
use App\Enums\SubscriptionPlan;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationSsoConnection;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User}
 */
function makeSsoOrg(
    string $plan = 'enterprise',
    bool $ssoEnabled = false,
): array {
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'SSO Org',
        'slug' => 'sso-org-' . uniqid(),
        'is_active' => true,
        'subscription_plan' => $plan,
        'billing_status' => BillingStatus::Active->value,
        'billing_activated_at' => now(),
        'sso_enabled' => $ssoEnabled,
        'billing_email' => 'billing@sso.test',
    ]);

    $owner = User::factory()->create([
        'email' => 'owner@company.com',
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

function fakeOidcDiscovery(string $issuer = 'https://idp.example.com'): void
{
    $issuer = rtrim($issuer, '/');

    Http::fake([
        $issuer . '/.well-known/openid-configuration' => Http::response([
            'issuer' => $issuer,
            'authorization_endpoint' => $issuer . '/authorize',
            'token_endpoint' => $issuer . '/token',
            'userinfo_endpoint' => $issuer . '/userinfo',
        ], 200),
        $issuer . '/token' => Http::response([
            'access_token' => 'access-token',
            'id_token' => 'header.' . rtrim(strtr(base64_encode(json_encode([
                'email' => 'owner@company.com',
                'nonce' => 'test-nonce',
            ])), '+/', '-_'), '=') . '.sig',
            'token_type' => 'Bearer',
        ], 200),
        $issuer . '/userinfo' => Http::response([
            'email' => 'owner@company.com',
        ], 200),
    ]);
}

test('enterprise owner can save sso connection without logging secrets', function () {
    ['organization' => $organization, 'owner' => $owner] = makeSsoOrg('enterprise');

    $this->actingAs($owner)
        ->put(route('settings.sso.update'), [
            'provider' => SsoProvider::Entra->value,
            'issuer' => 'https://idp.example.com',
            'client_id' => 'client-123',
            'client_secret' => 'super-secret-value',
            'allowed_email_domains' => 'company.com',
            'is_enabled' => true,
        ])
        ->assertRedirect(route('settings.sso.edit'));

    $connection = OrganizationSsoConnection::query()
        ->where('organization_id', $organization->id)
        ->first();

    expect($connection)->not->toBeNull()
        ->and($connection->client_id)->toBe('client-123')
        ->and($connection->client_secret)->toBe('super-secret-value')
        ->and($connection->is_enabled)->toBeTrue()
        ->and($connection->normalizedDomains())->toBe(['company.com']);

    $log = AuditLog::query()
        ->where('event_type', AuditEventType::SsoConnectionCreated->value)
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull();
    $payload = json_encode($log->details ?? []);
    expect($payload)->not->toContain('super-secret-value');
});

test('small plan cannot configure sso', function () {
    ['owner' => $owner] = makeSsoOrg('small');

    $this->actingAs($owner)
        ->put(route('settings.sso.update'), [
            'provider' => SsoProvider::Generic->value,
            'issuer' => 'https://idp.example.com',
            'client_id' => 'client-123',
            'client_secret' => 'secret',
            'allowed_email_domains' => 'company.com',
            'is_enabled' => true,
        ])
        ->assertSessionHasErrors('sso');
});

test('standard plan can configure sso when flag enabled', function () {
    ['organization' => $organization, 'owner' => $owner] = makeSsoOrg('standard', false);

    $this->actingAs($owner)
        ->put(route('settings.sso.update'), [
            'provider' => SsoProvider::Generic->value,
            'issuer' => 'https://idp.example.com',
            'client_id' => 'client-123',
            'client_secret' => 'secret',
            'allowed_email_domains' => 'company.com',
            'is_enabled' => true,
            'sso_enabled' => true,
        ])
        ->assertRedirect(route('settings.sso.edit'));

    expect($organization->fresh()->sso_enabled)->toBeTrue()
        ->and($organization->fresh()->ssoConnection)->not->toBeNull();
});

test('sso redirect starts oidc authorize for matching domain', function () {
    ['organization' => $organization] = makeSsoOrg('enterprise');
    fakeOidcDiscovery();

    OrganizationSsoConnection::query()->create([
        'organization_id' => $organization->id,
        'provider' => SsoProvider::Generic->value,
        'issuer' => 'https://idp.example.com',
        'client_id' => 'client-123',
        'client_secret' => 'secret',
        'allowed_email_domains' => ['company.com'],
        'is_enabled' => true,
    ]);

    $response = $this->post(route('auth.sso.redirect'), [
        'email_or_slug' => 'anyone@company.com',
    ]);

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toStartWith('https://idp.example.com/authorize?');
    expect(session('sso'))->toHaveKeys(['state', 'nonce', 'organization_id']);
});

test('sso callback logs in existing org user', function () {
    ['organization' => $organization, 'owner' => $owner] = makeSsoOrg('enterprise');
    fakeOidcDiscovery();

    OrganizationSsoConnection::query()->create([
        'organization_id' => $organization->id,
        'provider' => SsoProvider::Generic->value,
        'issuer' => 'https://idp.example.com',
        'client_id' => 'client-123',
        'client_secret' => 'secret',
        'allowed_email_domains' => ['company.com'],
        'is_enabled' => true,
    ]);

    $this->withSession([
        'sso' => [
            'state' => 'test-state',
            'nonce' => 'test-nonce',
            'organization_id' => $organization->id,
        ],
    ])->get(route('auth.sso.callback', [
                    'state' => 'test-state',
                    'code' => 'auth-code',
                ]))->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($owner);

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::SsoLoginSuccess->value)
        ->exists())->toBeTrue();
});

test('sso callback rejects email outside allowed domains', function () {
    ['organization' => $organization] = makeSsoOrg('enterprise');

    $issuer = 'https://idp.example.com';
    Http::fake([
        $issuer . '/.well-known/openid-configuration' => Http::response([
            'issuer' => $issuer,
            'authorization_endpoint' => $issuer . '/authorize',
            'token_endpoint' => $issuer . '/token',
            'userinfo_endpoint' => $issuer . '/userinfo',
        ], 200),
        $issuer . '/token' => Http::response([
            'access_token' => 'access-token',
            'id_token' => 'x.' . rtrim(strtr(base64_encode(json_encode([
                'email' => 'outsider@evil.com',
                'nonce' => 'test-nonce',
            ])), '+/', '-_'), '=') . '.y',
            'token_type' => 'Bearer',
        ], 200),
        $issuer . '/userinfo' => Http::response([
            'email' => 'outsider@evil.com',
        ], 200),
    ]);

    OrganizationSsoConnection::query()->create([
        'organization_id' => $organization->id,
        'provider' => SsoProvider::Generic->value,
        'issuer' => $issuer,
        'client_id' => 'client-123',
        'client_secret' => 'secret',
        'allowed_email_domains' => ['company.com'],
        'is_enabled' => true,
    ]);

    $this->withSession([
        'sso' => [
            'state' => 'test-state',
            'nonce' => 'test-nonce',
            'organization_id' => $organization->id,
        ],
    ])->get(route('auth.sso.callback', [
                    'state' => 'test-state',
                    'code' => 'auth-code',
                ]))->assertRedirect(route('login'))
        ->assertSessionHasErrors('email_or_slug');

    $this->assertGuest();
});

test('sso callback rejects unknown email even with allowed domain', function () {
    ['organization' => $organization] = makeSsoOrg('enterprise');

    $issuer = 'https://idp.example.com';
    Http::fake([
        $issuer . '/.well-known/openid-configuration' => Http::response([
            'issuer' => $issuer,
            'authorization_endpoint' => $issuer . '/authorize',
            'token_endpoint' => $issuer . '/token',
            'userinfo_endpoint' => $issuer . '/userinfo',
        ], 200),
        $issuer . '/token' => Http::response([
            'access_token' => 'access-token',
            'token_type' => 'Bearer',
        ], 200),
        $issuer . '/userinfo' => Http::response([
            'email' => 'stranger@company.com',
        ], 200),
    ]);

    OrganizationSsoConnection::query()->create([
        'organization_id' => $organization->id,
        'provider' => SsoProvider::Generic->value,
        'issuer' => $issuer,
        'client_id' => 'client-123',
        'client_secret' => 'secret',
        'allowed_email_domains' => ['company.com'],
        'is_enabled' => true,
    ]);

    $this->withSession([
        'sso' => [
            'state' => 'test-state',
            'nonce' => 'test-nonce',
            'organization_id' => $organization->id,
        ],
    ])->get(route('auth.sso.callback', [
                    'state' => 'test-state',
                    'code' => 'auth-code',
                ]))->assertRedirect(route('login'))
        ->assertSessionHasErrors('email_or_slug');

    $this->assertGuest();
});
