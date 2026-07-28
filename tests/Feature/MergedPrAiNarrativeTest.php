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
use App\Models\Evidence;
use App\Models\Organization;
use App\Models\OrganizationVcsConnection;
use App\Models\Product;
use App\Models\ProductRepository;
use App\Models\ProductVersion;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\Ai\AiMergedPrNarrativeParser;
use App\Services\AiAssistantService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User, product: Product, version: ProductVersion}
 */
function makeMergedPrAiNarrativeFixture(?string $releaseDate = '2026-07-15'): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Merged PR AI Org',
        'slug' => 'merged-pr-ai-org-' . uniqid(),
        'is_active' => true,
        'subscription_plan' => 'small',
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
        'name' => 'Merged PR AI Product',
        'slug' => 'merged-pr-ai-product-' . uniqid(),
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

function makeMergedPrAiNarrativeViewer(Organization $organization): User
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

function linkGithubRepoForAiNarrative(Product $product): ProductRepository
{
    $connection = OrganizationVcsConnection::query()->create([
        'organization_id' => $product->organization_id,
        'provider' => VcsProvider::Github,
        'auth_type' => VcsAuthType::Pat,
        'token' => 'ghp_merged_pr_ai_token',
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

function fakeGithubMergedPrsForAiNarrative(): void
{
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
}

test('AiMergedPrNarrativeParser extracts summary_markdown', function () {
    $parsed = AiMergedPrNarrativeParser::parse(<<<'JSON'
{
  "summary_markdown": "## Release narrative\n\nStub themes with human review.",
  "human_review_required": true,
  "disclaimer": "Draft only"
}
JSON);

    expect($parsed)->not->toBeNull()
        ->and($parsed['summary_markdown'])->toContain('Stub themes')
        ->and($parsed['human_review_required'])->toBeTrue();
});

test('suggestMergedPrNarrativeDraft returns stub draft without creating entities', function () {
    config(['ai.enabled' => true, 'ai.provider' => 'stub']);
    Cache::flush();

    ['owner' => $owner, 'product' => $product, 'version' => $version] = makeMergedPrAiNarrativeFixture();
    linkGithubRepoForAiNarrative($product);
    fakeGithubMergedPrsForAiNarrative();

    $evidenceBefore = Evidence::query()->count();
    $taskBefore = Task::query()->count();

    $result = app(AiAssistantService::class)->suggestMergedPrNarrativeDraft(
        $product,
        $version,
        $owner,
        null,
        'en',
    );

    expect($result['draft']['human_review_required'])->toBeTrue()
        ->and($result['draft']['summary_markdown'])->toContain('stub narrative')
        ->and($result['draft']['summary_markdown'])->toContain('Nothing is saved automatically')
        ->and(Evidence::query()->count())->toBe($evidenceBefore)
        ->and(Task::query()->count())->toBe($taskBefore);

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::AiMergedPrNarrativeSuggested->value)
        ->where('product_id', $product->id)
        ->exists())->toBeTrue();
});

test('HTTP AI merged-PR narrative returns JSON and does not create evidence', function () {
    config(['ai.enabled' => true, 'ai.provider' => 'stub']);
    Cache::flush();

    ['owner' => $owner, 'product' => $product, 'version' => $version] = makeMergedPrAiNarrativeFixture();
    linkGithubRepoForAiNarrative($product);
    fakeGithubMergedPrsForAiNarrative();

    $evidenceBefore = Evidence::query()->count();

    $this->actingAs($owner)
        ->postJson(route('products.versions.merged-prs.ai-narrative', [$product, $version]))
        ->assertOk()
        ->assertJsonPath('human_review_required', true)
        ->assertJsonPath('summary_markdown', fn($value) => is_string($value) && str_contains($value, 'stub narrative'))
        ->assertJsonPath('provider', 'stub');

    expect(Evidence::query()->count())->toBe($evidenceBefore);

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::AiMergedPrNarrativeSuggested->value)
        ->exists())->toBeTrue();
});

test('viewer cannot request AI merged-PR narrative', function () {
    config(['ai.enabled' => true, 'ai.provider' => 'stub']);
    Cache::flush();

    ['organization' => $organization, 'product' => $product, 'version' => $version] = makeMergedPrAiNarrativeFixture();
    linkGithubRepoForAiNarrative($product);
    fakeGithubMergedPrsForAiNarrative();
    $viewer = makeMergedPrAiNarrativeViewer($organization);

    $this->actingAs($viewer)
        ->postJson(route('products.versions.merged-prs.ai-narrative', [$product, $version]))
        ->assertForbidden();
});

test('AI merged-PR narrative fails when summary is unavailable', function () {
    config(['ai.enabled' => true, 'ai.provider' => 'stub']);
    Cache::flush();

    ['owner' => $owner, 'product' => $product, 'version' => $version] = makeMergedPrAiNarrativeFixture();

    $this->actingAs($owner)
        ->postJson(route('products.versions.merged-prs.ai-narrative', [$product, $version]))
        ->assertUnprocessable();
});

test('AI merged-PR narrative requires AI enabled', function () {
    config(['ai.enabled' => false, 'ai.provider' => 'stub']);
    Cache::flush();

    ['owner' => $owner, 'product' => $product, 'version' => $version] = makeMergedPrAiNarrativeFixture();
    linkGithubRepoForAiNarrative($product);
    fakeGithubMergedPrsForAiNarrative();

    $this->actingAs($owner)
        ->postJson(route('products.versions.merged-prs.ai-narrative', [$product, $version]))
        ->assertUnprocessable();
});

test('version show exposes aiEnabled when AI is on', function () {
    config(['ai.enabled' => true, 'ai.provider' => 'stub']);
    Cache::flush();

    ['owner' => $owner, 'product' => $product, 'version' => $version] = makeMergedPrAiNarrativeFixture();
    linkGithubRepoForAiNarrative($product);
    fakeGithubMergedPrsForAiNarrative();

    $this->actingAs($owner)
        ->get(route('products.versions.show', [$product, $version]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/versions/Show')
            ->where('aiEnabled', true)
            ->where('canManage', true));
});
