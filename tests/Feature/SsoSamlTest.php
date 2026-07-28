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
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User}
 */
function makeSamlOrg(string $plan = 'enterprise'): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'SAML Org',
        'slug' => 'saml-org-' . uniqid(),
        'is_active' => true,
        'subscription_plan' => $plan,
        'billing_status' => BillingStatus::Active->value,
        'billing_activated_at' => now(),
        'billing_email' => 'billing@saml.test',
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

    return compact('organization', 'owner');
}

function sampleIdpCertificate(): string
{
    // Self-signed cert for fixture Responses (signature verification disabled in tests).
    return <<<'CERT'
-----BEGIN CERTIFICATE-----
MIIBkTCB+wIJAKHBfJf0exampleDUMMYCERTIFICATENOTREAL000000000000000
00000000000000000000000000000000000000000000000000000000000000000
00000000000000000000000000000000000000000000000000000000000000000
00000000000000000000000000000000000000000000000000000000000000000
-----END CERTIFICATE-----
CERT;
}

function unsignedSamlResponse(
    string $idpEntityId,
    string $email,
    string $acsUrl,
    string $spEntityId,
    ?string $inResponseTo = null,
): string {
    $responseId = '_' . uniqid('resp', true);
    $assertionId = '_' . uniqid('assert', true);
    $instant = gmdate('Y-m-d\TH:i:s\Z');
    $notOnOrAfter = gmdate('Y-m-d\TH:i:s\Z', time() + 300);
    $inResponseToAttr = $inResponseTo
        ? ' InResponseTo="' . htmlspecialchars($inResponseTo, ENT_XML1) . '"'
        : '';

    $xml = <<<XML
<samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
    xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
    ID="{$responseId}"
    Version="2.0"
    IssueInstant="{$instant}"
    Destination="{$acsUrl}"{$inResponseToAttr}>
  <saml:Issuer>{$idpEntityId}</saml:Issuer>
  <samlp:Status>
    <samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:Success"/>
  </samlp:Status>
  <saml:Assertion ID="{$assertionId}" Version="2.0" IssueInstant="{$instant}">
    <saml:Issuer>{$idpEntityId}</saml:Issuer>
    <saml:Subject>
      <saml:NameID Format="urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress">{$email}</saml:NameID>
    </saml:Subject>
    <saml:Conditions NotOnOrAfter="{$notOnOrAfter}">
      <saml:AudienceRestriction>
        <saml:Audience>{$spEntityId}</saml:Audience>
      </saml:AudienceRestriction>
    </saml:Conditions>
    <saml:AttributeStatement>
      <saml:Attribute Name="email">
        <saml:AttributeValue>{$email}</saml:AttributeValue>
      </saml:Attribute>
    </saml:AttributeStatement>
  </saml:Assertion>
</samlp:Response>
XML;

    return base64_encode($xml);
}

beforeEach(function () {
    Config::set('sso.saml.require_signature', false);
    Config::set('app.url', 'https://cra.test');
});

test('enterprise owner can save saml connection without logging certificate', function () {
    ['organization' => $organization, 'owner' => $owner] = makeSamlOrg();

    $this->actingAs($owner)
        ->put(route('settings.sso.update'), [
            'provider' => SsoProvider::Saml->value,
            'issuer' => 'https://idp.example.com/metadata',
            'idp_sso_url' => 'https://idp.example.com/sso',
            'idp_x509_cert' => sampleIdpCertificate(),
            'allowed_email_domains' => 'company.com',
            'is_enabled' => true,
        ])
        ->assertRedirect(route('settings.sso.edit'));

    $connection = OrganizationSsoConnection::query()
        ->where('organization_id', $organization->id)
        ->first();

    expect($connection)->not->toBeNull()
        ->and($connection->provider)->toBe(SsoProvider::Saml)
        ->and($connection->idp_sso_url)->toBe('https://idp.example.com/sso')
        ->and($connection->idp_x509_cert)->not->toBeNull()
        ->and($connection->client_id)->toBeNull();

    $audit = AuditLog::query()
        ->where('event_type', AuditEventType::SsoConnectionCreated->value)
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull();
    $payload = json_encode($audit->details ?? []);
    expect($payload)->not->toContain('BEGIN CERTIFICATE')
        ->and($payload)->not->toContain('DUMMYCERTIFICATE');
});

test('small plan cannot configure saml', function () {
    ['owner' => $owner] = makeSamlOrg(SubscriptionPlan::Small->value);

    $this->actingAs($owner)
        ->put(route('settings.sso.update'), [
            'provider' => SsoProvider::Saml->value,
            'issuer' => 'https://idp.example.com/metadata',
            'idp_sso_url' => 'https://idp.example.com/sso',
            'idp_x509_cert' => sampleIdpCertificate(),
            'allowed_email_domains' => 'company.com',
            'is_enabled' => true,
        ])
        ->assertSessionHasErrors('sso');
});

test('saml redirect stores session and sends user to idp', function () {
    ['organization' => $organization, 'owner' => $owner] = makeSamlOrg();

    OrganizationSsoConnection::query()->create([
        'organization_id' => $organization->id,
        'provider' => SsoProvider::Saml->value,
        'issuer' => 'https://idp.example.com/metadata',
        'idp_sso_url' => 'https://idp.example.com/sso',
        'idp_x509_cert' => sampleIdpCertificate(),
        'allowed_email_domains' => ['company.com'],
        'is_enabled' => true,
    ]);

    $response = $this->post(route('auth.sso.redirect'), [
        'email_or_slug' => 'owner@company.com',
    ]);

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toStartWith('https://idp.example.com/sso?');

    $session = session('sso');
    expect($session)->toBeArray()
        ->and($session['protocol'])->toBe('saml')
        ->and($session['organization_id'])->toBe($organization->id)
        ->and($session['request_id'])->not->toBeNull()
        ->and($session['state'])->not->toBeNull();
});

test('saml acs logs in matching org user', function () {
    ['organization' => $organization] = makeSamlOrg();

    OrganizationSsoConnection::query()->create([
        'organization_id' => $organization->id,
        'provider' => SsoProvider::Saml->value,
        'issuer' => 'https://idp.example.com/metadata',
        'idp_sso_url' => 'https://idp.example.com/sso',
        'idp_x509_cert' => sampleIdpCertificate(),
        'allowed_email_domains' => ['company.com'],
        'is_enabled' => true,
    ]);

    $requestId = '_req-123';
    $relayState = 'relay-state-token';
    $acsUrl = route('auth.sso.acs', absolute: true);
    $spEntityId = rtrim(config('app.url'), '/') . '/auth/sso/metadata';

    $this->withSession([
        'sso' => [
            'state' => $relayState,
            'nonce' => null,
            'request_id' => $requestId,
            'protocol' => 'saml',
            'organization_id' => $organization->id,
        ],
    ])->post(route('auth.sso.acs'), [
                'SAMLResponse' => unsignedSamlResponse(
                    'https://idp.example.com/metadata',
                    'owner@company.com',
                    $acsUrl,
                    $spEntityId,
                    $requestId,
                ),
                'RelayState' => $relayState,
            ])->assertRedirect(config('fortify.home'));

    $this->assertAuthenticated();

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::SsoLoginSuccess->value)
        ->exists())->toBeTrue();
});

test('saml acs rejects unknown email domain', function () {
    ['organization' => $organization] = makeSamlOrg();

    OrganizationSsoConnection::query()->create([
        'organization_id' => $organization->id,
        'provider' => SsoProvider::Saml->value,
        'issuer' => 'https://idp.example.com/metadata',
        'idp_sso_url' => 'https://idp.example.com/sso',
        'idp_x509_cert' => sampleIdpCertificate(),
        'allowed_email_domains' => ['company.com'],
        'is_enabled' => true,
    ]);

    $relayState = 'relay-state-token';
    $acsUrl = route('auth.sso.acs', absolute: true);
    $spEntityId = rtrim(config('app.url'), '/') . '/auth/sso/metadata';

    $this->withSession([
        'sso' => [
            'state' => $relayState,
            'request_id' => '_req',
            'protocol' => 'saml',
            'organization_id' => $organization->id,
        ],
    ])->post(route('auth.sso.acs'), [
                'SAMLResponse' => unsignedSamlResponse(
                    'https://idp.example.com/metadata',
                    'outsider@other.com',
                    $acsUrl,
                    $spEntityId,
                ),
                'RelayState' => $relayState,
            ])->assertRedirect(route('login'))
        ->assertSessionHasErrors('email_or_slug');

    $this->assertGuest();
});

test('sp metadata endpoint returns xml', function () {
    $this->get(route('auth.sso.metadata'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/samlmetadata+xml; charset=UTF-8')
        ->assertSee('EntityDescriptor', false)
        ->assertSee('AssertionConsumerService', false);
});
