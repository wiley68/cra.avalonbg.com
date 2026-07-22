<?php

use App\Enums\AuditEventType;
use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\PolicyStatus;
use App\Enums\PolicyType;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrgPolicy;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\AiAssistantService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User, product: Product}
 */
function makeAiAuditFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'AI Audit Org',
        'slug' => 'ai-audit-org',
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
        'name' => 'AI Audit Product',
        'slug' => 'ai-audit-product',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => false,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    return compact('organization', 'owner', 'product');
}

test('sendMessage writes ai_request_completed audit without prompt or context secrets', function () {
    config(['ai.enabled' => true]);

    ['organization' => $organization, 'owner' => $owner, 'product' => $product] = makeAiAuditFixture();

    OrgPolicy::query()->create([
        'organization_id' => $organization->id,
        'policy_type' => PolicyType::Update,
        'title' => 'Secret Policy Body Title',
        'status' => PolicyStatus::Approved,
        'version_label' => '1.0',
        'body' => 'TOP_SECRET_POLICY_BODY_SHOULD_NOT_APPEAR_IN_AUDIT',
        'approved_at' => now(),
        'approved_by' => $owner->id,
    ]);

    $prompt = 'UNIQUE_PROMPT_SECRET_PHRASE_XYZ';

    $service = app(AiAssistantService::class);
    $conversation = $service->startConversation($product, $owner);
    $result = $service->sendMessage($conversation, $owner, $prompt);

    $log = AuditLog::query()
        ->where('event_type', AuditEventType::AiRequestCompleted->value)
        ->where('organization_id', $organization->id)
        ->where('product_id', $product->id)
        ->where('user_id', $owner->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->is_success)->toBeTrue()
        ->and($log->description)->toContain('conversation_id')
        ->and($log->description)->toContain((string) $conversation->id)
        ->and($log->description)->toContain((string) $result['user_message']->id)
        ->and($log->description)->toContain((string) $result['assistant_message']->id)
        ->and($log->description)->toContain('stub')
        ->and($log->description)->toContain('has_context')
        ->and($log->description)->not->toContain($prompt)
        ->and($log->description)->not->toContain('TOP_SECRET_POLICY_BODY_SHOULD_NOT_APPEAR_IN_AUDIT')
        ->and($log->description)->not->toContain('UNIQUE_PROMPT');
});

test('HTTP assistant message also creates ai_request_completed audit', function () {
    config(['ai.enabled' => true]);

    ['organization' => $organization, 'owner' => $owner, 'product' => $product] = makeAiAuditFixture();

    $this->actingAs($owner)
        ->post(route('products.assistant.messages.store', $product), [
            'content' => 'HTTP_AUDIT_PROMPT_SHOULD_STAY_OUT',
        ])
        ->assertRedirect();

    $log = AuditLog::query()
        ->where('event_type', AuditEventType::AiRequestCompleted->value)
        ->where('organization_id', $organization->id)
        ->where('product_id', $product->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->description)->not->toContain('HTTP_AUDIT_PROMPT_SHOULD_STAY_OUT');
});
