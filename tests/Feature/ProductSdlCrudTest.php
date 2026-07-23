<?php

use App\Enums\AuditEventType;
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
use App\Enums\SdlStageStatus;
use App\Enums\SupportStatus;
use App\Models\AuditLog;
use App\Models\Evidence;
use App\Models\Organization;
use App\Models\Product;
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
 * @return array{0: Organization, 1: User}
 */
function makeSdlOrgWithOwner(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'SDL Org',
        'slug' => 'sdl-org-' . uniqid(),
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

    return [$organization, $owner];
}

function makeSdlOrgReadOnly(Organization $organization): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $role = Role::query()->where('slug', 'read_only')->firstOrFail();

    $organization->users()->attach($user->id, [
        'role_id' => $role->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $user;
}

/**
 * @return array{0: Product, 1: ProductVersion}
 */
function makeProductWithVersionForSdl(Organization $organization, User $owner): array
{
    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Gateway SDL',
        'slug' => 'gateway-sdl-' . uniqid(),
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

    $version = ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '3.1.0',
        'release_date' => now()->toDateString(),
        'state' => ProductVersionState::Draft,
        'support_status' => SupportStatus::Supported,
    ]);

    return [$product, $version];
}

test('owner can create sdl run with seeded stage entries', function () {
    [$organization, $owner] = makeSdlOrgWithOwner();
    [$product, $version] = makeProductWithVersionForSdl($organization, $owner);

    $this->actingAs($owner)
        ->post(route('products.sdl.store', $product), [
            'title' => 'Release 3.1.0 SDL',
            'status' => SdlRunStatus::Draft->value,
            'current_stage' => SdlStage::Requirement->value,
            'product_version_id' => $version->id,
            'owner_user_id' => $owner->id,
            'notes' => 'Feature security gate.',
        ])
        ->assertRedirect();

    $run = SdlRun::query()
        ->where('product_id', $product->id)
        ->where('title', 'Release 3.1.0 SDL')
        ->firstOrFail();

    expect($run->organization_id)->toBe($organization->id)
        ->and($run->status)->toBe(SdlRunStatus::Draft)
        ->and($run->current_stage)->toBe(SdlStage::Requirement)
        ->and($run->product_version_id)->toBe($version->id)
        ->and($run->owner_user_id)->toBe($owner->id)
        ->and($run->stageEntries()->count())->toBe(count(SdlStage::ordered()))
        ->and(
            AuditLog::query()
                ->where('event_type', AuditEventType::SdlRunCreated->value)
                ->where('product_id', $product->id)
                ->exists(),
        )->toBeTrue();
});

test('owner can open sdl index and internal api list', function () {
    [$organization, $owner] = makeSdlOrgWithOwner();
    [$product] = makeProductWithVersionForSdl($organization, $owner);

    $run = SdlRun::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Listed run',
        'status' => SdlRunStatus::InProgress,
        'current_stage' => SdlStage::Design,
    ]);
    $run->ensureStageEntries();

    $this->actingAs($owner)
        ->get(route('products.sdl.index', $product))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/sdl/Index')
            ->where('product.id', $product->id)
            ->where('canManage', true));

    $this->actingAs($owner)
        ->getJson(route('internal.products.sdl.index', $product))
        ->assertOk()
        ->assertJsonPath('data.0.id', $run->id)
        ->assertJsonPath('data.0.title', 'Listed run');
});

test('owner can update and delete sdl run', function () {
    [$organization, $owner] = makeSdlOrgWithOwner();
    [$product, $version] = makeProductWithVersionForSdl($organization, $owner);

    $run = SdlRun::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Editable run',
        'status' => SdlRunStatus::Draft,
        'current_stage' => SdlStage::Requirement,
    ]);
    $run->ensureStageEntries();

    $this->actingAs($owner)
        ->put(route('products.sdl.update', [$product, $run]), [
            'title' => 'Editable run updated',
            'status' => SdlRunStatus::InProgress->value,
            'current_stage' => SdlStage::ThreatReview->value,
            'product_version_id' => $version->id,
            'owner_user_id' => $owner->id,
            'notes' => 'Moved to threat review.',
        ])
        ->assertRedirect(route('products.sdl.edit', [$product, $run]));

    $run->refresh();

    expect($run->title)->toBe('Editable run updated')
        ->and($run->status)->toBe(SdlRunStatus::InProgress)
        ->and($run->current_stage)->toBe(SdlStage::ThreatReview)
        ->and($run->product_version_id)->toBe($version->id);

    $this->actingAs($owner)
        ->delete(route('products.sdl.destroy', [$product, $run]))
        ->assertRedirect(route('products.sdl.index', $product));

    expect(SdlRun::query()->whereKey($run->id)->exists())->toBeFalse()
        ->and(SdlStageEntry::query()->where('sdl_run_id', $run->id)->exists())->toBeFalse()
        ->and(
            AuditLog::query()
                ->where('event_type', AuditEventType::SdlRunDeleted->value)
                ->where('product_id', $product->id)
                ->exists(),
        )->toBeTrue();
});

test('read-only viewer can view sdl but cannot create or manage', function () {
    [$organization, $owner] = makeSdlOrgWithOwner();
    [$product] = makeProductWithVersionForSdl($organization, $owner);
    $viewer = makeSdlOrgReadOnly($organization);

    $run = SdlRun::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Viewer run',
        'status' => SdlRunStatus::Draft,
        'current_stage' => SdlStage::Requirement,
    ]);

    $this->actingAs($viewer)
        ->get(route('products.sdl.index', $product))
        ->assertOk()
        ->assertInertia(fn($page) => $page->where('canManage', false));

    $this->actingAs($viewer)
        ->get(route('products.sdl.edit', [$product, $run]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/sdl/Edit')
            ->where('canManage', false));

    $this->actingAs($viewer)
        ->getJson(route('internal.products.sdl.index', $product))
        ->assertOk()
        ->assertJsonPath('total', 1);

    $this->actingAs($viewer)
        ->get(route('products.sdl.create', $product))
        ->assertForbidden();

    $this->actingAs($viewer)
        ->post(route('products.sdl.store', $product), [
            'title' => 'Forbidden',
            'status' => SdlRunStatus::Draft->value,
        ])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->put(route('products.sdl.update', [$product, $run]), [
            'title' => 'Forbidden update',
            'status' => SdlRunStatus::InProgress->value,
            'current_stage' => SdlStage::Design->value,
        ])
        ->assertForbidden();

    expect($run->fresh()->title)->toBe('Viewer run');

    $this->actingAs($viewer)
        ->delete(route('products.sdl.destroy', [$product, $run]))
        ->assertForbidden();

    expect(SdlRun::query()->whereKey($run->id)->exists())->toBeTrue();
});

test('owner can update stage checklist status notes and completion metadata', function () {
    [$organization, $owner] = makeSdlOrgWithOwner();
    [$product] = makeProductWithVersionForSdl($organization, $owner);

    $run = SdlRun::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Checklist run',
        'status' => SdlRunStatus::Draft,
        'current_stage' => SdlStage::Requirement,
    ]);
    $run->ensureStageEntries();

    $this->actingAs($owner)
        ->put(route('products.sdl.stages.update', [
            'product' => $product,
            'sdlRun' => $run,
            'stage' => SdlStage::Requirement->value,
        ]), [
            'status' => SdlStageStatus::Done->value,
            'notes' => 'Security requirements mapped.',
        ])
        ->assertRedirect(route('products.sdl.edit', [$product, $run]));

    $entry = SdlStageEntry::query()
        ->where('sdl_run_id', $run->id)
        ->where('stage', SdlStage::Requirement->value)
        ->firstOrFail();

    $run->refresh();

    expect($entry->status)->toBe(SdlStageStatus::Done)
        ->and($entry->notes)->toBe('Security requirements mapped.')
        ->and($entry->completed_by)->toBe($owner->id)
        ->and($entry->completed_at)->not->toBeNull()
        ->and($run->current_stage)->toBe(SdlStage::ThreatReview)
        ->and($run->status)->toBe(SdlRunStatus::InProgress)
        ->and(
            AuditLog::query()
                ->where('event_type', AuditEventType::SdlStageUpdated->value)
                ->where('product_id', $product->id)
                ->exists(),
        )->toBeTrue();

    $this->actingAs($owner)
        ->put(route('products.sdl.stages.update', [
            'product' => $product,
            'sdlRun' => $run,
            'stage' => SdlStage::ThreatReview->value,
        ]), [
            'status' => SdlStageStatus::Na->value,
            'notes' => 'No new threat model for patch release.',
        ])
        ->assertRedirect();

    $threatEntry = SdlStageEntry::query()
        ->where('sdl_run_id', $run->id)
        ->where('stage', SdlStage::ThreatReview->value)
        ->firstOrFail();

    expect($threatEntry->status)->toBe(SdlStageStatus::Na)
        ->and($threatEntry->completed_at)->not->toBeNull();

    $this->actingAs($owner)
        ->put(route('products.sdl.stages.update', [
            'product' => $product,
            'sdlRun' => $run,
            'stage' => SdlStage::Requirement->value,
        ]), [
            'status' => SdlStageStatus::Pending->value,
            'notes' => 'Reopened for follow-up.',
        ])
        ->assertRedirect();

    $entry->refresh();
    $run->refresh();

    expect($entry->status)->toBe(SdlStageStatus::Pending)
        ->and($entry->completed_at)->toBeNull()
        ->and($entry->completed_by)->toBeNull()
        ->and($run->current_stage)->toBe(SdlStage::Requirement);
});

test('read-only viewer cannot update sdl stage checklist', function () {
    [$organization, $owner] = makeSdlOrgWithOwner();
    [$product] = makeProductWithVersionForSdl($organization, $owner);
    $viewer = makeSdlOrgReadOnly($organization);

    $run = SdlRun::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Viewer checklist',
        'status' => SdlRunStatus::Draft,
        'current_stage' => SdlStage::Requirement,
    ]);
    $run->ensureStageEntries();

    $this->actingAs($viewer)
        ->put(route('products.sdl.stages.update', [
            'product' => $product,
            'sdlRun' => $run,
            'stage' => SdlStage::Requirement->value,
        ]), [
            'status' => SdlStageStatus::Done->value,
            'notes' => 'Nope',
        ])
        ->assertForbidden();
});

test('owner can link evidence to sdl run and stage', function () {
    [$organization, $owner] = makeSdlOrgWithOwner();
    [$product] = makeProductWithVersionForSdl($organization, $owner);

    $runEvidence = Evidence::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'type' => EvidenceType::Document,
        'title' => 'Release security checklist',
        'confidentiality' => EvidenceConfidentiality::Internal,
        'freshness_status' => EvidenceFreshnessStatus::Current,
        'uploaded_by' => $owner->id,
    ]);

    $stageEvidence = Evidence::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'type' => EvidenceType::Document,
        'title' => 'Code review record',
        'confidentiality' => EvidenceConfidentiality::Internal,
        'freshness_status' => EvidenceFreshnessStatus::Current,
        'uploaded_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->post(route('products.sdl.store', $product), [
            'title' => 'Evidence-linked run',
            'status' => SdlRunStatus::Draft->value,
            'current_stage' => SdlStage::CodeReview->value,
            'evidence_ids' => [$runEvidence->id],
        ])
        ->assertRedirect();

    $run = SdlRun::query()
        ->where('product_id', $product->id)
        ->where('title', 'Evidence-linked run')
        ->firstOrFail();

    expect($run->evidence()->pluck('evidence.id')->all())->toContain($runEvidence->id);

    $this->actingAs($owner)
        ->put(route('products.sdl.update', [$product, $run]), [
            'title' => 'Evidence-linked run',
            'status' => SdlRunStatus::InProgress->value,
            'current_stage' => SdlStage::CodeReview->value,
            'evidence_ids' => [$runEvidence->id, $stageEvidence->id],
        ])
        ->assertRedirect();

    expect($run->fresh()->evidence()->pluck('evidence.id')->sort()->values()->all())
        ->toEqual(collect([$runEvidence->id, $stageEvidence->id])->sort()->values()->all());

    $this->actingAs($owner)
        ->put(route('products.sdl.stages.update', [
            'product' => $product,
            'sdlRun' => $run,
            'stage' => SdlStage::CodeReview->value,
        ]), [
            'status' => SdlStageStatus::Done->value,
            'notes' => 'Peer review complete.',
            'evidence_ids' => [$stageEvidence->id],
        ])
        ->assertRedirect();

    $entry = SdlStageEntry::query()
        ->where('sdl_run_id', $run->id)
        ->where('stage', SdlStage::CodeReview->value)
        ->firstOrFail();

    expect($entry->evidence()->pluck('evidence.id')->all())->toContain($stageEvidence->id)
        ->and(
            DB::table('sdl_stage_evidence')
                ->where('sdl_stage_entry_id', $entry->id)
                ->where('evidence_id', $stageEvidence->id)
                ->exists(),
        )->toBeTrue();

    $runId = $run->id;
    $this->actingAs($owner)
        ->delete(route('products.sdl.destroy', [$product, $run]))
        ->assertRedirect();

    expect(DB::table('sdl_run_evidence')->where('sdl_run_id', $runId)->exists())->toBeFalse()
        ->and(DB::table('sdl_stage_evidence')->where('sdl_stage_entry_id', $entry->id)->exists())->toBeFalse();
});

/**
 * Complete every stage through Release approval; mark Release approval as Done.
 */
function prepareSdlRunForApproval(SdlRun $run, User $actor): void
{
    $run->ensureStageEntries();

    foreach (SdlStage::ordered() as $stage) {
        if ($stage === SdlStage::Publication || $stage === SdlStage::Monitoring) {
            break;
        }

        SdlStageEntry::query()
            ->where('sdl_run_id', $run->id)
            ->where('stage', $stage->value)
            ->update([
                'status' => SdlStageStatus::Done->value,
                'completed_at' => now(),
                'completed_by' => $actor->id,
                'notes' => 'Gate ready',
            ]);

        if ($stage === SdlStage::ReleaseApproval) {
            break;
        }
    }

    $run->update([
        'status' => SdlRunStatus::InProgress,
        'current_stage' => SdlStage::ReleaseApproval,
    ]);
}

test('owner cannot approve sdl run until release gate stages are complete', function () {
    [$organization, $owner] = makeSdlOrgWithOwner();
    [$product] = makeProductWithVersionForSdl($organization, $owner);

    $run = SdlRun::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Not ready',
        'status' => SdlRunStatus::InProgress,
        'current_stage' => SdlStage::Requirement,
    ]);
    $run->ensureStageEntries();

    $this->actingAs($owner)
        ->from(route('products.sdl.edit', [$product, $run]))
        ->post(route('products.sdl.approve', [$product, $run]))
        ->assertRedirect(route('products.sdl.edit', [$product, $run]))
        ->assertSessionHasErrors(['stages', 'release_approval']);

    expect($run->fresh()->status)->toBe(SdlRunStatus::InProgress)
        ->and($run->fresh()->approved_at)->toBeNull();
});

test('owner can approve sdl run when release gate is ready and edits lock', function () {
    [$organization, $owner] = makeSdlOrgWithOwner();
    [$product] = makeProductWithVersionForSdl($organization, $owner);

    $run = SdlRun::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Ready to approve',
        'status' => SdlRunStatus::InProgress,
        'current_stage' => SdlStage::ReleaseApproval,
    ]);
    prepareSdlRunForApproval($run, $owner);

    $this->actingAs($owner)
        ->post(route('products.sdl.approve', [$product, $run]))
        ->assertRedirect(route('products.sdl.edit', [$product, $run]));

    $run->refresh();

    expect($run->status)->toBe(SdlRunStatus::Approved)
        ->and($run->approved_by)->toBe($owner->id)
        ->and($run->approved_at)->not->toBeNull()
        ->and($run->current_stage)->toBe(SdlStage::Publication)
        ->and(
            AuditLog::query()
                ->where('event_type', AuditEventType::SdlRunApproved->value)
                ->where('product_id', $product->id)
                ->exists(),
        )->toBeTrue();

    $this->actingAs($owner)
        ->from(route('products.sdl.edit', [$product, $run]))
        ->put(route('products.sdl.update', [$product, $run]), [
            'title' => 'Should stay locked',
            'status' => SdlRunStatus::InProgress->value,
            'current_stage' => SdlStage::Publication->value,
        ])
        ->assertRedirect(route('products.sdl.edit', [$product, $run]))
        ->assertSessionHasErrors('status');

    $this->actingAs($owner)
        ->from(route('products.sdl.edit', [$product, $run]))
        ->put(route('products.sdl.stages.update', [
            'product' => $product,
            'sdlRun' => $run,
            'stage' => SdlStage::Publication->value,
        ]), [
            'status' => SdlStageStatus::Done->value,
            'notes' => 'Locked',
        ])
        ->assertRedirect(route('products.sdl.edit', [$product, $run]))
        ->assertSessionHasErrors('status');

    expect($run->fresh()->title)->toBe('Ready to approve');
});

test('owner cannot set approved status via create or update form', function () {
    [$organization, $owner] = makeSdlOrgWithOwner();
    [$product] = makeProductWithVersionForSdl($organization, $owner);

    $this->actingAs($owner)
        ->from(route('products.sdl.create', $product))
        ->post(route('products.sdl.store', $product), [
            'title' => 'Bypass approve',
            'status' => SdlRunStatus::Approved->value,
            'current_stage' => SdlStage::ReleaseApproval->value,
        ])
        ->assertRedirect(route('products.sdl.create', $product))
        ->assertSessionHasErrors('status');

    $run = SdlRun::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Editable',
        'status' => SdlRunStatus::Draft,
        'current_stage' => SdlStage::Requirement,
    ]);
    $run->ensureStageEntries();

    $this->actingAs($owner)
        ->from(route('products.sdl.edit', [$product, $run]))
        ->put(route('products.sdl.update', [$product, $run]), [
            'title' => 'Editable',
            'status' => SdlRunStatus::Approved->value,
            'current_stage' => SdlStage::ReleaseApproval->value,
        ])
        ->assertRedirect(route('products.sdl.edit', [$product, $run]))
        ->assertSessionHasErrors('status');
});

test('owner can revoke sdl approval and reopen editing', function () {
    [$organization, $owner] = makeSdlOrgWithOwner();
    [$product] = makeProductWithVersionForSdl($organization, $owner);

    $run = SdlRun::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Approved run',
        'status' => SdlRunStatus::InProgress,
        'current_stage' => SdlStage::ReleaseApproval,
    ]);
    prepareSdlRunForApproval($run, $owner);

    $this->actingAs($owner)
        ->post(route('products.sdl.approve', [$product, $run]))
        ->assertRedirect();

    $this->actingAs($owner)
        ->post(route('products.sdl.revoke-approval', [$product, $run]))
        ->assertRedirect(route('products.sdl.edit', [$product, $run]));

    $run->refresh();

    expect($run->status)->toBe(SdlRunStatus::InProgress)
        ->and($run->approved_at)->toBeNull()
        ->and($run->approved_by)->toBeNull()
        ->and($run->current_stage)->toBe(SdlStage::ReleaseApproval)
        ->and(
            AuditLog::query()
                ->where('event_type', AuditEventType::SdlRunApprovalRevoked->value)
                ->where('product_id', $product->id)
                ->exists(),
        )->toBeTrue();

    $this->actingAs($owner)
        ->put(route('products.sdl.update', [$product, $run]), [
            'title' => 'Reopened title',
            'status' => SdlRunStatus::InProgress->value,
            'current_stage' => SdlStage::ReleaseApproval->value,
        ])
        ->assertRedirect();

    expect($run->fresh()->title)->toBe('Reopened title');
});

test('read-only viewer cannot approve or revoke sdl run', function () {
    [$organization, $owner] = makeSdlOrgWithOwner();
    [$product] = makeProductWithVersionForSdl($organization, $owner);
    $viewer = makeSdlOrgReadOnly($organization);

    $run = SdlRun::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Viewer approve',
        'status' => SdlRunStatus::InProgress,
        'current_stage' => SdlStage::ReleaseApproval,
    ]);
    prepareSdlRunForApproval($run, $owner);

    $this->actingAs($viewer)
        ->post(route('products.sdl.approve', [$product, $run]))
        ->assertForbidden();

    $run->update([
        'status' => SdlRunStatus::Approved,
        'approved_at' => now(),
        'approved_by' => $owner->id,
        'current_stage' => SdlStage::Publication,
    ]);

    $this->actingAs($viewer)
        ->post(route('products.sdl.revoke-approval', [$product, $run]))
        ->assertForbidden();
});

test('release approval stage must be done not na for approval gate', function () {
    [$organization, $owner] = makeSdlOrgWithOwner();
    [$product] = makeProductWithVersionForSdl($organization, $owner);

    $run = SdlRun::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'NA release gate',
        'status' => SdlRunStatus::InProgress,
        'current_stage' => SdlStage::ReleaseApproval,
    ]);
    prepareSdlRunForApproval($run, $owner);

    SdlStageEntry::query()
        ->where('sdl_run_id', $run->id)
        ->where('stage', SdlStage::ReleaseApproval->value)
        ->update([
            'status' => SdlStageStatus::Na->value,
        ]);

    $this->actingAs($owner)
        ->from(route('products.sdl.edit', [$product, $run]))
        ->post(route('products.sdl.approve', [$product, $run]))
        ->assertRedirect(route('products.sdl.edit', [$product, $run]))
        ->assertSessionHasErrors('release_approval');
});
