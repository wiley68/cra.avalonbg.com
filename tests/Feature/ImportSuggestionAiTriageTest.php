<?php

use App\Enums\AuditEventType;
use App\Enums\ClassificationStatus;
use App\Enums\ImportSuggestionKind;
use App\Enums\ImportSuggestionStatus;
use App\Enums\IntegrationAuthType;
use App\Enums\IntegrationCategory;
use App\Enums\IntegrationConnectionStatus;
use App\Enums\IntegrationProvider;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Models\AuditLog;
use App\Models\ImportSuggestion;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Models\Product;
use App\Models\ProductIntegrationLink;
use App\Models\ProductVulnerability;
use App\Models\Role;
use App\Models\User;
use App\Services\Ai\AiImportedFindingTriageParser;
use App\Services\AiAssistantService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     organization: Organization,
 *     owner: User,
 *     viewer: User,
 *     product: Product,
 *     suggestion: ImportSuggestion
 * }
 */
function makeImportedFindingAiTriageFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Finding AI Triage Org',
        'slug' => 'finding-ai-triage-org-' . uniqid(),
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
        'name' => 'Finding AI Triage Product',
        'slug' => 'finding-ai-triage-product-' . uniqid(),
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

    $integration = OrganizationIntegration::query()->create([
        'organization_id' => $organization->id,
        'provider' => IntegrationProvider::Snyk,
        'category' => IntegrationCategory::Scanner,
        'auth_type' => IntegrationAuthType::ApiToken,
        'credentials' => ['api_token' => 'snyk_api_token'],
        'label' => 'Work Snyk',
        'status' => IntegrationConnectionStatus::Active,
        'last_verified_at' => now(),
    ]);

    $link = ProductIntegrationLink::query()->create([
        'product_id' => $product->id,
        'integration_id' => $integration->id,
        'external_project_key' => 'org-1',
        'external_target_id' => 'proj-1',
        'external_label' => 'Acme App',
    ]);

    $suggestion = ImportSuggestion::query()->create([
        'product_id' => $product->id,
        'link_id' => $link->id,
        'kind' => ImportSuggestionKind::Vulnerability,
        'external_id' => 'issue-ai-1',
        'title' => 'lodash: Prototype Pollution',
        'payload' => [
            'title' => 'lodash: Prototype Pollution',
            'summary' => 'Package: lodash@4.17.20',
            'cve_id' => 'CVE-2026-4242',
            'severity' => 'high',
            'package_name' => 'lodash@4.17.20',
            'html_url' => 'https://app.snyk.io/org/org-1/project/proj-1#issue-issue-ai-1',
        ],
        'status' => ImportSuggestionStatus::Pending,
    ]);

    return compact('organization', 'owner', 'viewer', 'product', 'suggestion');
}

test('AiImportedFindingTriageParser extracts summary and severity', function () {
    $parsed = AiImportedFindingTriageParser::parse(<<<'JSON'
{
  "summary_markdown": "## lodash finding\n\nStub triage with human review.",
  "suggested_severity": "high",
  "human_review_required": true,
  "disclaimer": "Draft only"
}
JSON);

    expect($parsed)->not->toBeNull()
        ->and($parsed['summary_markdown'])->toContain('Stub triage')
        ->and($parsed['suggested_severity'])->toBe('high')
        ->and($parsed['human_review_required'])->toBeTrue();
});

test('suggestImportedFindingTriageSummary returns stub draft without accepting', function () {
    config(['ai.enabled' => true, 'ai.provider' => 'stub']);

    ['owner' => $owner, 'product' => $product, 'suggestion' => $suggestion] = makeImportedFindingAiTriageFixture();

    $result = app(AiAssistantService::class)->suggestImportedFindingTriageSummary(
        $product,
        $suggestion,
        $owner,
        'Focus on package impact',
        'en',
    );

    expect($result['draft']['human_review_required'])->toBeTrue()
        ->and($result['draft']['summary_markdown'])->toContain('stub triage')
        ->and($result['draft']['summary_markdown'])->toContain('auto-accepted')
        ->and($result['draft']['suggested_severity'])->toBe('high')
        ->and($suggestion->fresh()->status)->toBe(ImportSuggestionStatus::Pending)
        ->and(ProductVulnerability::query()->count())->toBe(0);

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::AiImportedFindingTriageSuggested->value)
        ->where('product_id', $product->id)
        ->exists())->toBeTrue();

    $log = AuditLog::query()
        ->where('event_type', AuditEventType::AiImportedFindingTriageSuggested->value)
        ->first();

    expect($log->description)->toContain((string) $suggestion->id)
        ->and($log->description)->not->toContain('Focus on package impact');
});

test('HTTP AI imported finding triage returns JSON and does not accept', function () {
    config(['ai.enabled' => true, 'ai.provider' => 'stub']);

    ['owner' => $owner, 'product' => $product, 'suggestion' => $suggestion] = makeImportedFindingAiTriageFixture();

    $this->actingAs($owner)
        ->postJson(route('products.import-suggestions.ai-triage', [$product, $suggestion]))
        ->assertOk()
        ->assertJsonPath('human_review_required', true)
        ->assertJsonPath('suggested_severity', 'high')
        ->assertJsonPath('summary_markdown', fn($value) => is_string($value) && str_contains($value, 'stub triage'));

    expect($suggestion->fresh()->status)->toBe(ImportSuggestionStatus::Pending)
        ->and(ProductVulnerability::query()->count())->toBe(0);
});

test('viewer cannot request AI imported finding triage', function () {
    config(['ai.enabled' => true, 'ai.provider' => 'stub']);

    ['viewer' => $viewer, 'product' => $product, 'suggestion' => $suggestion] = makeImportedFindingAiTriageFixture();

    $this->actingAs($viewer)
        ->postJson(route('products.import-suggestions.ai-triage', [$product, $suggestion]))
        ->assertForbidden();
});

test('AI imported finding triage requires AI enabled', function () {
    config(['ai.enabled' => false, 'ai.provider' => 'stub']);

    ['owner' => $owner, 'product' => $product, 'suggestion' => $suggestion] = makeImportedFindingAiTriageFixture();

    $this->actingAs($owner)
        ->postJson(route('products.import-suggestions.ai-triage', [$product, $suggestion]))
        ->assertUnprocessable();
});

test('AI triage rejects non-vulnerability import suggestions', function () {
    config(['ai.enabled' => true, 'ai.provider' => 'stub']);

    ['owner' => $owner, 'product' => $product, 'suggestion' => $suggestion] = makeImportedFindingAiTriageFixture();

    $suggestion->update([
        'kind' => ImportSuggestionKind::Task,
        'title' => 'Jira task',
    ]);

    $this->actingAs($owner)
        ->postJson(route('products.import-suggestions.ai-triage', [$product, $suggestion]))
        ->assertStatus(422);

    expect($suggestion->fresh()->status)->toBe(ImportSuggestionStatus::Pending);
});

test('product edit page exposes aiEnabled flag', function () {
    config(['ai.enabled' => true, 'ai.provider' => 'stub']);

    ['owner' => $owner, 'product' => $product] = makeImportedFindingAiTriageFixture();

    $this->actingAs($owner)
        ->get(route('products.edit', $product))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('products/Edit')
            ->where('aiEnabled', true));
});
