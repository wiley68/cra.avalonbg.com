<?php

use App\Enums\AuditEventType;
use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ProductVersionState;
use App\Enums\ScopeStatus;
use App\Enums\SupportStatus;
use App\Enums\VcsAuthType;
use App\Enums\VcsConnectionStatus;
use App\Enums\VcsProvider;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationVcsConnection;
use App\Models\Product;
use App\Models\ProductRepository;
use App\Models\ProductVersion;
use App\Models\Role;
use App\Models\User;
use App\Services\MergedPrSummaryService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User, product: Product, version: ProductVersion}
 */
function makeMergedPrFixture(?string $releaseDate = '2026-07-15'): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Merged PR Org',
        'slug' => 'merged-pr-org-' . uniqid(),
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
        'name' => 'Merged PR Product',
        'slug' => 'merged-pr-product-' . uniqid(),
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    $version = ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '2.1.0',
        'release_date' => $releaseDate,
        'state' => ProductVersionState::Released,
        'support_status' => SupportStatus::Supported,
    ]);

    return compact('organization', 'owner', 'product', 'version');
}

function makeMergedPrViewer(Organization $organization): User
{
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

    return $viewer;
}

function linkGithubRepo(Product $product): ProductRepository
{
    $connection = OrganizationVcsConnection::query()->create([
        'organization_id' => $product->organization_id,
        'provider' => VcsProvider::Github,
        'auth_type' => VcsAuthType::Pat,
        'token' => 'ghp_merged_pr_token',
        'label' => 'GitHub',
        'status' => VcsConnectionStatus::Active,
        'last_verified_at' => now(),
    ]);

    return ProductRepository::query()->create([
        'product_id' => $product->id,
        'connection_id' => $connection->id,
        'external_id' => '99',
        'full_name' => 'acme/widget',
        'remote_url' => 'https://github.com/acme/widget',
        'default_branch' => 'main',
    ]);
}

function linkGitlabRepo(Product $product): ProductRepository
{
    $connection = OrganizationVcsConnection::query()->create([
        'organization_id' => $product->organization_id,
        'provider' => VcsProvider::Gitlab,
        'auth_type' => VcsAuthType::Pat,
        'token' => 'glpat_merged_mr_token',
        'label' => 'GitLab',
        'status' => VcsConnectionStatus::Active,
        'last_verified_at' => now(),
    ]);

    return ProductRepository::query()->create([
        'product_id' => $product->id,
        'connection_id' => $connection->id,
        'external_id' => '88',
        'full_name' => 'acme/widget',
        'remote_url' => 'https://gitlab.com/acme/widget',
        'default_branch' => 'main',
    ]);
}

test('release window is release_date plus or minus 14 days', function () {
    ['version' => $version] = makeMergedPrFixture('2026-07-15');

    $window = app(MergedPrSummaryService::class)->resolveWindow($version);

    expect($window['mode'])->toBe('release_window')
        ->and($window['anchor_date'])->toBe('2026-07-15')
        ->and($window['from'])->toBe('2026-07-01')
        ->and($window['to'])->toBe('2026-07-29');
});

test('missing release_date uses rolling last 30 days', function () {
    ['version' => $version] = makeMergedPrFixture(null);

    $window = app(MergedPrSummaryService::class)->resolveWindow(
        $version,
        now()->startOfDay(),
    );

    expect($window['mode'])->toBe('rolling_30_days')
        ->and($window['anchor_date'])->toBeNull()
        ->and($window['from'])->toBe(now()->startOfDay()->subDays(30)->toDateString())
        ->and($window['to'])->toBe(now()->toDateString());
});

test('version show lists merged prs from github search without creating entities', function () {
    Cache::flush();
    ['owner' => $owner, 'product' => $product, 'version' => $version] = makeMergedPrFixture();
    linkGithubRepo($product);

    Http::fake([
        'api.github.com/search/issues*' => Http::response([
            'total_count' => 1,
            'incomplete_results' => false,
            'items' => [
                [
                    'number' => 42,
                    'title' => 'Harden auth cookies',
                    'html_url' => 'https://github.com/acme/widget/pull/42',
                    'closed_at' => '2026-07-14T10:00:00Z',
                    'user' => ['login' => 'alice'],
                ],
            ],
        ], 200),
    ]);

    $this->actingAs($owner)
        ->get(route('products.versions.show', [$product, $version]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/versions/Show')
            ->where('mergedPrSummary.available', true)
            ->where('mergedPrSummary.provider', 'github')
            ->where('mergedPrSummary.count', 1)
            ->where('mergedPrSummary.window.mode', 'release_window')
            ->where('mergedPrSummary.prs.0.number', 42)
            ->where('mergedPrSummary.prs.0.title', 'Harden auth cookies')
            ->where('canManage', true));

    Http::assertSent(fn($request) => str_contains($request->url(), 'api.github.com/search/issues')
        && str_contains(urldecode((string) $request['q']), 'repo:acme/widget')
        && str_contains(urldecode((string) $request['q']), 'is:merged')
        && str_contains(urldecode((string) $request['q']), 'merged:2026-07-01..2026-07-29'));

    expect(\App\Models\Task::query()->count())->toBe(0)
        ->and(\App\Models\Evidence::query()->count())->toBe(0);

    expect(AuditLog::query()
        ->whereIn('event_type', [
            AuditEventType::MergedPrSummaryRefreshSucceeded->value,
            AuditEventType::MergedPrSummaryRefreshFailed->value,
        ])
        ->count())->toBe(0);
});

test('merged pr summary is cached for fifteen minutes', function () {
    Cache::flush();
    ['owner' => $owner, 'product' => $product, 'version' => $version] = makeMergedPrFixture();
    linkGithubRepo($product);

    Http::fake([
        'api.github.com/search/issues*' => Http::response([
            'total_count' => 0,
            'incomplete_results' => false,
            'items' => [],
        ], 200),
    ]);

    $this->actingAs($owner)
        ->get(route('products.versions.show', [$product, $version]))
        ->assertOk();

    $this->actingAs($owner)
        ->get(route('products.versions.show', [$product, $version]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->where('mergedPrSummary.from_cache', true));

    Http::assertSentCount(1);
});

test('owner can refresh merged pr summary and viewer cannot', function () {
    Cache::flush();
    [
        'organization' => $organization,
        'owner' => $owner,
        'product' => $product,
        'version' => $version,
    ] = makeMergedPrFixture();
    $viewer = makeMergedPrViewer($organization);
    linkGithubRepo($product);

    Http::fake([
        'api.github.com/search/issues*' => Http::response([
            'total_count' => 0,
            'incomplete_results' => false,
            'items' => [],
        ], 200),
    ]);

    $this->actingAs($owner)
        ->post(route('products.versions.merged-prs.refresh', [$product, $version]))
        ->assertRedirect(route('products.versions.show', [$product, $version]));

    $successLog = AuditLog::query()
        ->where('event_type', AuditEventType::MergedPrSummaryRefreshSucceeded->value)
        ->where('product_id', $product->id)
        ->first();

    expect($successLog)->not->toBeNull()
        ->and($successLog->is_success)->toBeTrue()
        ->and($successLog->user_id)->toBe($owner->id)
        ->and($successLog->description)->toContain('"provider"')
        ->and($successLog->description)->toContain('github')
        ->and($successLog->description)->not->toContain('ghp_merged_pr_token');

    $this->actingAs($viewer)
        ->get(route('products.versions.show', [$product, $version]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->where('canManage', false)
            ->where('mergedPrSummary.available', true));

    $auditCountBeforeForbidden = AuditLog::query()
        ->whereIn('event_type', [
            AuditEventType::MergedPrSummaryRefreshSucceeded->value,
            AuditEventType::MergedPrSummaryRefreshFailed->value,
        ])
        ->count();

    $this->actingAs($viewer)
        ->post(route('products.versions.merged-prs.refresh', [$product, $version]))
        ->assertForbidden();

    expect(AuditLog::query()
        ->whereIn('event_type', [
            AuditEventType::MergedPrSummaryRefreshSucceeded->value,
            AuditEventType::MergedPrSummaryRefreshFailed->value,
        ])
        ->count())->toBe($auditCountBeforeForbidden);
});

test('failed merged pr refresh writes failed audit without secrets', function () {
    Cache::flush();
    ['owner' => $owner, 'product' => $product, 'version' => $version] = makeMergedPrFixture();
    linkGithubRepo($product);

    Http::fake([
        'api.github.com/search/issues*' => Http::response(['message' => 'Server Error'], 500),
    ]);

    $this->actingAs($owner)
        ->post(route('products.versions.merged-prs.refresh', [$product, $version]))
        ->assertRedirect(route('products.versions.show', [$product, $version]));

    $failedLog = AuditLog::query()
        ->where('event_type', AuditEventType::MergedPrSummaryRefreshFailed->value)
        ->where('product_id', $product->id)
        ->first();

    expect($failedLog)->not->toBeNull()
        ->and($failedLog->is_success)->toBeFalse()
        ->and($failedLog->description)->toContain('"reason"')
        ->and($failedLog->description)->toContain('github')
        ->and($failedLog->description)->not->toContain('ghp_merged_pr_token');
});

test('version show lists merged mrs from gitlab without creating entities', function () {
    Cache::flush();
    ['owner' => $owner, 'product' => $product, 'version' => $version] = makeMergedPrFixture();
    linkGitlabRepo($product);

    Http::fake([
        'gitlab.com/api/v4/projects/*' => Http::response([
            [
                'iid' => 7,
                'title' => 'Harden auth cookies',
                'web_url' => 'https://gitlab.com/acme/widget/-/merge_requests/7',
                'merged_at' => '2026-07-14T10:00:00.000Z',
                'author' => ['username' => 'alice'],
            ],
            [
                'iid' => 8,
                'title' => 'Out of window',
                'web_url' => 'https://gitlab.com/acme/widget/-/merge_requests/8',
                'merged_at' => '2026-06-01T10:00:00.000Z',
                'author' => ['username' => 'bob'],
            ],
        ], 200),
    ]);

    $this->actingAs($owner)
        ->get(route('products.versions.show', [$product, $version]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/versions/Show')
            ->where('mergedPrSummary.available', true)
            ->where('mergedPrSummary.provider', 'gitlab')
            ->where('mergedPrSummary.count', 1)
            ->where('mergedPrSummary.prs.0.number', 7)
            ->where('mergedPrSummary.prs.0.title', 'Harden auth cookies')
            ->where('mergedPrSummary.prs.0.user_login', 'alice'));

    Http::assertSent(fn($request) => str_contains($request->url(), 'gitlab.com/api/v4/projects/')
        && str_contains($request->url(), '/merge_requests')
        && $request['state'] === 'merged'
        && str_starts_with((string) $request['updated_after'], '2026-07-01')
        && str_starts_with((string) $request['updated_before'], '2026-07-29'));

    expect(\App\Models\Task::query()->count())->toBe(0)
        ->and(\App\Models\Evidence::query()->count())->toBe(0);
});

test('gitlab merged mr refresh audits provider without secrets', function () {
    Cache::flush();
    ['owner' => $owner, 'product' => $product, 'version' => $version] = makeMergedPrFixture();
    linkGitlabRepo($product);

    Http::fake([
        'gitlab.com/api/v4/projects/*' => Http::response([], 200),
    ]);

    $this->actingAs($owner)
        ->post(route('products.versions.merged-prs.refresh', [$product, $version]))
        ->assertRedirect(route('products.versions.show', [$product, $version]));

    $successLog = AuditLog::query()
        ->where('event_type', AuditEventType::MergedPrSummaryRefreshSucceeded->value)
        ->where('product_id', $product->id)
        ->first();

    expect($successLog)->not->toBeNull()
        ->and($successLog->description)->toContain('gitlab')
        ->and($successLog->description)->not->toContain('glpat_merged_mr_token');
});

test('version show without repository explains why summary is unavailable', function () {
    ['owner' => $owner, 'product' => $product, 'version' => $version] = makeMergedPrFixture();

    $this->actingAs($owner)
        ->get(route('products.versions.show', [$product, $version]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->where('mergedPrSummary.available', false)
            ->where('mergedPrSummary.reason', 'no_repository')
            ->where('mergedPrSummary.prs', []));

    Http::assertNothingSent();
});
