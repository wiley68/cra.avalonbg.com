<?php

use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ProductVersionState;
use App\Enums\SdlRunStatus;
use App\Enums\SdlStage;
use App\Enums\SdlStageStatus;
use App\Enums\ScopeStatus;
use App\Enums\SupportStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVersion;
use App\Models\SdlRun;
use App\Models\SdlStageEntry;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, product: Product, version: ProductVersion}
 */
function makeSdlModelFixture(): array
{
    $organization = Organization::query()->create([
        'name' => 'SDL Model Org',
        'slug' => 'sdl-model-org-' . uniqid(),
        'is_active' => true,
        'locale' => 'en',
    ]);

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'SDL Model Product',
        'slug' => 'sdl-model-product-' . uniqid(),
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    $version = ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '2.0.0',
        'state' => ProductVersionState::Released,
        'support_status' => SupportStatus::Supported,
    ]);

    return compact('organization', 'product', 'version');
}

test('sdl run persists with enums casts and relations', function () {
    ['organization' => $organization, 'product' => $product, 'version' => $version] = makeSdlModelFixture();

    $run = SdlRun::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'product_version_id' => $version->id,
        'title' => 'Release 2.0.0 security gate',
        'status' => SdlRunStatus::Draft,
        'current_stage' => SdlStage::Requirement,
        'notes' => 'Initial SDL run.',
    ]);

    $entry = SdlStageEntry::query()->create([
        'sdl_run_id' => $run->id,
        'stage' => SdlStage::Requirement,
        'status' => SdlStageStatus::Pending,
        'notes' => 'Map CRA requirements to feature.',
    ]);

    $run->refresh()->load(['stageEntries', 'product', 'organization', 'version']);

    expect($run->status)->toBe(SdlRunStatus::Draft)
        ->and($run->current_stage)->toBe(SdlStage::Requirement)
        ->and($run->isActive())->toBeTrue()
        ->and($run->isTerminal())->toBeFalse()
        ->and($run->isApproved())->toBeFalse()
        ->and($run->product->is($product))->toBeTrue()
        ->and($run->organization->is($organization))->toBeTrue()
        ->and($run->version->is($version))->toBeTrue()
        ->and($run->stageEntries)->toHaveCount(1)
        ->and($run->stageEntries->first()->is($entry))->toBeTrue()
        ->and($entry->isComplete())->toBeFalse()
        ->and($product->sdlRuns()->count())->toBe(1)
        ->and($organization->sdlRuns()->count())->toBe(1)
        ->and($version->sdlRuns()->count())->toBe(1);
});

test('ensureStageEntries seeds all fixed stages idempotently', function () {
    ['organization' => $organization, 'product' => $product] = makeSdlModelFixture();

    $run = SdlRun::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Checklist seed',
        'status' => SdlRunStatus::InProgress,
        'current_stage' => SdlStage::ThreatReview,
    ]);

    $run->ensureStageEntries();
    $run->ensureStageEntries();

    $stages = $run->stageEntries()->orderBy('id')->get();

    expect($stages)->toHaveCount(count(SdlStage::ordered()))
        ->and($stages->pluck('stage')->all())->toEqual(SdlStage::ordered())
        ->and($stages->every(fn(SdlStageEntry $entry): bool => $entry->status === SdlStageStatus::Pending))->toBeTrue();
});

test('sdl stage entry enforces uniqueness per run and stage', function () {
    ['organization' => $organization, 'product' => $product] = makeSdlModelFixture();

    $run = SdlRun::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Unique stage check',
        'status' => SdlRunStatus::Draft,
        'current_stage' => SdlStage::Design,
    ]);

    SdlStageEntry::query()->create([
        'sdl_run_id' => $run->id,
        'stage' => SdlStage::Design,
        'status' => SdlStageStatus::Pending,
    ]);

    expect(fn() => SdlStageEntry::query()->create([
        'sdl_run_id' => $run->id,
        'stage' => SdlStage::Design,
        'status' => SdlStageStatus::Done,
    ]))->toThrow(QueryException::class);
});

test('deleting sdl run cascades stage entries', function () {
    ['organization' => $organization, 'product' => $product] = makeSdlModelFixture();

    $run = SdlRun::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Cascade check',
        'status' => SdlRunStatus::Blocked,
        'current_stage' => SdlStage::CodeReview,
    ]);

    $run->ensureStageEntries();
    $runId = $run->id;
    $run->delete();

    expect(SdlRun::query()->whereKey($runId)->exists())->toBeFalse()
        ->and(SdlStageEntry::query()->where('sdl_run_id', $runId)->exists())->toBeFalse()
        ->and(
            DB::table('sdl_stage_entries')->where('sdl_run_id', $runId)->exists(),
        )->toBeFalse();
});

test('sdl enums expose workflow helpers', function () {
    expect(SdlStage::ordered())->toHaveCount(10)
        ->and(SdlStage::first())->toBe(SdlStage::Requirement)
        ->and(SdlStage::Requirement->next())->toBe(SdlStage::ThreatReview)
        ->and(SdlStage::Monitoring->next())->toBeNull()
        ->and(SdlStage::ReleaseApproval->isReleaseGate())->toBeTrue()
        ->and(SdlRunStatus::Approved->isTerminal())->toBeTrue()
        ->and(SdlRunStatus::Cancelled->isTerminal())->toBeTrue()
        ->and(SdlRunStatus::InProgress->isTerminal())->toBeFalse()
        ->and(SdlRunStatus::active())->toContain(SdlRunStatus::Blocked)
        ->and(SdlStageStatus::Done->isComplete())->toBeTrue()
        ->and(SdlStageStatus::Na->isComplete())->toBeTrue()
        ->and(SdlStageStatus::Exception->requiresFollowUp())->toBeTrue()
        ->and(SdlStageStatus::Pending->isComplete())->toBeFalse();
});
