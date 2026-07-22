<?php

use App\Enums\AiConversationContextType;
use App\Enums\AuditEventType;
use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\Ai\AiSuggestionsParser;
use App\Services\AiAssistantService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User, product: Product}
 */
function makeDocumentAnalyseFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'AI Analyse Org',
        'slug' => 'ai-analyse-org',
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
        'name' => 'AI Analyse Product',
        'slug' => 'ai-analyse-product',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => false,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    return compact('organization', 'owner', 'product');
}

test('AiSuggestionsParser extracts JSON object from fenced content', function () {
    $raw = <<<'TXT'
Here you go:
```json
{"document_summary":"Policy draft","document_kind_guess":"security_policy","requirement_mappings":[],"evidence_mappings":[],"gaps":[],"human_review_required":true,"disclaimer":"Review me"}
```
TXT;

    $parsed = AiSuggestionsParser::parse($raw);

    expect($parsed)->not->toBeNull()
        ->and($parsed['document_summary'])->toBe('Policy draft')
        ->and($parsed['document_kind_guess'])->toBe('security_policy')
        ->and($parsed['human_review_required'])->toBeTrue();
});

test('analyseDocument persists structured suggestions without applying mappings', function () {
    config(['ai.enabled' => true, 'ai.provider' => 'stub']);

    ['owner' => $owner, 'product' => $product] = makeDocumentAnalyseFixture();

    $file = UploadedFile::fake()->createWithContent(
        'vuln-policy.md',
        "# Vulnerability disclosure\n\nWe publish advisories within 30 days.\n",
    );

    $result = app(AiAssistantService::class)->analyseDocument(
        $product,
        $owner,
        $file,
        'Focus on CVD coverage',
    );

    expect($result['conversation']->context_type)->toBe(AiConversationContextType::DocumentAnalyser)
        ->and($result['suggestions'])->toBeArray()
        ->and($result['suggestions']['human_review_required'])->toBeTrue()
        ->and($result['suggestions']['evidence_mappings'])->not->toBeEmpty()
        ->and($result['assistant_message']->metadata['suggestions_parsed'])->toBeTrue()
        ->and($result['assistant_message']->content)->toContain('human review');

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::AiDocumentAnalysed->value)
        ->where('product_id', $product->id)
        ->exists())->toBeTrue();

    $log = AuditLog::query()
        ->where('event_type', AuditEventType::AiDocumentAnalysed->value)
        ->first();

    expect($log->description)->toContain('vuln-policy.md')
        ->and($log->description)->not->toContain('We publish advisories within 30 days');
});

test('HTTP document analyse creates conversation and returns suggestions metadata', function () {
    config(['ai.enabled' => true, 'ai.provider' => 'stub']);

    ['owner' => $owner, 'product' => $product] = makeDocumentAnalyseFixture();

    $file = UploadedFile::fake()->createWithContent(
        'architecture.txt',
        "Architecture overview with network boundaries.\n",
    );

    $this->actingAs($owner)
        ->post(route('products.assistant.analyse', $product), [
            'file' => $file,
            'note' => 'Look for architecture evidence',
        ])
        ->assertRedirect();

    $conversation = AiConversation::query()
        ->where('product_id', $product->id)
        ->where('user_id', $owner->id)
        ->where('context_type', AiConversationContextType::DocumentAnalyser)
        ->latest('id')
        ->first();

    expect($conversation)->not->toBeNull()
        ->and(AiMessage::query()->where('conversation_id', $conversation->id)->count())->toBe(2);

    $this->actingAs($owner)
        ->get(route('products.assistant.conversations.show', [
            'product' => $product,
            'conversation' => $conversation,
        ]))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('products/assistant/Show')
            ->where('conversation.context_type', AiConversationContextType::DocumentAnalyser->value)
            ->where('conversation.messages.1.metadata.suggestions_parsed', true)
            ->has('conversation.messages.1.metadata.suggestions.evidence_mappings'));
});

test('document analyse rejects unsupported binary-like extension', function () {
    config(['ai.enabled' => true, 'ai.provider' => 'stub']);

    ['owner' => $owner, 'product' => $product] = makeDocumentAnalyseFixture();

    $file = UploadedFile::fake()->create('report.pdf', 100, 'application/pdf');

    $this->actingAs($owner)
        ->from(route('products.assistant.show', $product))
        ->post(route('products.assistant.analyse', $product), [
            'file' => $file,
        ])
        ->assertSessionHasErrors('file');

    expect(AiConversation::query()->count())->toBe(0);
});
