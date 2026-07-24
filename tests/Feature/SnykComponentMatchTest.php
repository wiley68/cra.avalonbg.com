<?php

use App\Enums\ClassificationStatus;
use App\Enums\ComponentSupportStatus;
use App\Enums\ImportSuggestionKind;
use App\Enums\ImportSuggestionStatus;
use App\Enums\IntegrationAuthType;
use App\Enums\IntegrationCategory;
use App\Enums\IntegrationConnectionStatus;
use App\Enums\IntegrationProvider;
use App\Enums\IntegrationSyncRunStatus;
use App\Enums\LicensingModel;
use App\Enums\PackageEcosystem;
use App\Enums\ProductType;
use App\Enums\ProductVersionState;
use App\Enums\ScopeStatus;
use App\Enums\SupportStatus;
use App\Models\ImportSuggestion;
use App\Models\IntegrationSyncRun;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Models\Product;
use App\Models\ProductComponent;
use App\Models\ProductIntegrationLink;
use App\Models\ProductVersion;
use App\Models\ProductVulnerability;
use App\Models\Role;
use App\Models\User;
use App\Services\ComponentMatchService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     organization: Organization,
 *     owner: User,
 *     product: Product,
 *     version: ProductVersion,
 *     integration: OrganizationIntegration,
 *     link: ProductIntegrationLink
 * }
 */
function makeSnykComponentMatchFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Snyk Component Match Org',
        'slug' => 'snyk-component-match-' . uniqid(),
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

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Match Product',
        'slug' => 'match-product',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => false,
        'scope_status' => ScopeStatus::InsufficientInformation,
        'classification_status' => ClassificationStatus::Unclassified,
    ]);

    $version = ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '1.0.0',
        'state' => ProductVersionState::Draft,
        'support_status' => SupportStatus::Unknown,
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
        'label' => 'Snyk',
        'status' => IntegrationConnectionStatus::Active,
        'last_verified_at' => now(),
    ]);

    $link = ProductIntegrationLink::query()->create([
        'product_id' => $product->id,
        'integration_id' => $integration->id,
        'external_project_key' => 'org-1',
        'external_target_id' => 'proj-1',
        'external_label' => 'Acme App',
        'config' => ['org_id' => 'org-1', 'project_id' => 'proj-1'],
    ]);

    return compact('organization', 'owner', 'product', 'version', 'integration', 'link');
}

test('component match service matches by name and purl identity', function () {
    ['product' => $product, 'version' => $version] = makeSnykComponentMatchFixture();

    $byName = ProductComponent::query()->create([
        'product_id' => $product->id,
        'product_version_id' => $version->id,
        'name' => 'lodash',
        'package_ecosystem' => PackageEcosystem::Npm,
        'version' => '4.17.20',
        'purl' => null,
        'support_status' => ComponentSupportStatus::Unknown,
    ]);

    $byPurl = ProductComponent::query()->create([
        'product_id' => $product->id,
        'product_version_id' => $version->id,
        'name' => 'guzzlehttp/guzzle',
        'package_ecosystem' => PackageEcosystem::Composer,
        'version' => '7.8.1',
        'purl' => 'pkg:composer/guzzlehttp/guzzle@7.8.1',
        'support_status' => ComponentSupportStatus::Unknown,
    ]);

    ProductComponent::query()->create([
        'product_id' => $product->id,
        'product_version_id' => $version->id,
        'name' => 'unrelated',
        'package_ecosystem' => PackageEcosystem::Npm,
        'version' => '1.0.0',
        'support_status' => ComponentSupportStatus::Unknown,
    ]);

    $matcher = app(ComponentMatchService::class);

    expect($matcher->matchIdsForPackage($product->id, 'lodash@4.17.21', 'npm'))
        ->toBe([$byName->id])
        ->and($matcher->matchIdsForPackage(
            $product->id,
            null,
            'composer',
            'pkg:composer/guzzlehttp/guzzle@7.9.0',
        ))->toBe([$byPurl->id])
        ->and($matcher->matchIdsForPackage($product->id, 'missing-pkg', 'npm'))
        ->toBe([]);
});

test('snyk sync attaches matched sbom components to vulnerability suggestions', function () {
    Storage::fake('local');

    ['owner' => $owner, 'product' => $product, 'version' => $version, 'link' => $link] = makeSnykComponentMatchFixture();

    $component = ProductComponent::query()->create([
        'product_id' => $product->id,
        'product_version_id' => $version->id,
        'name' => 'lodash',
        'package_ecosystem' => PackageEcosystem::Npm,
        'version' => '4.17.20',
        'purl' => 'pkg:npm/lodash@4.17.20',
        'support_status' => ComponentSupportStatus::Unknown,
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
                            [
                                'representations' => 'lodash@4.17.20',
                                'ecosystem' => 'npm',
                                'purl' => 'pkg:npm/lodash@4.17.20',
                            ],
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
    $summary = $link->fresh()->last_sync_summary;

    expect(IntegrationSyncRun::query()->first()->status)->toBe(IntegrationSyncRunStatus::Succeeded)
        ->and($suggestion)->not->toBeNull()
        ->and($suggestion->payload['matched_component_ids'] ?? null)->toBe([$component->id])
        ->and($suggestion->payload['matched_components'][0]['name'] ?? null)->toBe('lodash')
        ->and($summary['suggestions_with_component_matches'] ?? null)->toBe(1);

    $this->actingAs($owner)
        ->get(route('products.edit', $product))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->where('import_suggestions.0.matched_components.0.id', $component->id)
            ->where('import_suggestions.0.matched_components.0.name', 'lodash'));
});

test('accepting snyk suggestion links matched product components', function () {
    ['owner' => $owner, 'product' => $product, 'version' => $version, 'link' => $link] = makeSnykComponentMatchFixture();

    $component = ProductComponent::query()->create([
        'product_id' => $product->id,
        'product_version_id' => $version->id,
        'name' => 'lodash',
        'package_ecosystem' => PackageEcosystem::Npm,
        'version' => '4.17.20',
        'purl' => 'pkg:npm/lodash@4.17.20',
        'support_status' => ComponentSupportStatus::Unknown,
    ]);

    $suggestion = ImportSuggestion::query()->create([
        'product_id' => $product->id,
        'link_id' => $link->id,
        'kind' => ImportSuggestionKind::Vulnerability,
        'external_id' => 'issue-1',
        'title' => 'lodash: Prototype Pollution',
        'payload' => [
            'title' => 'lodash: Prototype Pollution',
            'cve_id' => 'CVE-2026-9999',
            'severity' => 'high',
            'package_name' => 'lodash@4.17.20',
            'package_ecosystem' => 'npm',
            'package_purl' => 'pkg:npm/lodash@4.17.20',
            'matched_component_ids' => [$component->id],
            'matched_components' => [
                [
                    'id' => $component->id,
                    'name' => 'lodash',
                    'version' => '4.17.20',
                    'purl' => 'pkg:npm/lodash@4.17.20',
                ],
            ],
        ],
        'status' => ImportSuggestionStatus::Pending,
    ]);

    $this->actingAs($owner)
        ->post(route('products.import-suggestions.accept', [$product, $suggestion]))
        ->assertRedirect();

    $vulnerability = ProductVulnerability::query()->first();

    expect($vulnerability)->not->toBeNull()
        ->and($vulnerability->components()->pluck('product_components.id')->all())->toBe([$component->id]);
});

test('snyk sync without matching components leaves matched ids empty', function () {
    Storage::fake('local');

    ['owner' => $owner, 'product' => $product, 'link' => $link] = makeSnykComponentMatchFixture();

    Http::fake([
        'https://api.snyk.io/rest/orgs/org-1/issues*' => Http::response([
            'data' => [
                [
                    'id' => 'issue-2',
                    'attributes' => [
                        'title' => 'Unknown package issue',
                        'key' => 'SNYK-JS-OTHER-1',
                        'effective_severity_level' => 'medium',
                        'coordinates' => [
                            ['representations' => 'not-in-sbom@1.0.0', 'ecosystem' => 'npm'],
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

    expect($suggestion->payload['matched_component_ids'] ?? null)->toBe([])
        ->and($link->fresh()->last_sync_summary['suggestions_with_component_matches'] ?? null)->toBe(0);
});
