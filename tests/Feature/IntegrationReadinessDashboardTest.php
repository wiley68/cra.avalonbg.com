<?php

use App\Enums\ClassificationStatus;
use App\Enums\ImportSuggestionKind;
use App\Enums\ImportSuggestionStatus;
use App\Enums\IntegrationAuthType;
use App\Enums\IntegrationCategory;
use App\Enums\IntegrationConnectionStatus;
use App\Enums\IntegrationProvider;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Models\ImportSuggestion;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Models\Product;
use App\Models\ProductIntegrationLink;
use App\Models\Role;
use App\Models\User;
use App\Services\ProductReadinessService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     organization: Organization,
 *     owner: User,
 *     product: Product,
 *     integration: OrganizationIntegration,
 *     link: ProductIntegrationLink
 * }
 */
function makeIntegrationsReadinessFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Integrations Readiness Org',
        'slug' => 'integrations-readiness-'.uniqid(),
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
        'name' => 'Readiness Product',
        'slug' => 'readiness-product',
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
            'api_token' => 'jira_token',
        ],
        'label' => 'Jira',
        'status' => IntegrationConnectionStatus::Active,
        'last_verified_at' => now(),
    ]);

    $link = ProductIntegrationLink::query()->create([
        'product_id' => $product->id,
        'integration_id' => $integration->id,
        'external_project_key' => 'CRA',
        'external_label' => 'CRA',
        'last_synced_at' => now(),
        'last_sync_summary' => [
            'issues_count' => 1,
            'synced_at' => now()->toIso8601String(),
        ],
    ]);

    return compact('organization', 'owner', 'product', 'integration', 'link');
}

test('readiness reports pending import suggestions as a warn gap', function () {
    ['product' => $product, 'link' => $link] = makeIntegrationsReadinessFixture();

    ImportSuggestion::query()->create([
        'product_id' => $product->id,
        'link_id' => $link->id,
        'kind' => ImportSuggestionKind::Task,
        'external_id' => '20001',
        'title' => 'Review MFA',
        'payload' => ['issue_key' => 'CRA-1'],
        'status' => ImportSuggestionStatus::Pending,
    ]);

    $report = app(ProductReadinessService::class)->build($product);
    $section = collect($report['sections'])->firstWhere('key', 'integrations');

    expect($section['status'])->toBe('warn')
        ->and($section['summary'])->toBe('pending_suggestions')
        ->and($section['metrics']['pending_import_suggestions'] ?? null)->toBe(1)
        ->and($report['metrics']['pending_import_suggestions'] ?? null)->toBe(1)
        ->and(collect($report['gaps'])->contains(
            fn (array $gap): bool => $gap['message_key'] === 'products.readiness.gaps.pending_import_suggestions',
        ))->toBeTrue();
});

test('readiness reports failed integration sync as a fail gap', function () {
    ['product' => $product, 'link' => $link] = makeIntegrationsReadinessFixture();

    $link->update([
        'last_sync_summary' => [
            'error' => 'Could not fetch Jira issues for this project.',
            'last_error' => 'Could not fetch Jira issues for this project.',
        ],
    ]);

    $report = app(ProductReadinessService::class)->build($product);
    $section = collect($report['sections'])->firstWhere('key', 'integrations');

    expect($section['status'])->toBe('fail')
        ->and($section['summary'])->toBe('sync_failed')
        ->and($report['metrics']['failed_integration_syncs'] ?? null)->toBe(1)
        ->and(collect($report['gaps'])->contains(
            fn (array $gap): bool => $gap['message_key'] === 'products.readiness.gaps.integration_sync_failed',
        ))->toBeTrue();
});

test('dashboard counts pending import suggestions and failed syncs', function () {
    ['owner' => $owner, 'product' => $product, 'link' => $link] = makeIntegrationsReadinessFixture();

    ImportSuggestion::query()->create([
        'product_id' => $product->id,
        'link_id' => $link->id,
        'kind' => ImportSuggestionKind::Task,
        'external_id' => '20001',
        'title' => 'Review MFA',
        'payload' => ['issue_key' => 'CRA-1'],
        'status' => ImportSuggestionStatus::Pending,
    ]);

    ImportSuggestion::query()->create([
        'product_id' => $product->id,
        'link_id' => $link->id,
        'kind' => ImportSuggestionKind::Task,
        'external_id' => '20002',
        'title' => 'Dismissed item',
        'payload' => ['issue_key' => 'CRA-2'],
        'status' => ImportSuggestionStatus::Dismissed,
    ]);

    $link->update([
        'last_sync_summary' => [
            'error' => 'Sync failed',
            'last_error' => 'Sync failed',
        ],
    ]);

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('dashboard.counts.pending_import_suggestions', 1)
            ->where('dashboard.counts.failed_integration_syncs', 1)
            ->where('dashboard.actions', function ($actions) use ($product) {
                $actions = collect($actions);
                $pending = $actions->firstWhere('key', 'pending_import_suggestions');
                $failed = $actions->firstWhere('key', 'failed_integration_syncs');

                return $pending !== null
                    && $pending['count'] === 1
                    && $pending['severity'] === 'warn'
                    && str_contains((string) $pending['href'], (string) $product->id)
                    && $failed !== null
                    && $failed['count'] === 1
                    && $failed['severity'] === 'fail';
            }));
});

test('healthy integrations section passes without gaps', function () {
    ['product' => $product] = makeIntegrationsReadinessFixture();

    $report = app(ProductReadinessService::class)->build($product);
    $section = collect($report['sections'])->firstWhere('key', 'integrations');

    expect($section['status'])->toBe('pass')
        ->and($section['summary'])->toBe('healthy')
        ->and(collect($report['gaps'])->contains(
            fn (array $gap): bool => $gap['section'] === 'integrations',
        ))->toBeFalse();
});
