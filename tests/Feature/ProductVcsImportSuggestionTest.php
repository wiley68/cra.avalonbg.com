<?php

use App\Enums\AuditEventType;
use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ProductVersionState;
use App\Enums\ScopeStatus;
use App\Enums\SupportStatus;
use App\Enums\VcsAuthType;
use App\Enums\VcsConnectionStatus;
use App\Enums\VcsImportSuggestionKind;
use App\Enums\VcsImportSuggestionStatus;
use App\Enums\VcsProvider;
use App\Enums\VcsSyncRunStatus;
use App\Enums\VulnerabilityDiscoverySource;
use App\Enums\VulnerabilityStatus;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationVcsConnection;
use App\Models\Product;
use App\Models\ProductRepository;
use App\Models\ProductVersion;
use App\Models\ProductVulnerability;
use App\Models\Role;
use App\Models\User;
use App\Models\VcsImportSuggestion;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * @return array{owner: User, product: Product, repository: ProductRepository, organization: Organization}
 */
function makeSuggestionFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Suggestion Org',
        'slug' => 'suggestion-org',
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

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Suggestion Product',
        'slug' => 'suggestion-product',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => false,
        'scope_status' => ScopeStatus::InsufficientInformation,
        'classification_status' => ClassificationStatus::Unclassified,
    ]);

    $connection = OrganizationVcsConnection::query()->create([
        'organization_id' => $organization->id,
        'provider' => VcsProvider::Github,
        'auth_type' => VcsAuthType::Pat,
        'token' => 'ghp_suggestion_token',
        'label' => 'GitHub',
        'status' => VcsConnectionStatus::Active,
        'last_verified_at' => now(),
    ]);

    $repository = ProductRepository::query()->create([
        'product_id' => $product->id,
        'connection_id' => $connection->id,
        'external_id' => '99',
        'full_name' => 'acme/widget',
        'remote_url' => 'https://github.com/acme/widget',
        'default_branch' => 'main',
    ]);

    return compact('owner', 'product', 'repository', 'organization');
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, \Illuminate\Http\Client\Response|\GuzzleHttp\Promise\PromiseInterface>
 */
function suggestionSyncHttpFakes(array $overrides = []): array
{
    return array_merge([
        'api.github.com/repos/acme/widget/tags*' => Http::response([], 200),
        'api.github.com/repos/acme/widget/releases*' => Http::response([
            [
                'tag_name' => 'v2.0.0',
                'name' => 'Release 2.0.0',
                'body' => 'Changelog for 2.0.0',
                'published_at' => '2026-07-10T00:00:00Z',
                'html_url' => 'https://github.com/acme/widget/releases/tag/v2.0.0',
            ],
        ], 200),
        'api.github.com/repos/acme/widget/actions/runs*' => Http::response([
            'workflow_runs' => [],
        ], 200),
        'api.github.com/repos/acme/widget/dependabot/alerts*' => Http::response([
            [
                'number' => 7,
                'html_url' => 'https://github.com/acme/widget/security/dependabot/7',
                'created_at' => '2026-07-05T12:00:00Z',
                'dependency' => [
                    'package' => [
                        'ecosystem' => 'npm',
                        'name' => 'lodash',
                    ],
                ],
                'security_advisory' => [
                    'ghsa_id' => 'GHSA-xxxx-yyyy-zzzz',
                    'cve_id' => 'CVE-2026-1234',
                    'summary' => 'Prototype pollution in lodash',
                    'severity' => 'high',
                ],
            ],
        ], 200),
    ], $overrides);
}

test('sync upserts version and vulnerability suggestions', function () {
    Storage::fake('local');

    ['owner' => $owner, 'product' => $product] = makeSuggestionFixture();

    Http::fake(suggestionSyncHttpFakes());

    $this->actingAs($owner)
        ->post(route('products.repository.sync', $product))
        ->assertRedirect();

    $suggestions = VcsImportSuggestion::query()->orderBy('kind')->get();

    expect($suggestions)->toHaveCount(2)
        ->and($suggestions->firstWhere('kind', VcsImportSuggestionKind::Version)?->external_id)->toBe('release:v2.0.0')
        ->and($suggestions->firstWhere('kind', VcsImportSuggestionKind::Version)?->status)->toBe(VcsImportSuggestionStatus::Pending)
        ->and($suggestions->firstWhere('kind', VcsImportSuggestionKind::Vulnerability)?->external_id)->toBe('dependabot:7')
        ->and($suggestions->firstWhere('kind', VcsImportSuggestionKind::Vulnerability)?->payload['cve_id'])->toBe('CVE-2026-1234')
        ->and($product->fresh()->repository->last_sync_summary['alerts_count'])->toBe(1)
        ->and($product->fresh()->repository->last_sync_summary['version_suggestions_upserted'])->toBe(1)
        ->and($product->fresh()->repository->last_sync_summary['vulnerability_suggestions_upserted'])->toBe(1)
        ->and($product->fresh()->repository->last_sync_summary['pending_version_suggestions'])->toBe(1)
        ->and($product->fresh()->repository->last_sync_summary['pending_vulnerability_suggestions'])->toBe(1);
});

test('sync skips suggestions when version or cve already exists', function () {
    Storage::fake('local');

    ['owner' => $owner, 'product' => $product] = makeSuggestionFixture();

    ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => 'v2.0.0',
        'state' => ProductVersionState::Draft,
        'support_status' => SupportStatus::Unknown,
    ]);

    ProductVulnerability::query()->create([
        'product_id' => $product->id,
        'title' => 'Existing',
        'cve_id' => 'CVE-2026-1234',
        'discovery_source' => VulnerabilityDiscoverySource::DependencyScanner,
        'status' => VulnerabilityStatus::Reported,
        'business_severity' => \App\Enums\VulnerabilityBusinessSeverity::High,
        'exploitation_status' => \App\Enums\VulnerabilityExploitationStatus::Unknown,
        'is_public' => false,
    ]);

    Http::fake(suggestionSyncHttpFakes());

    $this->actingAs($owner)
        ->post(route('products.repository.sync', $product))
        ->assertRedirect();

    expect(VcsImportSuggestion::query()->count())->toBe(0)
        ->and($product->fresh()->repository->last_sync_summary['version_suggestions_upserted'])->toBe(0)
        ->and($product->fresh()->repository->last_sync_summary['vulnerability_suggestions_upserted'])->toBe(0);
});

test('dependabot 403 soft-fails without creating vulnerability suggestions', function () {
    Storage::fake('local');

    ['owner' => $owner, 'product' => $product] = makeSuggestionFixture();

    Http::fake(suggestionSyncHttpFakes([
        'api.github.com/repos/acme/widget/dependabot/alerts*' => Http::response(['message' => 'Forbidden'], 403),
    ]));

    $this->actingAs($owner)
        ->post(route('products.repository.sync', $product))
        ->assertRedirect();

    expect($product->fresh()->repository->last_sync_summary['error'] ?? null)->toBeNull()
        ->and($product->fresh()->repository->last_sync_summary['alerts_count'])->toBe(0)
        ->and(VcsImportSuggestion::query()->where('kind', VcsImportSuggestionKind::Vulnerability)->count())->toBe(0)
        ->and(VcsImportSuggestion::query()->where('kind', VcsImportSuggestionKind::Version)->count())->toBe(1)
        ->and(\App\Models\VcsSyncRun::query()->first()->status)->toBe(VcsSyncRunStatus::Succeeded);
});

test('dependabot 401 soft-fails without failing the sync', function () {
    Storage::fake('local');

    ['owner' => $owner, 'product' => $product] = makeSuggestionFixture();

    Http::fake(suggestionSyncHttpFakes([
        'api.github.com/repos/acme/widget/dependabot/alerts*' => Http::response(['message' => 'Bad credentials'], 401),
    ]));

    $this->actingAs($owner)
        ->post(route('products.repository.sync', $product))
        ->assertRedirect();

    expect(\App\Models\VcsSyncRun::query()->first()->status)->toBe(VcsSyncRunStatus::Succeeded)
        ->and($product->fresh()->repository->last_sync_summary['alerts_count'])->toBe(0)
        ->and(VcsImportSuggestion::query()->where('kind', VcsImportSuggestionKind::Vulnerability)->count())->toBe(0);
});

test('accept version suggestion creates draft product version', function () {
    ['owner' => $owner, 'product' => $product, 'repository' => $repository] = makeSuggestionFixture();

    $suggestion = VcsImportSuggestion::query()->create([
        'product_id' => $product->id,
        'repository_id' => $repository->id,
        'kind' => VcsImportSuggestionKind::Version,
        'external_id' => 'release:v3.1.0',
        'payload' => [
            'title' => 'Release 3.1.0',
            'summary' => 'Notes',
            'tag_name' => 'v3.1.0',
            'version_number' => 'v3.1.0',
            'published_at' => '2026-07-12T00:00:00Z',
            'html_url' => 'https://github.com/acme/widget/releases/tag/v3.1.0',
            'body' => 'Full changelog',
        ],
        'status' => VcsImportSuggestionStatus::Pending,
    ]);

    $this->actingAs($owner)
        ->post(route('products.vcs-suggestions.accept', [$product, $suggestion]))
        ->assertRedirect();

    $version = ProductVersion::query()->first();
    $suggestion->refresh();

    expect($version)->not->toBeNull()
        ->and($version->version_number)->toBe('v3.1.0')
        ->and($version->state)->toBe(ProductVersionState::Draft)
        ->and($version->support_status)->toBe(SupportStatus::Unknown)
        ->and($version->git_ref)->toBe('v3.1.0')
        ->and($version->changelog)->toBe('Full changelog')
        ->and($version->release_date?->toDateString())->toBe('2026-07-12')
        ->and($suggestion->status)->toBe(VcsImportSuggestionStatus::Accepted)
        ->and($suggestion->accepted_entity_type)->toBe(ProductVersion::class)
        ->and($suggestion->accepted_entity_id)->toBe($version->id)
        ->and(AuditLog::query()->where('event_type', AuditEventType::VcsSuggestionAccepted)->count())->toBe(1);
});

test('accept vulnerability suggestion creates reported vulnerability', function () {
    ['owner' => $owner, 'product' => $product, 'repository' => $repository] = makeSuggestionFixture();

    $suggestion = VcsImportSuggestion::query()->create([
        'product_id' => $product->id,
        'repository_id' => $repository->id,
        'kind' => VcsImportSuggestionKind::Vulnerability,
        'external_id' => 'dependabot:7',
        'payload' => [
            'title' => 'lodash: Prototype pollution',
            'summary' => 'Prototype pollution in lodash',
            'cve_id' => 'CVE-2026-9999',
            'ghsa_id' => 'GHSA-aaaa-bbbb-cccc',
            'severity' => 'critical',
            'package_name' => 'lodash',
            'package_ecosystem' => 'npm',
            'html_url' => 'https://github.com/acme/widget/security/dependabot/7',
            'created_at' => '2026-07-05T12:00:00Z',
        ],
        'status' => VcsImportSuggestionStatus::Pending,
    ]);

    $this->actingAs($owner)
        ->post(route('products.vcs-suggestions.accept', [$product, $suggestion]))
        ->assertRedirect();

    $vulnerability = ProductVulnerability::query()->first();
    $suggestion->refresh();

    expect($vulnerability)->not->toBeNull()
        ->and($vulnerability->status)->toBe(VulnerabilityStatus::Reported)
        ->and($vulnerability->discovery_source)->toBe(VulnerabilityDiscoverySource::DependencyScanner)
        ->and($vulnerability->cve_id)->toBe('CVE-2026-9999')
        ->and($vulnerability->business_severity->value)->toBe('critical')
        ->and($suggestion->status)->toBe(VcsImportSuggestionStatus::Accepted)
        ->and($suggestion->accepted_entity_id)->toBe($vulnerability->id);
});

test('dismissed suggestion is not recreated on re-sync', function () {
    Storage::fake('local');

    ['owner' => $owner, 'product' => $product, 'repository' => $repository] = makeSuggestionFixture();

    $suggestion = VcsImportSuggestion::query()->create([
        'product_id' => $product->id,
        'repository_id' => $repository->id,
        'kind' => VcsImportSuggestionKind::Version,
        'external_id' => 'release:v2.0.0',
        'payload' => ['title' => 'Release 2.0.0', 'tag_name' => 'v2.0.0', 'version_number' => 'v2.0.0'],
        'status' => VcsImportSuggestionStatus::Pending,
    ]);

    $this->actingAs($owner)
        ->post(route('products.vcs-suggestions.dismiss', [$product, $suggestion]))
        ->assertRedirect();

    expect($suggestion->fresh()->status)->toBe(VcsImportSuggestionStatus::Dismissed)
        ->and(AuditLog::query()->where('event_type', AuditEventType::VcsSuggestionDismissed)->count())->toBe(1);

    Http::fake(suggestionSyncHttpFakes([
        'api.github.com/repos/acme/widget/dependabot/alerts*' => Http::response([], 200),
    ]));

    $this->actingAs($owner)
        ->post(route('products.repository.sync', $product))
        ->assertRedirect();

    expect(VcsImportSuggestion::query()->where('external_id', 'release:v2.0.0')->count())->toBe(1)
        ->and(VcsImportSuggestion::query()->where('external_id', 'release:v2.0.0')->first()->status)
        ->toBe(VcsImportSuggestionStatus::Dismissed);
});

test('product edit includes pending vcs suggestions', function () {
    ['owner' => $owner, 'product' => $product, 'repository' => $repository] = makeSuggestionFixture();

    VcsImportSuggestion::query()->create([
        'product_id' => $product->id,
        'repository_id' => $repository->id,
        'kind' => VcsImportSuggestionKind::Version,
        'external_id' => 'release:v9.0.0',
        'payload' => [
            'title' => 'Release 9.0.0',
            'tag_name' => 'v9.0.0',
            'version_number' => 'v9.0.0',
            'html_url' => null,
        ],
        'status' => VcsImportSuggestionStatus::Pending,
    ]);

    $this->actingAs($owner)
        ->get(route('products.edit', $product))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/Edit')
            ->has('vcs_suggestions', 1)
            ->where('vcs_suggestions.0.external_id', 'release:v9.0.0'));
});

test('read-only user cannot accept suggestion', function () {
    ['organization' => $organization, 'product' => $product, 'repository' => $repository] = makeSuggestionFixture();

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

    $suggestion = VcsImportSuggestion::query()->create([
        'product_id' => $product->id,
        'repository_id' => $repository->id,
        'kind' => VcsImportSuggestionKind::Version,
        'external_id' => 'release:v1.0.0',
        'payload' => ['title' => 'v1', 'tag_name' => 'v1.0.0', 'version_number' => 'v1.0.0'],
        'status' => VcsImportSuggestionStatus::Pending,
    ]);

    $this->actingAs($viewer)
        ->post(route('products.vcs-suggestions.accept', [$product, $suggestion]))
        ->assertForbidden();

    expect($suggestion->fresh()->status)->toBe(VcsImportSuggestionStatus::Pending)
        ->and(ProductVersion::query()->count())->toBe(0);
});
