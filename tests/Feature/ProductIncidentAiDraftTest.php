<?php

use App\Enums\AuditEventType;
use App\Enums\ClassificationStatus;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductIncident;
use App\Models\Role;
use App\Models\User;
use App\Services\Ai\AiIncidentSummaryDraftParser;
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
 *     incident: ProductIncident
 * }
 */
function makeIncidentAiDraftFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Incident AI Draft Org',
        'slug' => 'incident-ai-draft-org-' . uniqid(),
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
        'name' => 'Incident AI Draft Product',
        'slug' => 'incident-ai-draft-product-' . uniqid(),
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

    $incident = ProductIncident::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Auth anomaly cluster',
        'summary' => 'Initial short note.',
        'status' => IncidentStatus::Investigating,
        'severity' => IncidentSeverity::High,
        'root_cause' => 'Compromised credential',
        'corrective_measures' => 'Rotated tokens',
        'awareness_at' => now()->subHours(2),
        'owner_user_id' => $owner->id,
    ]);

    return compact('organization', 'owner', 'viewer', 'product', 'incident');
}

test('AiIncidentSummaryDraftParser extracts summary_markdown', function () {
    $parsed = AiIncidentSummaryDraftParser::parse(<<<'JSON'
{
  "summary_markdown": "## Auth anomaly\n\nStub summary with human review.",
  "human_review_required": true,
  "disclaimer": "Draft only"
}
JSON);

    expect($parsed)->not->toBeNull()
        ->and($parsed['summary_markdown'])->toContain('Stub summary')
        ->and($parsed['human_review_required'])->toBeTrue();
});

test('suggestIncidentSummaryDraft returns stub draft without writing summary', function () {
    config(['ai.enabled' => true, 'ai.provider' => 'stub']);

    ['owner' => $owner, 'product' => $product, 'incident' => $incident] = makeIncidentAiDraftFixture();

    $originalSummary = $incident->summary;

    $result = app(AiAssistantService::class)->suggestIncidentSummaryDraft(
        $product,
        $incident,
        $owner,
        'Existing summary notes',
        'Keep factual',
        'en',
    );

    expect($result['draft']['human_review_required'])->toBeTrue()
        ->and($result['draft']['summary_markdown'])->toContain('stub draft')
        ->and($result['draft']['summary_markdown'])->toContain('human review')
        ->and($incident->fresh()->summary)->toBe($originalSummary);

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::AiIncidentSummaryDraftSuggested->value)
        ->where('product_id', $product->id)
        ->exists())->toBeTrue();

    $log = AuditLog::query()
        ->where('event_type', AuditEventType::AiIncidentSummaryDraftSuggested->value)
        ->first();

    expect($log->description)->toContain((string) $incident->id)
        ->and($log->description)->not->toContain('Keep factual');
});

test('HTTP AI incident summary draft returns JSON and does not persist summary', function () {
    config(['ai.enabled' => true, 'ai.provider' => 'stub']);

    ['owner' => $owner, 'product' => $product, 'incident' => $incident] = makeIncidentAiDraftFixture();

    $originalSummary = $incident->summary;

    $this->actingAs($owner)
        ->postJson(route('products.incidents.ai-draft', [$product, $incident]), [
            'current_summary' => 'Existing summary notes',
        ])
        ->assertOk()
        ->assertJsonPath('human_review_required', true)
        ->assertJsonPath('summary_markdown', fn($value) => is_string($value) && str_contains($value, 'stub draft'));

    expect($incident->fresh()->summary)->toBe($originalSummary);
});

test('viewer cannot request AI incident summary draft', function () {
    config(['ai.enabled' => true, 'ai.provider' => 'stub']);

    ['viewer' => $viewer, 'product' => $product, 'incident' => $incident] = makeIncidentAiDraftFixture();

    $this->actingAs($viewer)
        ->postJson(route('products.incidents.ai-draft', [$product, $incident]), [
            'current_summary' => 'Should fail',
        ])
        ->assertForbidden();
});

test('AI incident summary draft requires AI enabled', function () {
    config(['ai.enabled' => false, 'ai.provider' => 'stub']);

    ['owner' => $owner, 'product' => $product, 'incident' => $incident] = makeIncidentAiDraftFixture();

    $this->actingAs($owner)
        ->postJson(route('products.incidents.ai-draft', [$product, $incident]))
        ->assertUnprocessable();
});

test('incident edit page exposes aiEnabled flag', function () {
    config(['ai.enabled' => true, 'ai.provider' => 'stub']);

    ['owner' => $owner, 'product' => $product, 'incident' => $incident] = makeIncidentAiDraftFixture();

    $this->actingAs($owner)
        ->get(route('products.incidents.edit', [$product, $incident]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/incidents/Edit')
            ->where('aiEnabled', true)
            ->where('canManage', true));
});
