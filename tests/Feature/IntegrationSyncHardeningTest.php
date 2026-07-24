<?php

use App\Enums\AuditEventType;
use App\Enums\ClassificationStatus;
use App\Enums\ImportSuggestionKind;
use App\Enums\IntegrationAuthType;
use App\Enums\IntegrationCategory;
use App\Enums\IntegrationConnectionStatus;
use App\Enums\IntegrationProvider;
use App\Enums\IntegrationSyncRunStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Jobs\SyncProductIntegrationJob;
use App\Models\AuditLog;
use App\Models\Evidence;
use App\Models\ImportSuggestion;
use App\Models\IntegrationSyncRun;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Models\Product;
use App\Models\ProductIntegrationLink;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     organization: Organization,
 *     owner: User,
 *     product: Product,
 *     jira: OrganizationIntegration,
 *     snyk: OrganizationIntegration
 * }
 */
function makeSyncHardeningFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Sync Hardening Org',
        'slug' => 'sync-hardening-org-'.uniqid(),
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
        'name' => 'Hardening Product',
        'slug' => 'hardening-product',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => false,
        'scope_status' => ScopeStatus::InsufficientInformation,
        'classification_status' => ClassificationStatus::Unclassified,
    ]);

    $jira = OrganizationIntegration::query()->create([
        'organization_id' => $organization->id,
        'provider' => IntegrationProvider::Jira,
        'category' => IntegrationCategory::Alm,
        'auth_type' => IntegrationAuthType::ApiToken,
        'credentials' => [
            'base_url' => 'https://acme.atlassian.net',
            'email' => 'owner@acme.example',
            'api_token' => 'jira_api_token',
        ],
        'label' => 'Jira',
        'status' => IntegrationConnectionStatus::Active,
        'last_verified_at' => now(),
    ]);

    $snyk = OrganizationIntegration::query()->create([
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

    return compact('organization', 'owner', 'product', 'jira', 'snyk');
}

test('sync product integration job is unique per link', function () {
    $job = new SyncProductIntegrationJob(42);

    expect($job)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job->uniqueId())->toBe('42')
        ->and($job->uniqueFor)->toBe(300);
});

test('jira 403 soft-fails with last_error and succeeds sync', function () {
    Storage::fake('local');

    ['owner' => $owner, 'product' => $product, 'jira' => $jira] = makeSyncHardeningFixture();

    $link = ProductIntegrationLink::query()->create([
        'product_id' => $product->id,
        'integration_id' => $jira->id,
        'external_project_key' => 'CRA',
        'external_label' => 'CRA Project',
    ]);

    Http::fake([
        'https://acme.atlassian.net/rest/api/3/search/jql' => Http::response(['errorMessages' => ['Forbidden']], 403),
    ]);

    $this->actingAs($owner)
        ->post(route('products.integrations.sync', [$product, 'jira']))
        ->assertRedirect();

    $run = IntegrationSyncRun::query()->first();
    $summary = $link->fresh()->last_sync_summary;

    expect($run)->not->toBeNull()
        ->and($run->status)->toBe(IntegrationSyncRunStatus::Succeeded)
        ->and($summary['soft_fail'] ?? null)->toBeTrue()
        ->and($summary['last_error'] ?? null)->not->toBeNull()
        ->and($summary['error'] ?? null)->toBeNull()
        ->and($summary['issues_count'] ?? null)->toBe(0)
        ->and($summary['evidence_id'] ?? null)->not->toBeNull()
        ->and(ImportSuggestion::query()->count())->toBe(0)
        ->and(Evidence::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('event_type', AuditEventType::IntegrationSyncSucceeded)->count())->toBe(1);
});

test('snyk 401 soft-fails with last_error without creating suggestions', function () {
    Storage::fake('local');

    ['owner' => $owner, 'product' => $product, 'snyk' => $snyk] = makeSyncHardeningFixture();

    $link = ProductIntegrationLink::query()->create([
        'product_id' => $product->id,
        'integration_id' => $snyk->id,
        'external_project_key' => 'org-1',
        'external_target_id' => 'proj-1',
        'external_label' => 'Acme App',
        'config' => ['org_id' => 'org-1', 'project_id' => 'proj-1'],
    ]);

    Http::fake([
        'https://api.snyk.io/rest/orgs/org-1/issues*' => Http::response(['errors' => [['detail' => 'Unauthorized']]], 401),
    ]);

    $this->actingAs($owner)
        ->post(route('products.integrations.sync', [$product, 'snyk']))
        ->assertRedirect();

    $run = IntegrationSyncRun::query()->first();
    $summary = $link->fresh()->last_sync_summary;

    expect($run->status)->toBe(IntegrationSyncRunStatus::Succeeded)
        ->and($summary['soft_fail'] ?? null)->toBeTrue()
        ->and($summary['last_error'] ?? null)->not->toBeNull()
        ->and($summary['findings_count'] ?? null)->toBe(0)
        ->and(ImportSuggestion::query()->where('kind', ImportSuggestionKind::Vulnerability)->count())->toBe(0);
});

test('jira rate limit 429 soft-fails like missing scopes', function () {
    Storage::fake('local');

    ['owner' => $owner, 'product' => $product, 'jira' => $jira] = makeSyncHardeningFixture();

    ProductIntegrationLink::query()->create([
        'product_id' => $product->id,
        'integration_id' => $jira->id,
        'external_project_key' => 'CRA',
        'external_label' => 'CRA Project',
    ]);

    Http::fake([
        'https://acme.atlassian.net/rest/api/3/search/jql' => Http::response(['message' => 'Rate limited'], 429),
    ]);

    $this->actingAs($owner)
        ->post(route('products.integrations.sync', [$product, 'jira']))
        ->assertRedirect();

    expect(IntegrationSyncRun::query()->first()->status)->toBe(IntegrationSyncRunStatus::Succeeded)
        ->and(ProductIntegrationLink::query()->first()->last_sync_summary['soft_fail'] ?? null)->toBeTrue();
});

test('hard sync failure stores both error and last_error', function () {
    Storage::fake('local');

    ['owner' => $owner, 'product' => $product, 'jira' => $jira] = makeSyncHardeningFixture();

    $link = ProductIntegrationLink::query()->create([
        'product_id' => $product->id,
        'integration_id' => $jira->id,
        'external_project_key' => 'CRA',
        'external_label' => 'CRA Project',
    ]);

    Http::fake([
        'https://acme.atlassian.net/rest/api/3/search/jql' => Http::response(['errorMessages' => ['Boom']], 500),
    ]);

    $this->actingAs($owner)
        ->post(route('products.integrations.sync', [$product, 'jira']))
        ->assertRedirect();

    $run = IntegrationSyncRun::query()->first();
    $summary = $link->fresh()->last_sync_summary;

    expect($run->status)->toBe(IntegrationSyncRunStatus::Failed)
        ->and($summary['error'] ?? null)->not->toBeNull()
        ->and($summary['last_error'] ?? null)->toBe($summary['error'])
        ->and($summary['soft_fail'] ?? null)->toBeNull()
        ->and(AuditLog::query()->where('event_type', AuditEventType::IntegrationSyncFailed)->count())->toBe(1);
});
