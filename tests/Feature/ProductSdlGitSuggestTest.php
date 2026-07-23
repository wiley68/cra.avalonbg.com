<?php

use App\Enums\ClassificationStatus;
use App\Enums\EvidenceConfidentiality;
use App\Enums\EvidenceFreshnessStatus;
use App\Enums\EvidenceType;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\SdlRunStatus;
use App\Enums\SdlStage;
use App\Enums\VcsAuthType;
use App\Enums\VcsConnectionStatus;
use App\Enums\VcsProvider;
use App\Models\Evidence;
use App\Models\Organization;
use App\Models\OrganizationVcsConnection;
use App\Models\Product;
use App\Models\ProductRepository;
use App\Models\Role;
use App\Models\SdlRun;
use App\Models\User;
use App\Services\ProductSdlService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     organization: Organization,
 *     owner: User,
 *     product: Product,
 *     repository: ProductRepository,
 *     snapshot: Evidence
 * }
 */
function makeSdlGitSuggestFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'SDL Suggest Org',
        'slug' => 'sdl-suggest-org-' . uniqid(),
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
        'name' => 'SDL Suggest Product',
        'slug' => 'sdl-suggest-product-' . uniqid(),
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

    $connection = OrganizationVcsConnection::query()->create([
        'organization_id' => $organization->id,
        'provider' => VcsProvider::Github,
        'auth_type' => VcsAuthType::Pat,
        'token' => 'ghp_suggest_token',
        'label' => 'GitHub',
        'status' => VcsConnectionStatus::Active,
        'last_verified_at' => now(),
    ]);

    $snapshot = Evidence::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'type' => EvidenceType::IntegrationSnapshot,
        'title' => 'VCS sync acme/suggest-app',
        'source' => 'github:acme/suggest-app',
        'confidentiality' => EvidenceConfidentiality::Internal,
        'freshness_status' => EvidenceFreshnessStatus::Current,
        'collected_at' => now(),
        'checksum_sha256' => hash('sha256', 'sdl-suggest-snapshot'),
        'uploaded_by' => $owner->id,
    ]);

    $repository = ProductRepository::query()->create([
        'product_id' => $product->id,
        'organization_id' => $organization->id,
        'connection_id' => $connection->id,
        'provider' => VcsProvider::Github,
        'full_name' => 'acme/suggest-app',
        'remote_url' => 'https://github.com/acme/suggest-app',
        'default_branch' => 'main',
        'external_id' => '99887',
        'last_synced_at' => now(),
        'last_sync_summary' => [
            'evidence_id' => $snapshot->id,
            'ci' => [
                'status' => 'completed',
                'conclusion' => 'success',
                'workflow_name' => 'CI',
                'html_url' => 'https://github.com/acme/suggest-app/actions/runs/42',
            ],
        ],
    ]);

    return compact('organization', 'owner', 'product', 'repository', 'snapshot');
}

test('gitSyncSuggestions returns snapshot and CI URL without attaching', function () {
    [
        'product' => $product,
        'snapshot' => $snapshot,
        'owner' => $owner,
    ] = makeSdlGitSuggestFixture();

    $run = SdlRun::query()->create([
        'organization_id' => $product->organization_id,
        'product_id' => $product->id,
        'title' => 'Suggest run',
        'status' => SdlRunStatus::InProgress,
        'current_stage' => SdlStage::SecurityTest,
        'owner_user_id' => $owner->id,
    ]);
    $run->ensureStageEntries();

    $suggestions = app(ProductSdlService::class)->gitSyncSuggestions($product, $run);

    expect($suggestions['has_error'])->toBeFalse()
        ->and($suggestions['items'])->toHaveCount(2)
        ->and($suggestions['items'][0]['kind'])->toBe('snapshot')
        ->and($suggestions['items'][0]['evidence_id'])->toBe($snapshot->id)
        ->and($suggestions['items'][0]['already_on_run'])->toBeFalse()
        ->and($suggestions['items'][0]['suggested_stages'])->toContain(SdlStage::DependencyScan->value)
        ->and($suggestions['items'][0]['suggested_stages'])->toContain(SdlStage::SecurityTest->value)
        ->and($suggestions['items'][1]['kind'])->toBe('ci_url')
        ->and($suggestions['items'][1]['url'])->toBe('https://github.com/acme/suggest-app/actions/runs/42')
        ->and($suggestions['items'][1]['suggested_stages'])->toContain(SdlStage::SecurityTest->value);

    expect($run->fresh()->evidence)->toHaveCount(0);
});

test('gitSyncSuggestions marks snapshot already on run', function () {
    [
        'product' => $product,
        'snapshot' => $snapshot,
        'owner' => $owner,
    ] = makeSdlGitSuggestFixture();

    $run = SdlRun::query()->create([
        'organization_id' => $product->organization_id,
        'product_id' => $product->id,
        'title' => 'Attached suggest run',
        'status' => SdlRunStatus::InProgress,
        'current_stage' => SdlStage::CodeReview,
        'owner_user_id' => $owner->id,
    ]);
    $run->ensureStageEntries();
    $run->evidence()->syncWithoutDetaching([$snapshot->id]);

    $suggestions = app(ProductSdlService::class)->gitSyncSuggestions($product->fresh(), $run->fresh());

    expect($suggestions['items'][0]['already_on_run'])->toBeTrue();
});

test('sdl edit exposes git_suggestions from last sync', function () {
    [
        'owner' => $owner,
        'product' => $product,
        'snapshot' => $snapshot,
    ] = makeSdlGitSuggestFixture();

    $run = SdlRun::query()->create([
        'organization_id' => $product->organization_id,
        'product_id' => $product->id,
        'title' => 'Edit suggest props',
        'status' => SdlRunStatus::Draft,
        'current_stage' => SdlStage::CodeReview,
    ]);
    $run->ensureStageEntries();

    $this->actingAs($owner)
        ->get(route('products.sdl.edit', [$product, $run]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/sdl/Edit')
            ->has('git_suggestions.items', 2)
            ->where('git_suggestions.items.0.kind', 'snapshot')
            ->where('git_suggestions.items.0.evidence_id', $snapshot->id)
            ->where('git_suggestions.items.1.kind', 'ci_url')
            ->where(
                'git_suggestions.items.1.url',
                'https://github.com/acme/suggest-app/actions/runs/42',
            ));
});

test('PR-like CI URL suggests code_review stage', function () {
    ['product' => $product, 'repository' => $repository] = makeSdlGitSuggestFixture();

    $summary = $repository->last_sync_summary;
    $summary['ci']['html_url'] = 'https://github.com/acme/suggest-app/pull/7';
    $repository->update(['last_sync_summary' => $summary]);

    $suggestions = app(ProductSdlService::class)->gitSyncSuggestions($product->fresh());

    $ci = collect($suggestions['items'])->firstWhere('kind', 'ci_url');

    expect($ci)->not->toBeNull()
        ->and($ci['suggested_stages'])->toBe([SdlStage::CodeReview->value]);
});
