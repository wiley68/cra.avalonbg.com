<?php

use App\Enums\AuditEventType;
use App\Enums\ClassificationStatus;
use App\Enums\EvidenceType;
use App\Enums\ImportSuggestionKind;
use App\Enums\ImportSuggestionStatus;
use App\Enums\IntegrationAuthType;
use App\Enums\IntegrationCategory;
use App\Enums\IntegrationConnectionStatus;
use App\Enums\IntegrationProvider;
use App\Enums\IntegrationSyncRunStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\VulnerabilityDiscoverySource;
use App\Enums\VulnerabilityStatus;
use App\Models\AuditLog;
use App\Models\Evidence;
use App\Models\ImportSuggestion;
use App\Models\IntegrationSyncRun;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Models\Product;
use App\Models\ProductIntegrationLink;
use App\Models\ProductVulnerability;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User}
 */
function makeSnykSettingsFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Snyk Settings Org',
        'slug' => 'snyk-settings-org-' . uniqid(),
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

/**
 * @return array{
 *     organization: Organization,
 *     owner: User,
 *     product: Product,
 *     integration: OrganizationIntegration
 * }
 */
function makeSnykProductFixture(): array
{
    ['organization' => $organization, 'owner' => $owner] = makeSnykSettingsFixture();

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Snyk Product',
        'slug' => 'snyk-product',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => false,
        'scope_status' => ScopeStatus::InsufficientInformation,
        'classification_status' => ClassificationStatus::Unclassified,
    ]);

    $integration = OrganizationIntegration::query()->create([
        'organization_id' => $organization->id,
        'provider' => IntegrationProvider::Snyk,
        'category' => IntegrationCategory::Scanner,
        'auth_type' => IntegrationAuthType::ApiToken,
        'credentials' => [
            'base_url' => 'https://api.snyk.io',
            'api_token' => 'snyk_api_token',
        ],
        'label' => 'Work Snyk',
        'status' => IntegrationConnectionStatus::Active,
        'last_verified_at' => now(),
    ]);

    return compact('organization', 'owner', 'product', 'integration');
}

test('owner can connect snyk with valid token and audit is recorded', function () {
    ['organization' => $organization, 'owner' => $owner] = makeSnykSettingsFixture();

    Http::fake([
        'https://api.snyk.io/rest/self*' => Http::response([
            'data' => ['id' => 'user-1', 'type' => 'user'],
        ], 200),
    ]);

    $this->actingAs($owner)
        ->post(route('settings.integrations.snyk.store'), [
            'api_token' => 'snyk_valid_api_token',
            'label' => 'Work Snyk',
        ])
        ->assertRedirect();

    $integration = OrganizationIntegration::query()->first();

    expect($integration)->not->toBeNull()
        ->and($integration->organization_id)->toBe($organization->id)
        ->and($integration->provider)->toBe(IntegrationProvider::Snyk)
        ->and($integration->category)->toBe(IntegrationCategory::Scanner)
        ->and($integration->auth_type)->toBe(IntegrationAuthType::ApiToken)
        ->and($integration->status)->toBe(IntegrationConnectionStatus::Active)
        ->and($integration->credentials['api_token'] ?? null)->toBe('snyk_valid_api_token')
        ->and($integration->toArray())->not->toHaveKey('credentials');

    expect(AuditLog::query()->where('event_type', AuditEventType::IntegrationConnected)->count())->toBe(1);
});

test('invalid snyk token is rejected', function () {
    ['owner' => $owner] = makeSnykSettingsFixture();

    Http::fake([
        'https://api.snyk.io/rest/self*' => Http::response(['errors' => []], 401),
    ]);

    $this->actingAs($owner)
        ->post(route('settings.integrations.snyk.store'), [
            'api_token' => 'bad_token_value',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('api_token');

    expect(OrganizationIntegration::query()->count())->toBe(0);
});

test('owner can disconnect snyk', function () {
    ['owner' => $owner, 'integration' => $integration] = makeSnykProductFixture();

    $this->actingAs($owner)
        ->delete(route('settings.integrations.providers.destroy', $integration))
        ->assertRedirect();

    expect(OrganizationIntegration::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('event_type', AuditEventType::IntegrationDisconnected)->count())->toBe(1);
});

test('product edit includes snyk integration props', function () {
    ['owner' => $owner, 'product' => $product] = makeSnykProductFixture();

    $this->actingAs($owner)
        ->get(route('products.edit', $product))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('products/Edit')
            ->where('snyk_link', null)
            ->where('snyk_integration.connected', true));
});

test('owner can link snyk project and audit is recorded', function () {
    ['owner' => $owner, 'product' => $product, 'integration' => $integration] = makeSnykProductFixture();

    Http::fake([
        'https://api.snyk.io/rest/orgs/org-1/projects/proj-1*' => Http::response([
            'data' => [
                'id' => 'proj-1',
                'attributes' => ['name' => 'Acme App'],
            ],
        ], 200),
    ]);

    $this->actingAs($owner)
        ->put(route('products.integrations.update', [$product, 'snyk']), [
            'org_id' => 'org-1',
            'project_id' => 'proj-1',
        ])
        ->assertRedirect();

    $link = ProductIntegrationLink::query()->first();

    expect($link)->not->toBeNull()
        ->and($link->product_id)->toBe($product->id)
        ->and($link->integration_id)->toBe($integration->id)
        ->and($link->external_project_key)->toBe('org-1')
        ->and($link->external_target_id)->toBe('proj-1')
        ->and($link->external_label)->toBe('Acme App')
        ->and($link->config['org_id'] ?? null)->toBe('org-1');

    expect(AuditLog::query()->where('event_type', AuditEventType::IntegrationLinked)->count())->toBe(1);
});

test('owner can sync snyk findings into pending vulnerability suggestions', function () {
    Storage::fake('local');

    ['owner' => $owner, 'product' => $product, 'integration' => $integration] = makeSnykProductFixture();

    $link = ProductIntegrationLink::query()->create([
        'product_id' => $product->id,
        'integration_id' => $integration->id,
        'external_project_key' => 'org-1',
        'external_target_id' => 'proj-1',
        'external_label' => 'Acme App',
        'config' => ['org_id' => 'org-1', 'project_id' => 'proj-1'],
    ]);

    Http::fake([
        'https://api.snyk.io/rest/orgs/org-1/issues*' => Http::response([
            'data' => [
                [
                    'id' => 'issue-1',
                    'attributes' => [
                        'title' => 'Prototype Pollution',
                        'key' => 'SNYK-JS-LODASH-1',
                        'effective_severity_level' => 'high',
                        'created_at' => '2026-07-20T10:00:00.000Z',
                        'problems' => [
                            ['id' => 'CVE-2026-9999', 'source' => 'CVE'],
                        ],
                        'coordinates' => [
                            ['representations' => 'lodash@4.17.20', 'ecosystem' => 'npm'],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $this->actingAs($owner)
        ->post(route('products.integrations.sync', [$product, 'snyk']))
        ->assertRedirect();

    $suggestion = ImportSuggestion::query()->first();
    $run = IntegrationSyncRun::query()->first();
    $evidence = Evidence::query()->first();
    $summary = $link->fresh()->last_sync_summary;

    expect($run)->not->toBeNull()
        ->and($run->status)->toBe(IntegrationSyncRunStatus::Succeeded)
        ->and($suggestion)->not->toBeNull()
        ->and($suggestion->kind)->toBe(ImportSuggestionKind::Vulnerability)
        ->and($suggestion->status)->toBe(ImportSuggestionStatus::Pending)
        ->and($suggestion->external_id)->toBe('issue-1')
        ->and($suggestion->payload['cve_id'] ?? null)->toBe('CVE-2026-9999')
        ->and($suggestion->payload['severity'] ?? null)->toBe('high');

    expect($evidence)->not->toBeNull()
        ->and($evidence->type)->toBe(EvidenceType::IntegrationSnapshot)
        ->and($evidence->source)->toBe('snyk:org-1/proj-1')
        ->and($evidence->product_id)->toBe($product->id)
        ->and($summary['findings_count'] ?? null)->toBe(1)
        ->and($summary['evidence_id'] ?? null)->toBe($evidence->id)
        ->and($summary['evidence_checksum_sha256'] ?? null)->toBe($evidence->checksum_sha256)
        ->and($summary['finding_refs'][0]['external_id'] ?? null)->toBe('issue-1')
        ->and(AuditLog::query()->where('event_type', AuditEventType::IntegrationSyncSucceeded)->count())->toBe(1)
        ->and(AuditLog::query()->where('event_type', AuditEventType::EvidenceCreated)->count())->toBe(1);

    Http::assertSent(function ($request) {
        return $request->method() === 'GET'
            && str_contains($request->url(), '/rest/orgs/org-1/issues')
            && $request->hasHeader('Authorization', 'token snyk_api_token');
    });
});

test('owner can accept snyk suggestion as product vulnerability', function () {
    ['owner' => $owner, 'product' => $product, 'integration' => $integration] = makeSnykProductFixture();

    $link = ProductIntegrationLink::query()->create([
        'product_id' => $product->id,
        'integration_id' => $integration->id,
        'external_project_key' => 'org-1',
        'external_target_id' => 'proj-1',
        'external_label' => 'Acme App',
    ]);

    $suggestion = ImportSuggestion::query()->create([
        'product_id' => $product->id,
        'link_id' => $link->id,
        'kind' => ImportSuggestionKind::Vulnerability,
        'external_id' => 'issue-1',
        'title' => 'lodash: Prototype Pollution',
        'payload' => [
            'title' => 'lodash: Prototype Pollution',
            'summary' => 'Package: lodash@4.17.20',
            'cve_id' => 'CVE-2026-9999',
            'severity' => 'high',
            'package_name' => 'lodash@4.17.20',
            'html_url' => 'https://app.snyk.io/org/org-1/project/proj-1#issue-issue-1',
            'snyk_issue_key' => 'SNYK-JS-LODASH-1',
            'created_at' => '2026-07-20T10:00:00.000Z',
        ],
        'status' => ImportSuggestionStatus::Pending,
    ]);

    $this->actingAs($owner)
        ->post(route('products.import-suggestions.accept', [$product, $suggestion]))
        ->assertRedirect();

    $vulnerability = ProductVulnerability::query()->first();
    $suggestion->refresh();

    expect($vulnerability)->not->toBeNull()
        ->and($vulnerability->product_id)->toBe($product->id)
        ->and($vulnerability->title)->toBe('lodash: Prototype Pollution')
        ->and($vulnerability->cve_id)->toBe('CVE-2026-9999')
        ->and($vulnerability->discovery_source)->toBe(VulnerabilityDiscoverySource::DependencyScanner)
        ->and($vulnerability->status)->toBe(VulnerabilityStatus::Reported)
        ->and($suggestion->status)->toBe(ImportSuggestionStatus::Accepted)
        ->and($suggestion->accepted_entity_id)->toBe($vulnerability->id)
        ->and(AuditLog::query()->where('event_type', AuditEventType::ImportSuggestionAccepted)->count())->toBe(1);
});

test('owner can dismiss snyk suggestion', function () {
    ['owner' => $owner, 'product' => $product, 'integration' => $integration] = makeSnykProductFixture();

    $link = ProductIntegrationLink::query()->create([
        'product_id' => $product->id,
        'integration_id' => $integration->id,
        'external_project_key' => 'org-1',
        'external_target_id' => 'proj-1',
        'external_label' => 'Acme App',
    ]);

    $suggestion = ImportSuggestion::query()->create([
        'product_id' => $product->id,
        'link_id' => $link->id,
        'kind' => ImportSuggestionKind::Vulnerability,
        'external_id' => 'issue-1',
        'title' => 'lodash: Prototype Pollution',
        'payload' => ['cve_id' => 'CVE-2026-9999'],
        'status' => ImportSuggestionStatus::Pending,
    ]);

    $this->actingAs($owner)
        ->post(route('products.import-suggestions.dismiss', [$product, $suggestion]))
        ->assertRedirect();

    expect($suggestion->fresh()->status)->toBe(ImportSuggestionStatus::Dismissed)
        ->and(ProductVulnerability::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('event_type', AuditEventType::ImportSuggestionDismissed)->count())->toBe(1);
});

test('read-only user cannot connect or accept snyk', function () {
    ['organization' => $organization, 'product' => $product, 'integration' => $integration] = makeSnykProductFixture();

    $link = ProductIntegrationLink::query()->create([
        'product_id' => $product->id,
        'integration_id' => $integration->id,
        'external_project_key' => 'org-1',
        'external_target_id' => 'proj-1',
        'external_label' => 'Acme App',
    ]);

    $suggestion = ImportSuggestion::query()->create([
        'product_id' => $product->id,
        'link_id' => $link->id,
        'kind' => ImportSuggestionKind::Vulnerability,
        'external_id' => 'issue-1',
        'title' => 'Finding',
        'payload' => [],
        'status' => ImportSuggestionStatus::Pending,
    ]);

    $viewer = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $viewerRole = Role::query()->where('slug', 'read_only')->firstOrFail();
    $organization->users()->attach($viewer->id, [
        'role_id' => $viewerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Http::fake();

    $this->actingAs($viewer)
        ->post(route('settings.integrations.snyk.store'), [
            'api_token' => 'snyk_valid_api_token',
        ])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->post(route('products.import-suggestions.accept', [$product, $suggestion]))
        ->assertForbidden();

    expect($suggestion->fresh()->status)->toBe(ImportSuggestionStatus::Pending)
        ->and(ProductVulnerability::query()->count())->toBe(0);
});
