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
        'api.github.com/repos/acme/widget/dependabot/alerts*' => Http::response([], 200),
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

test('successful sync only issues get requests to github', function () {
    Storage::fake('local');

    ['owner' => $owner, 'product' => $product] = makeSyncFixture();

    Http::fake([
        'api.github.com/repos/acme/widget/tags*' => Http::response([], 200),
        'api.github.com/repos/acme/widget/releases*' => Http::response([], 200),
        'api.github.com/repos/acme/widget/actions/runs*' => Http::response([
            'workflow_runs' => [],
        ], 200),
        'api.github.com/repos/acme/widget/dependabot/alerts*' => Http::response([], 200),
    ]);

    $this->actingAs($owner)
        ->post(route('products.repository.sync', $product))
        ->assertRedirect();

    Http::assertSentCount(4);

    Http::assertSent(function ($request) {
        return $request->method() === 'GET'
            && str_contains($request->url(), 'api.github.com/repos/acme/widget/')
            && $request->hasHeader('Authorization', 'Bearer ghp_sync_token');
    });

    Http::assertNotSent(fn($request) => $request->method() !== 'GET');

    expect(VcsSyncRun::query()->first()->status)->toBe(VcsSyncRunStatus::Succeeded)
        ->and(VcsSyncRun::query()->first()->summary['ci']['status'])->toBe('unknown')
        ->and(VcsSyncRun::query()->first()->summary['ci']['conclusion'])->toBeNull();
});

test('failed releases request marks sync as failed', function () {
    ['owner' => $owner, 'product' => $product] = makeSyncFixture();

    Http::fake([
        'api.github.com/repos/acme/widget/tags*' => Http::response([
            ['name' => 'v1.0.0', 'commit' => ['sha' => 'aaa']],
        ], 200),
        'api.github.com/repos/acme/widget/releases*' => Http::response(['message' => 'Server Error'], 500),
    ]);

    $this->actingAs($owner)
        ->post(route('products.repository.sync', $product))
        ->assertRedirect();

    $run = VcsSyncRun::query()->first();

    expect($run->status)->toBe(VcsSyncRunStatus::Failed)
        ->and($run->summary['error'])->toContain('Failed to list GitHub releases')
        ->and(AuditLog::query()->where('event_type', AuditEventType::VcsSyncFailed)->count())->toBe(1)
        ->and(Evidence::query()->count())->toBe(0);
});

test('failed ci status request marks sync as failed', function () {
    ['owner' => $owner, 'product' => $product] = makeSyncFixture();

    Http::fake([
        'api.github.com/repos/acme/widget/tags*' => Http::response([], 200),
        'api.github.com/repos/acme/widget/releases*' => Http::response([], 200),
        'api.github.com/repos/acme/widget/actions/runs*' => Http::response(['message' => 'Forbidden'], 403),
    ]);

    $this->actingAs($owner)
        ->post(route('products.repository.sync', $product))
        ->assertRedirect();

    expect(VcsSyncRun::query()->first()->status)->toBe(VcsSyncRunStatus::Failed)
        ->and(VcsSyncRun::query()->first()->summary['error'])->toContain('Failed to fetch GitHub Actions status')
        ->and(Evidence::query()->count())->toBe(0);
});

test('read-only user cannot sync repository', function () {
    ['owner' => $owner, 'product' => $product] = makeSyncFixture();

    $organization = $product->organization;
    $viewer = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);
    $role = Role::query()->where('slug', 'read_only')->firstOrFail();
    $organization->users()->attach($viewer->id, [
        'role_id' => $role->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Http::fake();

    $this->actingAs($viewer)
        ->post(route('products.repository.sync', $product))
        ->assertForbidden();

    Http::assertNothingSent();
    expect(VcsSyncRun::query()->count())->toBe(0);
});

test('sync audit details do not include the pat token', function () {
    Storage::fake('local');

    ['owner' => $owner, 'product' => $product] = makeSyncFixture();

    Http::fake([
        'api.github.com/repos/acme/widget/tags*' => Http::response([], 200),
        'api.github.com/repos/acme/widget/releases*' => Http::response([], 200),
        'api.github.com/repos/acme/widget/actions/runs*' => Http::response([
            'workflow_runs' => [],
        ], 200),
        'api.github.com/repos/acme/widget/dependabot/alerts*' => Http::response([], 200),
    ]);

    $this->actingAs($owner)
        ->post(route('products.repository.sync', $product))
        ->assertRedirect();

    $audit = AuditLog::query()
        ->where('event_type', AuditEventType::VcsSyncSucceeded)
        ->first();

    expect($audit)->not->toBeNull()
        ->and(json_encode($audit->details))->not->toContain('ghp_sync_token')
        ->and($audit->description)->not->toContain('ghp_sync_token');
});

test('sync with github app mints installation token then reads api', function () {
    Storage::fake('local');

    ['owner' => $owner, 'product' => $product, 'repository' => $repository] = makeSyncFixture();

    $repository->connection->update([
        'auth_type' => VcsAuthType::GithubApp,
        'token' => null,
        'github_app_id' => '300',
        'github_installation_id' => '400',
        'github_private_key' => makeGithubAppPrivateKeyPem(),
    ]);

    Http::fake([
        'api.github.com/app/installations/400/access_tokens' => Http::response(['token' => 'ghs_sync_token'], 201),
        'api.github.com/repos/acme/widget/tags*' => Http::response([
            ['name' => 'v9.0.0', 'commit' => ['sha' => 'zzz']],
        ], 200),
        'api.github.com/repos/acme/widget/releases*' => Http::response([], 200),
        'api.github.com/repos/acme/widget/actions/runs*' => Http::response([
            'workflow_runs' => [],
        ], 200),
        'api.github.com/repos/acme/widget/dependabot/alerts*' => Http::response([], 200),
    ]);

    $this->actingAs($owner)
        ->post(route('products.repository.sync', $product))
        ->assertRedirect();

    $run = VcsSyncRun::query()->latest('id')->first();

    expect($run)->not->toBeNull()
        ->and($run->status)->toBe(VcsSyncRunStatus::Succeeded)
        ->and($run->summary['latest_tag'])->toBe('v9.0.0');

    Http::assertSent(fn($request) => $request->method() === 'POST'
        && str_contains($request->url(), '/app/installations/400/access_tokens'));
    Http::assertSent(fn($request) => $request->method() === 'GET'
        && str_contains($request->url(), 'api.github.com/repos/acme/widget/tags')
        && $request->hasHeader('Authorization', 'Bearer ghs_sync_token'));
});
