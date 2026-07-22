<?php

use App\Enums\AiConversationContextType;
use App\Enums\AiDraftType;
use App\Enums\AuditEventType;
use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\PatchCampaignStatus;
use App\Enums\ProductType;
use App\Enums\ProductVersionState;
use App\Enums\ScopeStatus;
use App\Enums\SupportStatus;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\PatchCampaign;
use App\Models\Product;
use App\Models\ProductVersion;
use App\Models\Role;
use App\Models\User;
use App\Services\Ai\AiDraftParser;
use App\Services\AiAssistantService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User, product: Product, campaign: PatchCampaign}
 */
function makeDraftGeneratorFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'AI Draft Org',
        'slug' => 'ai-draft-org',
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
        'name' => 'AI Draft Product',
        'slug' => 'ai-draft-product',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => false,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    $version = ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '2.0.0',
        'state' => ProductVersionState::Released,
        'support_status' => SupportStatus::Supported,
    ]);

    $campaign = PatchCampaign::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'target_version_id' => $version->id,
        'title' => 'July security roll-out',
        'status' => PatchCampaignStatus::Active,
        'notes' => 'Apply patch to production first.',
        'created_by' => $owner->id,
        'started_at' => now(),
    ]);

    return compact('organization', 'owner', 'product', 'campaign');
}

test('AiDraftParser extracts JSON object from fenced content', function () {
    $raw = <<<'TXT'
Draft:
```json
{"draft_type":"security_advisory","subject":"Advisory","body_markdown":"## Body","body_plain":"Body","highlights":["A"],"affected_summary":null,"recommended_actions":["Review"],"human_review_required":true,"disclaimer":"Draft only"}
```
TXT;

    $parsed = AiDraftParser::parse($raw, AiDraftType::SecurityAdvisory);

    expect($parsed)->not->toBeNull()
        ->and($parsed['draft_type'])->toBe('security_advisory')
        ->and($parsed['subject'])->toBe('Advisory')
        ->and($parsed['human_review_required'])->toBeTrue();
});

test('generateDraft persists structured draft without sending', function () {
    config(['ai.enabled' => true, 'ai.provider' => 'stub']);

    ['owner' => $owner, 'product' => $product, 'campaign' => $campaign] = makeDraftGeneratorFixture();

    $result = app(AiAssistantService::class)->generateDraft(
        $product,
        $owner,
        $campaign,
        AiDraftType::CustomerNotification,
        'Focus on production',
    );

    expect($result['conversation']->context_type)->toBe(AiConversationContextType::Draft)
        ->and($result['draft'])->toBeArray()
        ->and($result['draft']['human_review_required'])->toBeTrue()
        ->and($result['draft']['campaign_id'])->toBe($campaign->id)
        ->and($result['assistant_message']->metadata['draft_parsed'])->toBeTrue()
        ->and($result['assistant_message']->content)->toContain('human review')
        ->and($result['assistant_message']->content)->toContain('not sent');

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::AiDraftGenerated->value)
        ->where('product_id', $product->id)
        ->exists())->toBeTrue();

    $log = AuditLog::query()
        ->where('event_type', AuditEventType::AiDraftGenerated->value)
        ->first();

    expect($log->description)->toContain((string) $campaign->id)
        ->and($log->description)->toContain('customer_notification')
        ->and($log->description)->not->toContain('Apply patch to production first');
});

test('HTTP draft generate creates conversation and returns draft metadata', function () {
    config(['ai.enabled' => true, 'ai.provider' => 'stub']);

    ['owner' => $owner, 'product' => $product, 'campaign' => $campaign] = makeDraftGeneratorFixture();

    $this->actingAs($owner)
        ->post(route('products.assistant.draft', $product), [
            'campaign_id' => $campaign->id,
            'draft_type' => AiDraftType::SecurityAdvisory->value,
            'note' => 'Keep it short',
        ])
        ->assertRedirect();

    $conversation = AiConversation::query()
        ->where('product_id', $product->id)
        ->where('user_id', $owner->id)
        ->where('context_type', AiConversationContextType::Draft)
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
            ->where('conversation.context_type', AiConversationContextType::Draft->value)
            ->where('conversation.messages.1.metadata.draft_parsed', true)
            ->has('conversation.messages.1.metadata.draft.subject'));
});

test('draft generate rejects campaign from another product', function () {
    config(['ai.enabled' => true, 'ai.provider' => 'stub']);

    ['owner' => $owner, 'product' => $product] = makeDraftGeneratorFixture();

    $otherProduct = Product::query()->create([
        'organization_id' => $product->organization_id,
        'name' => 'Other Product',
        'slug' => 'other-draft-product',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => false,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    $otherVersion = ProductVersion::query()->create([
        'product_id' => $otherProduct->id,
        'version_number' => '9.9.9',
        'state' => ProductVersionState::Released,
        'support_status' => SupportStatus::Supported,
    ]);

    $foreignCampaign = PatchCampaign::query()->create([
        'organization_id' => $product->organization_id,
        'product_id' => $otherProduct->id,
        'target_version_id' => $otherVersion->id,
        'title' => 'Foreign campaign',
        'status' => PatchCampaignStatus::Draft,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->from(route('products.campaigns.show', [
            'product' => $product,
            'campaign' => PatchCampaign::query()->where('product_id', $product->id)->firstOrFail(),
        ]))
        ->post(route('products.assistant.draft', $product), [
            'campaign_id' => $foreignCampaign->id,
            'draft_type' => AiDraftType::CustomerNotification->value,
        ])
        ->assertSessionHasErrors('campaign_id');

    expect(AiConversation::query()->count())->toBe(0);
});
