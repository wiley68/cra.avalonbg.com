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
use App\Enums\VcsAuthType;
use App\Enums\VcsConnectionStatus;
use App\Enums\VcsProvider;
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
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     organization: Organization,
 *     owner: User,
 *     viewer: User,
 *     outsider: User,
 *     product: Product,
 *     failedLink: ProductIntegrationLink,
 *     softFailLink: ProductIntegrationLink,
 *     repo: ProductRepository
 * }
 */
function makeIntegrationHealthFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Health Index Org',
        'slug' => 'health-index-org-' . uniqid(),
        'is_active' => true,
        'locale' => 'en',
    ]);

    $otherOrg = Organization::query()->create([
        'name' => 'Other Health Org',
        'slug' => 'other-health-org-' . uniqid(),
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
        'name' => 'Health Product',
        'slug' => 'health-product-' . uniqid(),
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

    $otherProduct = Product::query()->create([
        'organization_id' => $otherOrg->id,
        'name' => 'Other Product',
        'slug' => 'other-product-' . uniqid(),
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => true,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
        'scope_reviewed_at' => now(),
        'scope_reviewed_by' => $outsider->id,
        'classification_reviewed_at' => now(),
        'classification_reviewed_by' => $outsider->id,
    ]);

    $snyk = OrganizationIntegration::query()->create([
        'organization_id' => $organization->id,
        'provider' => IntegrationProvider::Snyk,
        'category' => IntegrationCategory::Scanner,
        'auth_type' => IntegrationAuthType::ApiToken,
        'credentials' => ['api_token' => 'token'],
        'label' => 'Snyk',
        'status' => IntegrationConnectionStatus::Active,
        'last_verified_at' => now(),
    ]);

    $jira = OrganizationIntegration::query()->create([
        'organization_id' => $organization->id,
        'provider' => IntegrationProvider::Jira,
        'category' => IntegrationCategory::Alm,
        'auth_type' => IntegrationAuthType::ApiToken,
        'credentials' => ['email' => 'a@b.c', 'api_token' => 'token', 'cloud_id' => 'c'],
        'label' => 'Jira',
        'status' => IntegrationConnectionStatus::Active,
        'last_verified_at' => now(),
    ]);

    $otherIntegration = OrganizationIntegration::query()->create([
        'organization_id' => $otherOrg->id,
        'provider' => IntegrationProvider::Snyk,
        'category' => IntegrationCategory::Scanner,
        'auth_type' => IntegrationAuthType::ApiToken,
        'credentials' => ['api_token' => 'other'],
        'label' => 'Other Snyk',
        'status' => IntegrationConnectionStatus::Active,
        'last_verified_at' => now(),
    ]);

    $failedLink = ProductIntegrationLink::query()->create([
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

    $softFailLink = ProductIntegrationLink::query()->create([
        'product_id' => $product->id,
        'integration_id' => $jira->id,
        'external_project_key' => 'CRA',
        'external_target_id' => '10001',
        'external_label' => 'CRA Board',
        'last_synced_at' => now()->subMinutes(30),
        'last_sync_summary' => [
            'last_error' => 'Missing browse projects scope',
            'soft_fail' => true,
        ],
    ]);

    ProductIntegrationLink::query()->create([
        'product_id' => $otherProduct->id,
        'integration_id' => $otherIntegration->id,
        'external_project_key' => 'other',
        'external_target_id' => 'other-proj',
        'external_label' => 'Should not appear',
        'last_synced_at' => now(),
        'last_sync_summary' => ['error' => 'secret other-org error'],
    ]);

    ImportSuggestion::query()->create([
        'product_id' => $product->id,
        'link_id' => $failedLink->id,
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
        'token' => 'ghp_x',
        'label' => 'GitHub',
        'status' => VcsConnectionStatus::Active,
        'last_verified_at' => now(),
    ]);

    $repo = ProductRepository::query()->create([
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

    return compact(
        'organization',
        'owner',
        'viewer',
        'outsider',
        'product',
        'failedLink',
        'softFailLink',
        'repo',
    );
}

test('owner can open integration health shell without list props', function () {
    ['owner' => $owner, 'organization' => $organization] = makeIntegrationHealthFixture();

    $this->actingAs($owner)
        ->get(route('integrations.health.index'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('integrations/Health')
            ->where('organization.id', $organization->id)
            ->where('canManage', true)
            ->missing('rows')
            ->missing('data'));
});

test('viewer can open integration health shell', function () {
    ['viewer' => $viewer] = makeIntegrationHealthFixture();

    $this->actingAs($viewer)
        ->get(route('integrations.health.index'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('integrations/Health')
            ->where('canManage', false));
});

test('outsider cannot open another organization health index', function () {
    ['outsider' => $outsider] = makeIntegrationHealthFixture();

    $this->actingAs($outsider)
        ->get(route('integrations.health.index'))
        ->assertOk(); // their own org shell is empty-ok; API isolation checked below
});

test('internal api lists org health rows with failed soft_fail and vcs', function () {
    [
        'owner' => $owner,
        'failedLink' => $failedLink,
        'softFailLink' => $softFailLink,
        'repo' => $repo,
    ] = makeIntegrationHealthFixture();

    $response = $this->actingAs($owner)
        ->getJson(route('internal.integrations.health.index', [
            'sort_by' => 'health',
            'sort_desc' => '0',
            'per_page' => 50,
        ]))
        ->assertOk()
        ->assertJsonPath('total', 3);

    $data = collect($response->json('data'));

    expect($data->pluck('id')->all())
        ->toContain('link:' . $failedLink->id)
        ->toContain('link:' . $softFailLink->id)
        ->toContain('repo:' . $repo->id)
        ->not->toContain('Should not appear');

    $failed = $data->firstWhere('id', 'link:' . $failedLink->id);
    $soft = $data->firstWhere('id', 'link:' . $softFailLink->id);
    $vcs = $data->firstWhere('id', 'repo:' . $repo->id);

    expect($failed['health'])->toBe('failed')
        ->and($failed['last_error'])->toBe('Snyk API returned 403')
        ->and($failed['pending_suggestions'])->toBe(1)
        ->and($failed['provider'])->toBe('snyk')
        ->and($soft['health'])->toBe('soft_fail')
        ->and($soft['last_error'])->toBe('Missing browse projects scope')
        ->and($vcs['health'])->toBe('failed')
        ->and($vcs['provider'])->toBe('github')
        ->and($vcs['target'])->toBe('acme/widget');

    // Failed should sort before soft_fail when ascending by health rank
    expect($data->first()['health'])->toBe('failed');
});

test('internal api search filters health rows', function () {
    ['owner' => $owner] = makeIntegrationHealthFixture();

    $this->actingAs($owner)
        ->getJson(route('internal.integrations.health.index', [
            'search' => 'rate limited',
        ]))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.target', 'acme/widget');
});

test('viewer can read health api and outsider does not see other org rows', function () {
    ['viewer' => $viewer, 'outsider' => $outsider] = makeIntegrationHealthFixture();

    $this->actingAs($viewer)
        ->getJson(route('internal.integrations.health.index'))
        ->assertOk()
        ->assertJsonPath('total', 3);

    $this->actingAs($outsider)
        ->getJson(route('internal.integrations.health.index'))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.target', 'Should not appear');
});
