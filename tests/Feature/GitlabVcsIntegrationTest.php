<?php

use App\Enums\AuditEventType;
use App\Enums\ClassificationStatus;
use App\Enums\EvidenceType;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\VcsAuthType;
use App\Enums\VcsConnectionStatus;
use App\Enums\VcsProvider;
use App\Enums\VcsSyncRunStatus;
use App\Enums\VcsSyncSchedule;
use App\Models\AuditLog;
use App\Models\Evidence;
use App\Models\Organization;
use App\Models\OrganizationVcsConnection;
use App\Models\Product;
use App\Models\ProductRepository;
use App\Models\Role;
use App\Models\User;
use App\Models\VcsSyncRun;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User}
 */
function makeGitlabIntegrationsFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'GitLab Org',
        'slug' => 'gitlab-org',
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

    return compact('organization', 'owner');
}

/**
 * @return array{owner: User, product: Product, connection: OrganizationVcsConnection, repository: ProductRepository}
 */
function makeGitlabSyncFixture(): array
{
    ['organization' => $organization, 'owner' => $owner] = makeGitlabIntegrationsFixture();

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'GitLab Product',
        'slug' => 'gitlab-product',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => false,
        'scope_status' => ScopeStatus::InsufficientInformation,
        'classification_status' => ClassificationStatus::Unclassified,
    ]);

    $connection = OrganizationVcsConnection::query()->create([
        'organization_id' => $organization->id,
        'provider' => VcsProvider::Gitlab,
        'auth_type' => VcsAuthType::Pat,
        'token' => 'glpat_sync_token',
        'label' => 'GitLab',
        'status' => VcsConnectionStatus::Active,
        'sync_schedule' => VcsSyncSchedule::Off,
        'last_verified_at' => now(),
    ]);

    $repository = ProductRepository::query()->create([
        'product_id' => $product->id,
        'connection_id' => $connection->id,
        'external_id' => '77',
        'full_name' => 'acme/widget',
        'remote_url' => 'https://gitlab.com/acme/widget',
        'default_branch' => 'main',
    ]);

    return compact('owner', 'product', 'connection', 'repository');
}

test('owner can connect gitlab with valid pat', function () {
    ['organization' => $organization, 'owner' => $owner] = makeGitlabIntegrationsFixture();

    Http::fake([
        'gitlab.com/api/v4/user' => Http::response(['username' => 'octocat'], 200),
    ]);

    $this->actingAs($owner)
        ->post(route('settings.integrations.gitlab.store'), [
            'token' => 'glpat_valid_token_value',
            'label' => 'Work GitLab',
        ])
        ->assertRedirect();

    $connection = OrganizationVcsConnection::query()->first();

    expect($connection)->not->toBeNull()
        ->and($connection->organization_id)->toBe($organization->id)
        ->and($connection->provider)->toBe(VcsProvider::Gitlab)
        ->and($connection->token)->toBe('glpat_valid_token_value')
        ->and($connection->label)->toBe('Work GitLab')
        ->and(AuditLog::query()->where('event_type', AuditEventType::VcsConnectionCreated)->count())->toBe(1);
});

test('invalid gitlab token is rejected', function () {
    ['owner' => $owner] = makeGitlabIntegrationsFixture();

    Http::fake([
        'gitlab.com/api/v4/user' => Http::response(['message' => '401 Unauthorized'], 401),
    ]);

    $this->actingAs($owner)
        ->from(route('settings.integrations.edit'))
        ->post(route('settings.integrations.gitlab.store'), [
            'token' => 'glpat_bad',
        ])
        ->assertRedirect(route('settings.integrations.edit'))
        ->assertSessionHasErrors('token');

    expect(OrganizationVcsConnection::query()->count())->toBe(0);
});

test('owner can link gitlab repository from nested path', function () {
    ['owner' => $owner, 'product' => $product, 'connection' => $connection] = makeGitlabSyncFixture();
    $product->repository?->delete();

    Http::fake([
        'gitlab.com/api/v4/projects/*' => Http::response([
            'id' => 9001,
            'path_with_namespace' => 'acme/group/widget',
            'web_url' => 'https://gitlab.com/acme/group/widget',
            'default_branch' => 'main',
        ], 200),
    ]);

    $this->actingAs($owner)
        ->post(route('products.repository.store', $product), [
            'connection_id' => $connection->id,
            'repository' => 'https://gitlab.com/acme/group/widget.git',
        ])
        ->assertRedirect();

    $repository = ProductRepository::query()->first();

    expect($repository)->not->toBeNull()
        ->and($repository->full_name)->toBe('acme/group/widget')
        ->and($repository->external_id)->toBe('9001')
        ->and($repository->remote_url)->toBe('https://gitlab.com/acme/group/widget');
});

test('owner can sync gitlab repository tags releases and pipelines', function () {
    Storage::fake('local');

    ['owner' => $owner, 'product' => $product, 'repository' => $repository] = makeGitlabSyncFixture();

    Http::fake([
        'gitlab.com/api/v4/projects/*/repository/tags*' => Http::response([
            ['name' => 'v1.0.0', 'commit' => ['id' => 'abc']],
        ], 200),
        'gitlab.com/api/v4/projects/*/releases*' => Http::response([
            [
                'tag_name' => 'v1.0.0',
                'name' => 'Release 1.0.0',
                'description' => 'Notes',
                'released_at' => '2026-07-01T00:00:00Z',
                '_links' => ['self' => 'https://gitlab.com/acme/widget/-/releases/v1.0.0'],
            ],
        ], 200),
        'gitlab.com/api/v4/projects/*/pipelines*' => Http::response([
            [
                'status' => 'success',
                'source' => 'push',
                'web_url' => 'https://gitlab.com/acme/widget/-/pipelines/1',
                'sha' => 'abc123',
            ],
        ], 200),
        'gitlab.com/api/v4/projects/*/vulnerability_findings*' => Http::response([], 200),
    ]);

    $this->actingAs($owner)
        ->post(route('products.repository.sync', $product))
        ->assertRedirect();

    $run = VcsSyncRun::query()->first();
    $evidence = Evidence::query()->first();

    expect($run->status)->toBe(VcsSyncRunStatus::Succeeded)
        ->and($run->summary['tags_count'])->toBe(1)
        ->and($run->summary['releases_count'])->toBe(1)
        ->and($run->summary['ci']['conclusion'])->toBe('success')
        ->and($evidence->type)->toBe(EvidenceType::IntegrationSnapshot)
        ->and($evidence->source)->toBe('gitlab:acme/widget')
        ->and($repository->fresh()->last_synced_at)->not->toBeNull();
});

test('gitlab vulnerability findings 403 soft-fails during sync', function () {
    Storage::fake('local');

    ['owner' => $owner, 'product' => $product] = makeGitlabSyncFixture();

    Http::fake([
        'gitlab.com/api/v4/projects/*/repository/tags*' => Http::response([], 200),
        'gitlab.com/api/v4/projects/*/releases*' => Http::response([], 200),
        'gitlab.com/api/v4/projects/*/pipelines*' => Http::response([], 200),
        'gitlab.com/api/v4/projects/*/vulnerability_findings*' => Http::response(['message' => 'Forbidden'], 403),
    ]);

    $this->actingAs($owner)
        ->post(route('products.repository.sync', $product))
        ->assertRedirect();

    expect(VcsSyncRun::query()->first()->status)->toBe(VcsSyncRunStatus::Succeeded)
        ->and($product->fresh()->repository->last_sync_summary['alerts_count'])->toBe(0);
});
