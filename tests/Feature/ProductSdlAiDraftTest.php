<?php

use App\Enums\AuditEventType;
use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\SdlRunStatus;
use App\Enums\SdlStage;
use App\Enums\SdlStageStatus;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Role;
use App\Models\SdlRun;
use App\Models\User;
use App\Services\Ai\AiSdlStageNotesDraftParser;
use App\Services\AiAssistantService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     organization: Organization,
 *     owner: User,
 *     viewer: User,
 *     product: Product,
 *     run: SdlRun
 * }
 */
function makeSdlAiDraftFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'SDL AI Draft Org',
        'slug' => 'sdl-ai-draft-org-' . uniqid(),
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

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'SDL AI Draft Product',
        'slug' => 'sdl-ai-draft-product-' . uniqid(),
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

    $run = SdlRun::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Release 1.2 SDL',
        'status' => SdlRunStatus::InProgress,
        'current_stage' => SdlStage::ThreatReview,
        'owner_user_id' => $owner->id,
        'notes' => 'Initial run notes.',
    ]);
    $run->ensureStageEntries();

    $entry = $run->stageEntries()
        ->where('stage', SdlStage::ThreatReview)
        ->firstOrFail();
    $entry->update([
        'status' => SdlStageStatus::Pending,
        'notes' => 'Existing threat notes.',
    ]);

    return compact('organization', 'owner', 'viewer', 'product', 'run');
}

test('AiSdlStageNotesDraftParser extracts notes_markdown', function () {
    $parsed = AiSdlStageNotesDraftParser::parse(<<<'JSON'
{
  "notes_markdown": "## Threat review\n\nStub checklist with human review.",
  "human_review_required": true,
  "disclaimer": "Draft only"
}
JSON);

    expect($parsed)->not->toBeNull()
        ->and($parsed['notes_markdown'])->toContain('Stub checklist')
        ->and($parsed['human_review_required'])->toBeTrue();
});

test('suggestSdlStageNotesDraft returns stub draft without writing stage notes', function () {
    config(['ai.enabled' => true, 'ai.provider' => 'stub']);

    ['owner' => $owner, 'product' => $product, 'run' => $run] = makeSdlAiDraftFixture();

    $entry = $run->stageEntries()
        ->where('stage', SdlStage::ThreatReview)
        ->firstOrFail();
    $originalNotes = $entry->notes;

    $result = app(AiAssistantService::class)->suggestSdlStageNotesDraft(
        $product,
        $run,
        $owner,
        SdlStage::ThreatReview,
        'Existing notes',
        'Keep factual',
        'en',
    );

    expect($result['draft']['human_review_required'])->toBeTrue()
        ->and($result['draft']['notes_markdown'])->toContain('stub draft')
        ->and($result['draft']['notes_markdown'])->toContain('human review')
        ->and($entry->fresh()->notes)->toBe($originalNotes);

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::AiSdlStageNotesDraftSuggested->value)
        ->where('product_id', $product->id)
        ->exists())->toBeTrue();

    $log = AuditLog::query()
        ->where('event_type', AuditEventType::AiSdlStageNotesDraftSuggested->value)
        ->first();

    expect($log->description)->toContain((string) $run->id)
        ->and($log->description)->toContain(SdlStage::ThreatReview->value)
        ->and($log->description)->not->toContain('Keep factual');
});

test('HTTP AI SDL stage notes draft returns JSON and does not persist notes', function () {
    config(['ai.enabled' => true, 'ai.provider' => 'stub']);

    ['owner' => $owner, 'product' => $product, 'run' => $run] = makeSdlAiDraftFixture();

    $entry = $run->stageEntries()
        ->where('stage', SdlStage::ThreatReview)
        ->firstOrFail();
    $originalNotes = $entry->notes;

    $this->actingAs($owner)
        ->postJson(route('products.sdl.ai-draft', [$product, $run]), [
            'stage' => SdlStage::ThreatReview->value,
            'current_notes' => 'Existing notes',
        ])
        ->assertOk()
        ->assertJsonPath('human_review_required', true)
        ->assertJsonPath('stage', SdlStage::ThreatReview->value)
        ->assertJsonPath('notes_markdown', fn($value) => is_string($value) && str_contains($value, 'stub draft'));

    expect($entry->fresh()->notes)->toBe($originalNotes);
});

test('viewer cannot request AI SDL stage notes draft', function () {
    config(['ai.enabled' => true, 'ai.provider' => 'stub']);

    ['viewer' => $viewer, 'product' => $product, 'run' => $run] = makeSdlAiDraftFixture();

    $this->actingAs($viewer)
        ->postJson(route('products.sdl.ai-draft', [$product, $run]), [
            'stage' => SdlStage::ThreatReview->value,
            'current_notes' => 'Should fail',
        ])
        ->assertForbidden();
});

test('AI SDL stage notes draft requires AI enabled', function () {
    config(['ai.enabled' => false, 'ai.provider' => 'stub']);

    ['owner' => $owner, 'product' => $product, 'run' => $run] = makeSdlAiDraftFixture();

    $this->actingAs($owner)
        ->postJson(route('products.sdl.ai-draft', [$product, $run]), [
            'stage' => SdlStage::ThreatReview->value,
        ])
        ->assertUnprocessable();
});

test('SDL edit page exposes aiEnabled flag', function () {
    config(['ai.enabled' => true, 'ai.provider' => 'stub']);

    ['owner' => $owner, 'product' => $product, 'run' => $run] = makeSdlAiDraftFixture();

    $this->actingAs($owner)
        ->get(route('products.sdl.edit', [$product, $run]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/sdl/Edit')
            ->where('aiEnabled', true)
            ->where('canManage', true));
});
