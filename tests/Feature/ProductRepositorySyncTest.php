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
 * @return array{owner: User, product: Product, repository: ProductRepository}
 */
function makeSyncFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Sync Org',
        'slug' => 'sync-org',
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
        'name' => 'Sync Product',
        'slug' => 'sync-product',
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
        'token' => 'ghp_sync_token',
        'label' => 'GitHub',
        'status' => VcsConnectionStatus::Active,
        'last_verified_at' => now(),
    ]);

    $repository = ProductRepository::query()->create([
        'product_id' => $product->id,
        'connection_id' => $connection->id,
        'external_id' => '55',
        'full_name' => 'acme/widget',
        'remote_url' => 'https://github.com/acme/widget',
        'default_branch' => 'main',
    ]);

    return compact('owner', 'product', 'repository');
}

test('owner can sync repository tags releases and ci status', function () {
    Storage::fake('local');

    ['owner' => $owner, 'product' => $product, 'repository' => $repository] = makeSyncFixture();

    Http::fake([
        'api.github.com/repos/acme/widget/tags*' => Http::response([
            ['name' => 'v1.2.0', 'commit' => ['sha' => 'aaa']],
            ['name' => 'v1.1.0', 'commit' => ['sha' => 'bbb']],
        ], 200),
        'api.github.com/repos/acme/widget/releases*' => Http::response([
            [
                'tag_name' => 'v1.2.0',
                'name' => 'Release 1.2.0',
                'published_at' => '2026-07-01T00:00:00Z',
                'html_url' => 'https://github.com/acme/widget/releases/tag/v1.2.0',
            ],
        ], 200),
        'api.github.com/repos/acme/widget/actions/runs*' => Http::response([
            'workflow_runs' => [
                [
                    'status' => 'completed',
                    'conclusion' => 'success',
                    'name' => 'CI',
                    'html_url' => 'https://github.com/acme/widget/actions/runs/1',
                    'head_sha' => 'abc123',
                ],
            ],
        ], 200),
    ]);

    $this->actingAs($owner)
        ->post(route('products.repository.sync', $product))
        ->assertRedirect();

    $repository->refresh();
    $run = VcsSyncRun::query()->first();
    $evidence = Evidence::query()->first();

    expect($run)->not->toBeNull()
        ->and($run->summary['error'] ?? null)->toBeNull()
        ->and($run->status)->toBe(VcsSyncRunStatus::Succeeded)
        ->and($run->triggered_by)->toBe($owner->id)
        ->and($run->summary['tags_count'])->toBe(2)
        ->and($run->summary['releases_count'])->toBe(1)
        ->and($run->summary['latest_tag'])->toBe('v1.2.0')
        ->and($run->summary['ci']['conclusion'])->toBe('success')
        ->and($run->summary['evidence_id'])->toBe($evidence->id)
        ->and($repository->last_synced_at)->not->toBeNull()
        ->and($repository->last_sync_summary['tags_count'])->toBe(2)
        ->and(AuditLog::query()->where('event_type', AuditEventType::VcsSyncSucceeded)->count())->toBe(1);

    expect($evidence)->not->toBeNull()
        ->and($evidence->type)->toBe(EvidenceType::IntegrationSnapshot)
        ->and($evidence->product_id)->toBe($product->id)
        ->and($evidence->source)->toBe('github:acme/widget')
        ->and($evidence->checksum_sha256)->toBe(hash('sha256', Storage::disk('local')->get($evidence->storage_path)))
        ->and(AuditLog::query()->where('event_type', AuditEventType::EvidenceCreated)->count())->toBe(1);

    expect(Storage::disk('local')->exists($evidence->storage_path))->toBeTrue();
});

test('failed sync is recorded and audited', function () {
    ['owner' => $owner, 'product' => $product, 'repository' => $repository] = makeSyncFixture();

    Http::fake([
        'api.github.com/repos/acme/widget/tags*' => Http::response(['message' => 'Bad credentials'], 401),
    ]);

    $this->actingAs($owner)
        ->post(route('products.repository.sync', $product))
        ->assertRedirect();

    $run = VcsSyncRun::query()->first();
    $repository->refresh();

    expect($run->status)->toBe(VcsSyncRunStatus::Failed)
        ->and($run->summary['error'])->toContain('Failed to list GitHub tags')
        ->and($repository->last_sync_summary['error'])->toContain('Failed to list GitHub tags')
        ->and(AuditLog::query()->where('event_type', AuditEventType::VcsSyncFailed)->count())->toBe(1)
        ->and(Evidence::query()->count())->toBe(0);
});

test('sync without linked repository returns not found', function () {
    ['owner' => $owner, 'product' => $product, 'repository' => $repository] = makeSyncFixture();
    $repository->delete();

    $this->actingAs($owner)
        ->post(route('products.repository.sync', $product))
        ->assertNotFound();
});
