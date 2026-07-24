<?php

use App\Enums\AuditEventType;
use App\Enums\ClassificationStatus;
use App\Enums\ImportSuggestionKind;
use App\Enums\ImportSuggestionStatus;
use App\Enums\IntegrationAuthType;
use App\Enums\IntegrationCategory;
use App\Enums\IntegrationConnectionStatus;
use App\Enums\IntegrationProvider;
use App\Enums\IntegrationSyncRunStatus;
use App\Enums\IntegrationSyncSchedule;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Models\ImportSuggestion;
use App\Models\IntegrationSyncRun;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Models\Product;
use App\Models\ProductIntegrationLink;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, product: Product, user: User}
 */
function makeIntegrationWave2Fixture(): array
{
    $organization = Organization::query()->create([
        'name' => 'Integration Wave2 Org',
        'slug' => 'integration-wave2-org-' . uniqid(),
        'is_active' => true,
        'locale' => 'en',
    ]);

    $user = User::factory()->create();

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Integration Wave2 Product',
        'slug' => 'integration-wave2-product-' . uniqid(),
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    return compact('organization', 'product', 'user');
}

test('organization integration encrypts credentials and hides them from arrays', function () {
    ['organization' => $organization] = makeIntegrationWave2Fixture();

    $plainCredentials = [
        'email' => 'jira@example.com',
        'api_token' => 'jira_plain_token_value',
        'base_url' => 'https://example.atlassian.net',
    ];

    $integration = OrganizationIntegration::query()->create([
        'organization_id' => $organization->id,
        'provider' => IntegrationProvider::Jira,
        'category' => IntegrationProvider::Jira->category(),
        'auth_type' => IntegrationAuthType::ApiToken,
        'credentials' => $plainCredentials,
        'label' => 'Jira Cloud',
        'status' => IntegrationConnectionStatus::Active,
        'sync_schedule' => IntegrationSyncSchedule::Daily,
        'last_verified_at' => now(),
    ]);

    $rawCredentials = DB::table('organization_integrations')
        ->where('id', $integration->id)
        ->value('credentials');

    expect($rawCredentials)->not->toBe(json_encode($plainCredentials));
    expect($integration->fresh()->credentials)->toBe($plainCredentials);
    expect($integration->toArray())->not->toHaveKey('credentials');
    expect($integration->provider->category())->toBe(IntegrationCategory::Alm);
    expect(IntegrationProvider::Snyk->category())->toBe(IntegrationCategory::Scanner);
});

test('integration link sync run and suggestion relations resolve', function () {
    ['organization' => $organization, 'product' => $product, 'user' => $user] = makeIntegrationWave2Fixture();

    $integration = OrganizationIntegration::query()->create([
        'organization_id' => $organization->id,
        'provider' => IntegrationProvider::Jira,
        'category' => IntegrationCategory::Alm,
        'auth_type' => IntegrationAuthType::ApiToken,
        'credentials' => ['api_token' => 'token'],
        'label' => 'Jira Cloud',
        'status' => IntegrationConnectionStatus::Active,
        'sync_schedule' => IntegrationSyncSchedule::Daily,
    ]);

    $link = ProductIntegrationLink::query()->create([
        'product_id' => $product->id,
        'integration_id' => $integration->id,
        'external_project_key' => 'CRA',
        'external_target_id' => null,
        'external_label' => 'CRA board',
        'config' => ['jql' => 'project = CRA AND labels = compliance'],
        'last_sync_summary' => ['imported' => 2],
    ]);

    $run = IntegrationSyncRun::query()->create([
        'link_id' => $link->id,
        'status' => IntegrationSyncRunStatus::Succeeded,
        'triggered_by' => $user->id,
        'started_at' => now()->subMinute(),
        'finished_at' => now(),
        'summary' => ['issues' => 2],
    ]);

    $suggestion = ImportSuggestion::query()->create([
        'product_id' => $product->id,
        'link_id' => $link->id,
        'kind' => ImportSuggestionKind::Task,
        'external_id' => 'CRA-42',
        'title' => 'Document SBOM for release',
        'payload' => ['url' => 'https://example.atlassian.net/browse/CRA-42'],
        'status' => ImportSuggestionStatus::Pending,
    ]);

    expect($organization->integrations)->toHaveCount(1);
    expect($product->integrationLinks)->toHaveCount(1);
    expect($product->importSuggestions)->toHaveCount(1);
    expect($link->integration->id)->toBe($integration->id);
    expect($link->syncRuns)->toHaveCount(1);
    expect($link->importSuggestions)->toHaveCount(1);
    expect($run->triggeredByUser?->id)->toBe($user->id);
    expect($suggestion->kind)->toBe(ImportSuggestionKind::Task);
    expect($suggestion->status)->toBe(ImportSuggestionStatus::Pending);
});

test('integration audit event types resolve translated labels', function () {
    expect(AuditEventType::IntegrationConnected->value)->toBe('integration_connected');
    expect(AuditEventType::ImportSuggestionAccepted->label())->not->toBe(
        'audit_logs.event_types.import_suggestion_accepted',
    );
});

test('organization integration provider is unique per organization', function () {
    ['organization' => $organization] = makeIntegrationWave2Fixture();

    OrganizationIntegration::query()->create([
        'organization_id' => $organization->id,
        'provider' => IntegrationProvider::Snyk,
        'category' => IntegrationCategory::Scanner,
        'auth_type' => IntegrationAuthType::ApiToken,
        'credentials' => ['api_token' => 'snyk_token'],
        'status' => IntegrationConnectionStatus::Active,
        'sync_schedule' => IntegrationSyncSchedule::Off,
    ]);

    expect(fn() => OrganizationIntegration::query()->create([
        'organization_id' => $organization->id,
        'provider' => IntegrationProvider::Snyk,
        'category' => IntegrationCategory::Scanner,
        'auth_type' => IntegrationAuthType::ApiToken,
        'credentials' => ['api_token' => 'other'],
        'status' => IntegrationConnectionStatus::Active,
        'sync_schedule' => IntegrationSyncSchedule::Off,
    ]))->toThrow(QueryException::class);
});

test('product integration link is unique per product and integration', function () {
    ['organization' => $organization, 'product' => $product] = makeIntegrationWave2Fixture();

    $integration = OrganizationIntegration::query()->create([
        'organization_id' => $organization->id,
        'provider' => IntegrationProvider::Jira,
        'category' => IntegrationCategory::Alm,
        'auth_type' => IntegrationAuthType::ApiToken,
        'credentials' => ['api_token' => 'token'],
        'status' => IntegrationConnectionStatus::Active,
        'sync_schedule' => IntegrationSyncSchedule::Off,
    ]);

    ProductIntegrationLink::query()->create([
        'product_id' => $product->id,
        'integration_id' => $integration->id,
        'external_project_key' => 'CRA',
    ]);

    expect(fn() => ProductIntegrationLink::query()->create([
        'product_id' => $product->id,
        'integration_id' => $integration->id,
        'external_project_key' => 'OTHER',
    ]))->toThrow(QueryException::class);
});

test('import suggestion external id is unique per link and kind', function () {
    ['organization' => $organization, 'product' => $product] = makeIntegrationWave2Fixture();

    $integration = OrganizationIntegration::query()->create([
        'organization_id' => $organization->id,
        'provider' => IntegrationProvider::Snyk,
        'category' => IntegrationCategory::Scanner,
        'auth_type' => IntegrationAuthType::ApiToken,
        'credentials' => ['api_token' => 'snyk'],
        'status' => IntegrationConnectionStatus::Active,
        'sync_schedule' => IntegrationSyncSchedule::Hourly,
    ]);

    $link = ProductIntegrationLink::query()->create([
        'product_id' => $product->id,
        'integration_id' => $integration->id,
        'external_target_id' => 'org/project',
        'external_label' => 'Snyk project',
    ]);

    ImportSuggestion::query()->create([
        'product_id' => $product->id,
        'link_id' => $link->id,
        'kind' => ImportSuggestionKind::Vulnerability,
        'external_id' => 'SNYK-JS-LODASH-1',
        'title' => 'Prototype Pollution',
        'payload' => ['severity' => 'CVE-2021-23337'],
        'status' => ImportSuggestionStatus::Pending,
    ]);

    expect(fn() => ImportSuggestion::query()->create([
        'product_id' => $product->id,
        'link_id' => $link->id,
        'kind' => ImportSuggestionKind::Vulnerability,
        'external_id' => 'SNYK-JS-LODASH-1',
        'title' => 'Duplicate',
        'status' => ImportSuggestionStatus::Dismissed,
    ]))->toThrow(QueryException::class);
});

test('integration sync schedule isDue matches hourly and daily windows', function () {
    expect(IntegrationSyncSchedule::Off->isDue(null))->toBeFalse();
    expect(IntegrationSyncSchedule::Hourly->isDue(null))->toBeTrue();
    expect(IntegrationSyncSchedule::Hourly->isDue(now()->subMinutes(30)))->toBeFalse();
    expect(IntegrationSyncSchedule::Hourly->isDue(now()->subHours(2)))->toBeTrue();
    expect(IntegrationSyncSchedule::Daily->isDue(now()->subHours(12)))->toBeFalse();
    expect(IntegrationSyncSchedule::Daily->isDue(now()->subDays(2)))->toBeTrue();
});
