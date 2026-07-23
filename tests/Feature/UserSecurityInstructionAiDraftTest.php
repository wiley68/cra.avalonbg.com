<?php

use App\Enums\AuditEventType;
use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\UserSecurityInstructionSectionKey;
use App\Enums\UserSecurityInstructionStatus;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\UserSecurityInstruction;
use App\Services\Ai\AiUsiSectionDraftParser;
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
 *     draft: UserSecurityInstruction,
 *     published: UserSecurityInstruction
 * }
 */
function makeUsiAiDraftFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'USI AI Draft Org',
        'slug' => 'usi-ai-draft-org-' . uniqid(),
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
        'name' => 'USI AI Draft Product',
        'slug' => 'usi-ai-draft-product-' . uniqid(),
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    test()->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'title' => 'AI draft guide',
            'version_label' => '0.1',
            'locale' => 'en',
        ])
        ->assertRedirect();

    $draft = UserSecurityInstruction::query()
        ->where('product_id', $product->id)
        ->where('title', 'AI draft guide')
        ->firstOrFail()
        ->load('sections');

    test()->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'use_template' => true,
            'locale' => 'en',
        ])
        ->assertRedirect();

    $published = UserSecurityInstruction::query()
        ->where('product_id', $product->id)
        ->where('title', 'User security instructions')
        ->firstOrFail()
        ->load('sections');

    $published->sections()->update(['is_applicable' => false, 'body' => '']);
    $published->sections()
        ->where('section_key', UserSecurityInstructionSectionKey::SecureInstallation->value)
        ->update([
            'is_applicable' => true,
            'body' => 'Install securely for publish fixture.',
        ]);

    test()->actingAs($owner)
        ->post(route('products.security-instructions.publish', [$product, $published]))
        ->assertRedirect();

    $published->refresh();

    return compact('organization', 'owner', 'viewer', 'product', 'draft', 'published');
}

test('AiUsiSectionDraftParser extracts fenced JSON', function () {
    $raw = <<<'TXT'
Suggestion:
```json
{"section_key":"logging","body_markdown":"## Logging\nEnable audit logs.","human_review_required":true,"disclaimer":"Draft only"}
```
TXT;

    $parsed = AiUsiSectionDraftParser::parse(
        $raw,
        UserSecurityInstructionSectionKey::Logging,
    );

    expect($parsed)->not->toBeNull()
        ->and($parsed['section_key'])->toBe('logging')
        ->and($parsed['body_markdown'])->toContain('audit logs')
        ->and($parsed['human_review_required'])->toBeTrue();
});

test('suggestUsiSectionDraft returns stub draft without writing section body', function () {
    config(['ai.enabled' => true, 'ai.provider' => 'stub']);

    ['owner' => $owner, 'product' => $product, 'draft' => $draft] = makeUsiAiDraftFixture();

    $section = $draft->sections
        ->firstWhere('section_key', UserSecurityInstructionSectionKey::SecureInstallation);
    expect($section)->not->toBeNull();

    $originalBody = $section->body;

    $result = app(AiAssistantService::class)->suggestUsiSectionDraft(
        $product,
        $draft,
        $owner,
        UserSecurityInstructionSectionKey::SecureInstallation,
        'Existing install notes',
        'Keep short',
    );

    expect($result['draft']['human_review_required'])->toBeTrue()
        ->and($result['draft']['section_key'])->toBe('secure_installation')
        ->and($result['draft']['body_markdown'])->toContain('stub draft')
        ->and($result['draft']['body_markdown'])->toContain('human review')
        ->and($section->fresh()->body)->toBe($originalBody);

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::AiUsiSectionDraftSuggested->value)
        ->where('product_id', $product->id)
        ->exists())->toBeTrue();

    $log = AuditLog::query()
        ->where('event_type', AuditEventType::AiUsiSectionDraftSuggested->value)
        ->first();

    expect($log->description)->toContain('secure_installation')
        ->and($log->description)->toContain((string) $draft->id)
        ->and($log->description)->not->toContain('Keep short');
});

test('HTTP AI section draft returns JSON and does not persist body', function () {
    config(['ai.enabled' => true, 'ai.provider' => 'stub']);

    ['owner' => $owner, 'product' => $product, 'draft' => $draft] = makeUsiAiDraftFixture();

    $section = $draft->sections
        ->firstWhere('section_key', UserSecurityInstructionSectionKey::Logging);
    $originalBody = $section->body;

    $this->actingAs($owner)
        ->postJson(route('products.security-instructions.ai-draft', [$product, $draft]), [
            'section_key' => UserSecurityInstructionSectionKey::Logging->value,
            'current_body' => 'Current logging body',
            'note' => 'Focus on retention',
        ])
        ->assertOk()
        ->assertJsonPath('section_key', 'logging')
        ->assertJsonPath('human_review_required', true)
        ->assertJsonFragment(['provider' => 'stub']);

    expect($section->fresh()->body)->toBe($originalBody)
        ->and($draft->fresh()->status)->toBe(UserSecurityInstructionStatus::Draft);
});

test('edit page exposes aiEnabled when AI is on', function () {
    config(['ai.enabled' => true, 'ai.provider' => 'stub']);

    ['owner' => $owner, 'product' => $product, 'draft' => $draft] = makeUsiAiDraftFixture();

    $this->actingAs($owner)
        ->get(route('products.security-instructions.edit', [$product, $draft]))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('products/user-security-instructions/Edit')
            ->where('aiEnabled', true));
});

test('viewer cannot request AI section draft', function () {
    config(['ai.enabled' => true, 'ai.provider' => 'stub']);

    ['viewer' => $viewer, 'product' => $product, 'draft' => $draft] = makeUsiAiDraftFixture();

    $this->actingAs($viewer)
        ->postJson(route('products.security-instructions.ai-draft', [$product, $draft]), [
            'section_key' => UserSecurityInstructionSectionKey::Logging->value,
        ])
        ->assertForbidden();
});

test('AI section draft rejects published instructions', function () {
    config(['ai.enabled' => true, 'ai.provider' => 'stub']);

    ['owner' => $owner, 'product' => $product, 'published' => $published] = makeUsiAiDraftFixture();

    expect($published->status)->toBe(UserSecurityInstructionStatus::Published);

    $this->actingAs($owner)
        ->postJson(route('products.security-instructions.ai-draft', [$product, $published]), [
            'section_key' => UserSecurityInstructionSectionKey::SecureInstallation->value,
        ])
        ->assertUnprocessable();
});

test('AI section draft requires AI enabled', function () {
    config(['ai.enabled' => false, 'ai.provider' => 'stub']);

    ['owner' => $owner, 'product' => $product, 'draft' => $draft] = makeUsiAiDraftFixture();

    $this->actingAs($owner)
        ->postJson(route('products.security-instructions.ai-draft', [$product, $draft]), [
            'section_key' => UserSecurityInstructionSectionKey::Logging->value,
        ])
        ->assertUnprocessable();
});
