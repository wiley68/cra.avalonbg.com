<?php

use App\Enums\AuditEventType;
use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\TechnicalDocumentationSectionKey;
use App\Enums\TechnicalDocumentationStatus;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Role;
use App\Models\TechnicalDocumentationPackage;
use App\Models\User;
use App\Services\Ai\AiTechDocSectionDraftParser;
use App\Services\AiAssistantService;
use App\Services\TechnicalDocumentationService;
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
 *     draft: TechnicalDocumentationPackage,
 *     published: TechnicalDocumentationPackage
 * }
 */
function makeTechDocAiDraftFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Tech Doc AI Draft Org',
        'slug' => 'tech-doc-ai-draft-org-' . uniqid(),
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
        'name' => 'Tech Doc AI Draft Product',
        'slug' => 'tech-doc-ai-draft-product-' . uniqid(),
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    $packages = app(TechnicalDocumentationService::class);

    $draft = $packages->create($product, [
        'title' => 'AI draft package',
        'version_label' => '0.1',
        'locale' => 'en',
        'notes' => null,
        'inherit_from_previous' => false,
        'product_version_id' => null,
        'user_security_instruction_id' => null,
        'sdl_run_id' => null,
    ], $owner)->load('sections');

    $published = $packages->create($product, [
        'title' => 'Published package',
        'version_label' => '1.0',
        'locale' => 'en',
        'notes' => null,
        'inherit_from_previous' => false,
        'product_version_id' => null,
        'user_security_instruction_id' => null,
        'sdl_run_id' => null,
    ], $owner)->load('sections');

    foreach ($published->sections as $section) {
        if ($section->section_key->defaultSource()->value === 'authored') {
            $section->update([
                'body_markdown' => 'Authored content for ' . $section->section_key->value,
                'is_applicable' => true,
            ]);
        } else {
            $section->update([
                'is_applicable' => false,
                'override_reason' => 'N/A for publish fixture',
                'body_markdown' => null,
            ]);
        }
    }

    $published->update([
        'status' => TechnicalDocumentationStatus::Published,
        'published_at' => now(),
        'published_by' => $owner->id,
    ]);

    return compact('organization', 'owner', 'viewer', 'product', 'draft', 'published');
}

test('AiTechDocSectionDraftParser extracts fenced JSON', function () {
    $raw = <<<'TXT'
Suggestion:
```json
{"section_key":"architecture","body_markdown":"## Architecture\nLayered gateway.","human_review_required":true,"disclaimer":"Draft only"}
```
TXT;

    $parsed = AiTechDocSectionDraftParser::parse(
        $raw,
        TechnicalDocumentationSectionKey::Architecture,
    );

    expect($parsed)->not->toBeNull()
        ->and($parsed['section_key'])->toBe('architecture')
        ->and($parsed['body_markdown'])->toContain('Layered gateway')
        ->and($parsed['human_review_required'])->toBeTrue();
});

test('suggestTechDocSectionDraft returns stub draft without writing section body', function () {
    config(['ai.enabled' => true, 'ai.provider' => 'stub']);

    ['owner' => $owner, 'product' => $product, 'draft' => $draft] = makeTechDocAiDraftFixture();

    $section = $draft->sections
        ->firstWhere('section_key', TechnicalDocumentationSectionKey::Architecture);
    expect($section)->not->toBeNull();

    $originalBody = $section->body_markdown;

    $result = app(AiAssistantService::class)->suggestTechDocSectionDraft(
        $product,
        $draft,
        $owner,
        TechnicalDocumentationSectionKey::Architecture,
        'Existing architecture notes',
        'Keep short',
    );

    expect($result['draft']['human_review_required'])->toBeTrue()
        ->and($result['draft']['section_key'])->toBe('architecture')
        ->and($result['draft']['body_markdown'])->toContain('stub draft')
        ->and($result['draft']['body_markdown'])->toContain('human review')
        ->and($section->fresh()->body_markdown)->toBe($originalBody);

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::AiTechDocSectionDraftSuggested->value)
        ->where('product_id', $product->id)
        ->exists())->toBeTrue();

    $log = AuditLog::query()
        ->where('event_type', AuditEventType::AiTechDocSectionDraftSuggested->value)
        ->first();

    expect($log->description)->toContain('architecture')
        ->and($log->description)->toContain((string) $draft->id)
        ->and($log->description)->not->toContain('Keep short');
});

test('HTTP AI section draft returns JSON and does not persist body', function () {
    config(['ai.enabled' => true, 'ai.provider' => 'stub']);

    ['owner' => $owner, 'product' => $product, 'draft' => $draft] = makeTechDocAiDraftFixture();

    $section = $draft->sections
        ->firstWhere('section_key', TechnicalDocumentationSectionKey::ProductDescription);
    $originalBody = $section->body_markdown;

    $this->actingAs($owner)
        ->postJson(route('products.technical-documentation.ai-draft', [$product, $draft]), [
            'section_key' => TechnicalDocumentationSectionKey::ProductDescription->value,
            'current_body' => 'Current product description',
            'note' => 'Focus on intended use',
        ])
        ->assertOk()
        ->assertJsonPath('section_key', 'product_description')
        ->assertJsonPath('human_review_required', true)
        ->assertJsonFragment(['provider' => 'stub']);

    expect($section->fresh()->body_markdown)->toBe($originalBody)
        ->and($draft->fresh()->status)->toBe(TechnicalDocumentationStatus::Draft);
});

test('edit page exposes aiEnabled when AI is on', function () {
    config(['ai.enabled' => true, 'ai.provider' => 'stub']);

    ['owner' => $owner, 'product' => $product, 'draft' => $draft] = makeTechDocAiDraftFixture();

    $this->actingAs($owner)
        ->get(route('products.technical-documentation.edit', [$product, $draft]))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('products/technical-documentation/Edit')
            ->where('aiEnabled', true));
});

test('viewer cannot request AI section draft', function () {
    config(['ai.enabled' => true, 'ai.provider' => 'stub']);

    ['viewer' => $viewer, 'product' => $product, 'draft' => $draft] = makeTechDocAiDraftFixture();

    $this->actingAs($viewer)
        ->postJson(route('products.technical-documentation.ai-draft', [$product, $draft]), [
            'section_key' => TechnicalDocumentationSectionKey::Architecture->value,
        ])
        ->assertForbidden();
});

test('AI section draft rejects published packages', function () {
    config(['ai.enabled' => true, 'ai.provider' => 'stub']);

    ['owner' => $owner, 'product' => $product, 'published' => $published] = makeTechDocAiDraftFixture();

    expect($published->status)->toBe(TechnicalDocumentationStatus::Published);

    $this->actingAs($owner)
        ->postJson(route('products.technical-documentation.ai-draft', [$product, $published]), [
            'section_key' => TechnicalDocumentationSectionKey::Architecture->value,
        ])
        ->assertUnprocessable();
});

test('AI section draft rejects generated section keys', function () {
    config(['ai.enabled' => true, 'ai.provider' => 'stub']);

    ['owner' => $owner, 'product' => $product, 'draft' => $draft] = makeTechDocAiDraftFixture();

    $this->actingAs($owner)
        ->postJson(route('products.technical-documentation.ai-draft', [$product, $draft]), [
            'section_key' => TechnicalDocumentationSectionKey::Sbom->value,
        ])
        ->assertUnprocessable();
});

test('AI section draft requires AI enabled', function () {
    config(['ai.enabled' => false, 'ai.provider' => 'stub']);

    ['owner' => $owner, 'product' => $product, 'draft' => $draft] = makeTechDocAiDraftFixture();

    $this->actingAs($owner)
        ->postJson(route('products.technical-documentation.ai-draft', [$product, $draft]), [
            'section_key' => TechnicalDocumentationSectionKey::Architecture->value,
        ])
        ->assertUnprocessable();
});
