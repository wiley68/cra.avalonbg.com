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
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     organization: Organization,
 *     owner: User,
 *     viewer: User,
 *     product: Product,
 *     jira: OrganizationIntegration,
 *     snyk: OrganizationIntegration,
 *     jiraLink: ProductIntegrationLink,
 *     snykLink: ProductIntegrationLink,
 *     suggestion: ImportSuggestion
 * }
 */
function makeWave2RbacFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Wave2 RBAC Org',
        'slug' => 'wave2-rbac-org-' . uniqid(),
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

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Wave2 Product',
        'slug' => 'wave2-product',
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
            'api_token' => 'jira_token',
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
            'api_token' => 'snyk_token',
        ],
        'label' => 'Snyk',
        'status' => IntegrationConnectionStatus::Active,
        'last_verified_at' => now(),
    ]);

    $jiraLink = ProductIntegrationLink::query()->create([
        'product_id' => $product->id,
        'integration_id' => $jira->id,
        'external_project_key' => 'CRA',
        'external_target_id' => '10001',
        'external_label' => 'CRA',
    ]);

    $snykLink = ProductIntegrationLink::query()->create([
        'product_id' => $product->id,
        'integration_id' => $snyk->id,
        'external_project_key' => 'org-1',
        'external_target_id' => 'proj-1',
        'external_label' => 'App',
        'config' => ['org_id' => 'org-1', 'project_id' => 'proj-1'],
    ]);

    $suggestion = ImportSuggestion::query()->create([
        'product_id' => $product->id,
        'link_id' => $jiraLink->id,
        'kind' => ImportSuggestionKind::Task,
        'external_id' => '20001',
        'title' => 'CRA-1: Task',
        'payload' => ['issue_key' => 'CRA-1'],
        'status' => ImportSuggestionStatus::Pending,
    ]);

    return compact(
        'organization',
        'owner',
        'viewer',
        'product',
        'jira',
        'snyk',
        'jiraLink',
        'snykLink',
        'suggestion',
    );
}

test('read-only user cannot disconnect integration connectors', function () {
    ['viewer' => $viewer, 'jira' => $jira, 'snyk' => $snyk] = makeWave2RbacFixture();

    $this->actingAs($viewer)
        ->delete(route('settings.integrations.providers.destroy', $jira))
        ->assertForbidden();

    $this->actingAs($viewer)
        ->delete(route('settings.integrations.providers.destroy', $snyk))
        ->assertForbidden();

    expect(OrganizationIntegration::query()->count())->toBe(2);
});

test('read-only user cannot sync jira or snyk product links', function () {
    ['viewer' => $viewer, 'product' => $product] = makeWave2RbacFixture();

    Http::fake();

    $this->actingAs($viewer)
        ->post(route('products.integrations.sync', [$product, 'jira']))
        ->assertForbidden();

    $this->actingAs($viewer)
        ->post(route('products.integrations.sync', [$product, 'snyk']))
        ->assertForbidden();

    Http::assertNothingSent();
});

test('read-only user cannot link snyk project', function () {
    ['viewer' => $viewer, 'product' => $product] = makeWave2RbacFixture();

    ProductIntegrationLink::query()
        ->where('product_id', $product->id)
        ->whereHas('integration', fn($q) => $q->where('provider', IntegrationProvider::Snyk))
        ->delete();

    Http::fake();

    $this->actingAs($viewer)
        ->put(route('products.integrations.update', [$product, 'snyk']), [
            'org_id' => 'org-1',
            'project_id' => 'proj-1',
        ])
        ->assertForbidden();

    expect(
        ProductIntegrationLink::query()
            ->where('product_id', $product->id)
            ->whereHas('integration', fn($q) => $q->where('provider', IntegrationProvider::Snyk))
            ->count(),
    )->toBe(0);
});

test('read-only user cannot dismiss import suggestion', function () {
    ['viewer' => $viewer, 'product' => $product, 'suggestion' => $suggestion] = makeWave2RbacFixture();

    $this->actingAs($viewer)
        ->post(route('products.import-suggestions.dismiss', [$product, $suggestion]))
        ->assertForbidden();

    expect($suggestion->fresh()->status)->toBe(ImportSuggestionStatus::Pending);
});
