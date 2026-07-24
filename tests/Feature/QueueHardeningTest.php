<?php

use App\Enums\ClassificationStatus;
use App\Enums\IntegrationAuthType;
use App\Enums\IntegrationCategory;
use App\Enums\IntegrationConnectionStatus;
use App\Enums\IntegrationProvider;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\VcsAuthType;
use App\Enums\VcsConnectionStatus;
use App\Enums\VcsProvider;
use App\Jobs\SyncProductIntegrationJob;
use App\Jobs\SyncProductRepositoryJob;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Models\OrganizationVcsConnection;
use App\Models\Product;
use App\Models\ProductIntegrationLink;
use App\Models\ProductRepository;
use App\Models\Role;
use App\Models\User;
use App\Support\QueuedSyncFailureRecorder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     organization: Organization,
 *     owner: User,
 *     product: Product,
 *     link: ProductIntegrationLink,
 *     repository: ProductRepository
 * }
 */
function makeQueueHardeningFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Queue Harden Org',
        'slug' => 'queue-harden-org-' . uniqid(),
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
        'name' => 'Queue Product',
        'slug' => 'queue-product',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => false,
        'scope_status' => ScopeStatus::InsufficientInformation,
        'classification_status' => ClassificationStatus::Unclassified,
    ]);

    $integration = OrganizationIntegration::query()->create([
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

    $link = ProductIntegrationLink::query()->create([
        'product_id' => $product->id,
        'integration_id' => $integration->id,
        'external_project_key' => 'CRA',
        'external_label' => 'CRA Project',
    ]);

    $connection = OrganizationVcsConnection::query()->create([
        'organization_id' => $organization->id,
        'provider' => VcsProvider::Github,
        'auth_type' => VcsAuthType::Pat,
        'token' => 'ghp_test_token',
        'label' => 'GitHub',
        'status' => VcsConnectionStatus::Active,
        'last_verified_at' => now(),
    ]);

    $repository = ProductRepository::query()->create([
        'product_id' => $product->id,
        'connection_id' => $connection->id,
        'external_id' => '42',
        'full_name' => 'acme/queue-product',
        'remote_url' => 'https://github.com/acme/queue-product',
        'default_branch' => 'main',
    ]);

    return compact('organization', 'owner', 'product', 'link', 'repository');
}

test('sync jobs declare tries backoff and timeout for queue hardening', function () {
    $integrationJob = new SyncProductIntegrationJob(1);
    $vcsJob = new SyncProductRepositoryJob(1);

    expect($integrationJob->tries)->toBe(3)
        ->and($integrationJob->backoff)->toBe([15, 60, 120])
        ->and($integrationJob->timeout)->toBe(90)
        ->and($vcsJob->tries)->toBe(3)
        ->and($vcsJob->backoff)->toBe([15, 60, 120])
        ->and($vcsJob->timeout)->toBe(90);
});

test('database queue retry_after is greater than sync job timeout', function () {
    expect((int) config('queue.connections.database.retry_after'))->toBeGreaterThan(90);
});

test('failed integration sync job records queue_failed on link summary', function () {
    ['link' => $link] = makeQueueHardeningFixture();

    $job = new SyncProductIntegrationJob($link->id);
    $job->failed(new RuntimeException('api_token=supersecret boom'));

    $summary = $link->fresh()->last_sync_summary;

    expect($summary['queue_failed'])->toBeTrue()
        ->and($summary['soft_fail'])->toBeFalse()
        ->and($summary['last_error'])->toContain('RuntimeException')
        ->and($summary['last_error'])->toContain('[redacted]')
        ->and($summary['last_error'])->not->toContain('supersecret')
        ->and($summary)->toHaveKey('failed_at');
});

test('failed vcs sync job records queue_failed on repository summary', function () {
    ['repository' => $repository] = makeQueueHardeningFixture();

    $job = new SyncProductRepositoryJob($repository->id);
    $job->failed(new RuntimeException('hard fail'));

    $summary = $repository->fresh()->last_sync_summary;

    expect($summary['queue_failed'])->toBeTrue()
        ->and($summary['last_error'])->toContain('hard fail');
});

test('queued sync failure recorder redacts credential-like tokens', function () {
    $message = QueuedSyncFailureRecorder::safeMessage(
        new RuntimeException('Authorization: Bearer ghp_ABCDEFG123 and sk-proj-xyz'),
    );

    expect($message)->toContain('[redacted]')
        ->and($message)->not->toContain('ghp_ABCDEFG123')
        ->and($message)->not->toContain('sk-proj-xyz');
});

test('manual sync now uses dispatchSync and does not enqueue a database job', function () {
    Storage::fake('local');
    config(['queue.default' => 'database']);

    ['owner' => $owner, 'product' => $product, 'link' => $link] = makeQueueHardeningFixture();

    Http::fake([
        'https://acme.atlassian.net/rest/api/3/search/jql' => Http::response([
            'issues' => [],
            'total' => 0,
        ], 200),
    ]);

    $this->actingAs($owner)
        ->post(route('products.integrations.sync', [$product, 'jira']))
        ->assertRedirect();

    expect(\Illuminate\Support\Facades\DB::table('jobs')->count())->toBe(0)
        ->and($link->fresh()->last_synced_at)->not->toBeNull();
});

test('ops baseline check reports retry_after and failed_jobs count', function () {
    config(['queue.default' => 'database']);

    $exit = Artisan::call('ops:baseline-check');
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('retry_after')
        ->and($output)->toContain('failed_jobs count')
        ->and($output)->toContain('backoff 15/60/120');
});
