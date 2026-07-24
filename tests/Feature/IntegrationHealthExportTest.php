<?php

use App\Enums\AuditEventType;
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
use App\Enums\VcsAuthType;
use App\Enums\VcsConnectionStatus;
use App\Enums\VcsProvider;
use App\Models\AuditLog;
use App\Models\ImportSuggestion;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Models\OrganizationVcsConnection;
use App\Models\Product;
use App\Models\ProductIntegrationLink;
use App\Models\ProductRepository;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     organization: Organization,
 *     owner: User,
 *     viewer: User,
 *     outsider: User,
 *     product: Product
 * }
 */
function makeIntegrationHealthExportFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Health Export Org',
        'slug' => 'health-export-org-' . uniqid(),
        'is_active' => true,
        'locale' => 'en',
    ]);

    $otherOrg = Organization::query()->create([
        'name' => 'Other Export Org',
        'slug' => 'other-export-org-' . uniqid(),
        'is_active' => true,
        'locale' => 'en',
    ]);

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $viewer = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $outsider = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $ownerRole = Role::query()->where('slug', 'organization_owner')->firstOrFail();
    $viewerRole = Role::query()->where('slug', 'read_only')->firstOrFail();

    $organization->users()->attach($owner->id, [
        'role_id' => $ownerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $organization->users()->attach($viewer->id, [
        'role_id' => $viewerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $otherOrg->users()->attach($outsider->id, [
        'role_id' => $ownerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Health Export Product',
        'slug' => 'health-export-product-' . uniqid(),
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => true,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
        'scope_reviewed_at' => now(),
        'scope_reviewed_by' => $owner->id,
        'classification_reviewed_at' => now(),
        'classification_reviewed_by' => $owner->id,
    ]);

    $snyk = OrganizationIntegration::query()->create([
        'organization_id' => $organization->id,
        'provider' => IntegrationProvider::Snyk,
        'category' => IntegrationCategory::Scanner,
        'auth_type' => IntegrationAuthType::ApiToken,
        'credentials' => ['api_token' => 'secret-token-value'],
        'label' => 'Snyk',
        'status' => IntegrationConnectionStatus::Active,
        'last_verified_at' => now(),
    ]);

    $link = ProductIntegrationLink::query()->create([
        'product_id' => $product->id,
        'integration_id' => $snyk->id,
        'external_project_key' => 'org-1',
        'external_target_id' => 'proj-1',
        'external_label' => 'Acme App',
        'last_synced_at' => now()->subHour(),
        'last_sync_summary' => [
            'error' => 'Snyk API returned 403',
            'soft_fail' => false,
        ],
    ]);

    ImportSuggestion::query()->create([
        'product_id' => $product->id,
        'link_id' => $link->id,
        'kind' => ImportSuggestionKind::Vulnerability,
        'external_id' => 'issue-1',
        'title' => 'Pending finding',
        'payload' => ['title' => 'Pending finding'],
        'status' => ImportSuggestionStatus::Pending,
    ]);

    $vcs = OrganizationVcsConnection::query()->create([
        'organization_id' => $organization->id,
        'provider' => VcsProvider::Github,
        'auth_type' => VcsAuthType::Pat,
        'token' => 'ghp_secret_token',
        'label' => 'GitHub',
        'status' => VcsConnectionStatus::Active,
        'last_verified_at' => now(),
    ]);

    ProductRepository::query()->create([
        'product_id' => $product->id,
        'connection_id' => $vcs->id,
        'external_id' => '99',
        'full_name' => 'acme/widget',
        'remote_url' => 'https://github.com/acme/widget',
        'default_branch' => 'main',
        'last_synced_at' => now()->subDay(),
        'last_sync_summary' => [
            'error' => 'GitHub rate limited',
        ],
    ]);

    return compact('organization', 'owner', 'viewer', 'outsider', 'product');
}

test('owner can export integration health as markdown without secrets', function () {
    ['owner' => $owner, 'organization' => $organization] = makeIntegrationHealthExportFixture();

    $response = $this->actingAs($owner)
        ->get(route('integrations.health.export', ['format' => 'markdown']))
        ->assertOk();

    expect($response->headers->get('content-type'))->toContain('text/markdown');

    $body = $response->getContent();

    expect($body)->toContain('Snyk API returned 403')
        ->and($body)->toContain('GitHub rate limited')
        ->and($body)->toContain('Acme App')
        ->and($body)->toContain('acme/widget')
        ->and($body)->toContain($organization->name)
        ->and($body)->not->toContain('secret-token-value')
        ->and($body)->not->toContain('ghp_secret_token');

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::IntegrationHealthExported->value)
        ->where('organization_id', $organization->id)
        ->count())->toBe(1);
});

test('owner can export integration health as pdf', function () {
    ['owner' => $owner] = makeIntegrationHealthExportFixture();

    $response = $this->actingAs($owner)
        ->get(route('integrations.health.export', ['format' => 'pdf']))
        ->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and($response->getContent())->toStartWith('%PDF');

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::IntegrationHealthExported->value)
        ->count())->toBe(1);
});

test('viewer can export integration health markdown', function () {
    ['viewer' => $viewer] = makeIntegrationHealthExportFixture();

    $this->actingAs($viewer)
        ->get(route('integrations.health.export', ['format' => 'markdown']))
        ->assertOk();
});

test('invalid health export format is not found', function () {
    ['owner' => $owner] = makeIntegrationHealthExportFixture();

    $this->actingAs($owner)
        ->get('/integrations/health/export/html')
        ->assertNotFound();
});
