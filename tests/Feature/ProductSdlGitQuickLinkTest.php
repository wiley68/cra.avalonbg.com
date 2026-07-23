<?php

use App\Enums\ClassificationStatus;
use App\Enums\EvidenceConfidentiality;
use App\Enums\EvidenceFreshnessStatus;
use App\Enums\EvidenceType;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ProductVersionState;
use App\Enums\ScopeStatus;
use App\Enums\SdlRunStatus;
use App\Enums\SdlStage;
use App\Enums\SupportStatus;
use App\Enums\VcsAuthType;
use App\Enums\VcsConnectionStatus;
use App\Enums\VcsProvider;
use App\Models\Evidence;
use App\Models\Organization;
use App\Models\OrganizationVcsConnection;
use App\Models\Product;
use App\Models\ProductRepository;
use App\Models\ProductVersion;
use App\Models\Role;
use App\Models\SdlRun;
use App\Models\SdlStageEntry;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

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
function makeSdlGitQuickLinkFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'SDL Git Org',
        'slug' => 'sdl-git-org-' . uniqid(),
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
        'name' => 'SDL Git Product',
        'slug' => 'sdl-git-product-' . uniqid(),
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

    ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '1.0.0',
        'state' => ProductVersionState::Draft,
        'support_status' => SupportStatus::Supported,
    ]);

    $connection = OrganizationVcsConnection::query()->create([
        'organization_id' => $organization->id,
        'provider' => VcsProvider::Github,
        'auth_type' => VcsAuthType::Pat,
        'token' => 'ghp_sdl_token',
        'label' => 'GitHub',
        'status' => VcsConnectionStatus::Active,
        'last_verified_at' => now(),
    ]);

    $repository = ProductRepository::query()->create([
        'product_id' => $product->id,
        'organization_id' => $organization->id,
        'connection_id' => $connection->id,
        'provider' => VcsProvider::Github,
        'full_name' => 'acme/sdl-app',
        'remote_url' => 'https://github.com/acme/sdl-app',
        'default_branch' => 'main',
        'external_id' => '12345',
        'last_synced_at' => now(),
        'last_sync_summary' => [
            'evidence_id' => null,
            'ci' => [
                'status' => 'completed',
                'conclusion' => 'success',
                'html_url' => 'https://github.com/acme/sdl-app/actions/runs/99',
            ],
        ],
    ]);

    $snapshot = Evidence::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'type' => EvidenceType::IntegrationSnapshot,
        'title' => 'VCS sync acme/sdl-app',
        'source' => 'https://github.com/acme/sdl-app',
        'confidentiality' => EvidenceConfidentiality::Internal,
        'freshness_status' => EvidenceFreshnessStatus::Current,
        'collected_at' => now(),
        'checksum_sha256' => hash('sha256', 'sdl-git-snapshot'),
        'uploaded_by' => $owner->id,
    ]);

    $summary = $repository->last_sync_summary;
    $summary['evidence_id'] = $snapshot->id;
    $repository->update(['last_sync_summary' => $summary]);

    return compact('organization', 'owner', 'product', 'repository', 'snapshot');
}

test('sdl edit exposes repository and recent git evidence props', function () {
    [
        'owner' => $owner,
        'product' => $product,
        'snapshot' => $snapshot,
    ] = makeSdlGitQuickLinkFixture();

    $run = SdlRun::query()->create([
        'organization_id' => $product->organization_id,
        'product_id' => $product->id,
        'title' => 'Git quick link run',
        'status' => SdlRunStatus::Draft,
        'current_stage' => SdlStage::CodeReview,
    ]);
    $run->ensureStageEntries();

    $this->actingAs($owner)
        ->get(route('products.sdl.edit', [$product, $run]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/sdl/Edit')
            ->where('repository.full_name', 'acme/sdl-app')
            ->where('repository.last_sync_summary.ci.html_url', 'https://github.com/acme/sdl-app/actions/runs/99')
            ->has('git_evidence', 1)
            ->where('git_evidence.0.id', $snapshot->id)
            ->where('evidence.0.type', EvidenceType::IntegrationSnapshot->value));
});

test('owner can link external pr url as evidence on sdl run and stage', function () {
    [
        'owner' => $owner,
        'product' => $product,
    ] = makeSdlGitQuickLinkFixture();

    $run = SdlRun::query()->create([
        'organization_id' => $product->organization_id,
        'product_id' => $product->id,
        'title' => 'PR link run',
        'status' => SdlRunStatus::InProgress,
        'current_stage' => SdlStage::CodeReview,
    ]);
    $run->ensureStageEntries();

    $this->actingAs($owner)
        ->post(route('products.sdl.link-external-evidence', [$product, $run]), [
            'url' => 'https://github.com/acme/sdl-app/pull/42',
            'title' => 'Peer review PR #42',
            'stage' => SdlStage::CodeReview->value,
        ])
        ->assertRedirect(route('products.sdl.edit', [$product, $run]));

    $evidence = Evidence::query()
        ->where('product_id', $product->id)
        ->where('source', 'https://github.com/acme/sdl-app/pull/42')
        ->firstOrFail();

    expect($evidence->title)->toBe('Peer review PR #42')
        ->and($evidence->type)->toBe(EvidenceType::Other)
        ->and($run->fresh()->evidence()->pluck('evidence.id')->all())->toContain($evidence->id);

    $entry = SdlStageEntry::query()
        ->where('sdl_run_id', $run->id)
        ->where('stage', SdlStage::CodeReview->value)
        ->firstOrFail();

    expect($entry->evidence()->pluck('evidence.id')->all())->toContain($evidence->id)
        ->and(
            DB::table('sdl_stage_evidence')
                ->where('sdl_stage_entry_id', $entry->id)
                ->where('evidence_id', $evidence->id)
                ->exists(),
        )->toBeTrue();
});

test('invalid git url is rejected and approved runs stay locked', function () {
    [
        'owner' => $owner,
        'product' => $product,
    ] = makeSdlGitQuickLinkFixture();

    $run = SdlRun::query()->create([
        'organization_id' => $product->organization_id,
        'product_id' => $product->id,
        'title' => 'Locked git run',
        'status' => SdlRunStatus::Approved,
        'current_stage' => SdlStage::Publication,
        'approved_at' => now(),
        'approved_by' => $owner->id,
    ]);
    $run->ensureStageEntries();

    $this->actingAs($owner)
        ->from(route('products.sdl.edit', [$product, $run]))
        ->post(route('products.sdl.link-external-evidence', [$product, $run]), [
            'url' => 'not-a-url',
        ])
        ->assertRedirect(route('products.sdl.edit', [$product, $run]))
        ->assertSessionHasErrors('url');

    $this->actingAs($owner)
        ->from(route('products.sdl.edit', [$product, $run]))
        ->post(route('products.sdl.link-external-evidence', [$product, $run]), [
            'url' => 'https://github.com/acme/sdl-app/pull/7',
        ])
        ->assertRedirect(route('products.sdl.edit', [$product, $run]))
        ->assertSessionHasErrors('status');

    expect(
        Evidence::query()
            ->where('product_id', $product->id)
            ->where('source', 'https://github.com/acme/sdl-app/pull/7')
            ->exists(),
    )->toBeFalse();
});

test('sdl create page includes repository git context when linked', function () {
    [
        'owner' => $owner,
        'product' => $product,
        'snapshot' => $snapshot,
    ] = makeSdlGitQuickLinkFixture();

    $this->actingAs($owner)
        ->get(route('products.sdl.create', $product))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/sdl/Create')
            ->where('repository.full_name', 'acme/sdl-app')
            ->where('git_evidence.0.id', $snapshot->id));
});
