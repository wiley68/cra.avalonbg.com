<?php

use App\Enums\AuditEventType;
use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\PolicyStatus;
use App\Enums\PolicyType;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrgPolicy;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\AiAssistantService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     organization: Organization,
 *     owner: User,
 *     auditor: User,
 *     viewer: User,
 *     product: Product
 * }
 */
function makeAiRbacFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'AI RBAC Org',
        'slug' => 'ai-rbac-org',
        'is_active' => true,
        'locale' => 'en',
    ]);

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $auditor = User::factory()->create([
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
    $auditorRole = Role::query()->where('slug', 'auditor')->firstOrFail();
    $viewerRole = Role::query()->where('slug', 'read_only')->firstOrFail();

    foreach ([
        [$owner, $ownerRole],
        [$auditor, $auditorRole],
        [$viewer, $viewerRole],
    ] as [$user, $role]) {
        $organization->users()->attach($user->id, [
            'role_id' => $role->id,
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'AI RBAC Product',
        'slug' => 'ai-rbac-product',
        'manufacturer' => 'Immutable Co',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
        'scope_rationale' => 'Baseline rationale',
    ]);

    return compact('organization', 'owner', 'auditor', 'viewer', 'product');
}

test('guests cannot open or post to the product assistant', function () {
    config(['ai.enabled' => true]);

    ['product' => $product] = makeAiRbacFixture();

    $this->get(route('products.assistant.show', $product))
        ->assertRedirect(route('login'));

    $this->post(route('products.assistant.messages.store', $product), [
        'content' => 'Guest probe',
    ])->assertRedirect(route('login'));

    expect(AiConversation::query()->count())->toBe(0);
});

test('assistant show includes section 6 disclaimer translation for the chat UI', function () {
    config(['ai.enabled' => true]);

    ['owner' => $owner, 'product' => $product] = makeAiRbacFixture();

    $this->actingAs($owner)
        ->get(route('products.assistant.show', $product))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('products/assistant/Show')
            ->where('locale', fn($locale) => $locale === 'en')
            ->missing('translations'));

    expect(\App\Support\Translations::get('products.assistant.disclaimer'))
        ->toBeString()
        ->toContain('human review');
});

test('auditor can ask read-only Q&A without mutating product or policies', function () {
    config(['ai.enabled' => true]);

    ['organization' => $organization, 'auditor' => $auditor, 'product' => $product] = makeAiRbacFixture();

    $policyCountBefore = OrgPolicy::query()->where('organization_id', $organization->id)->count();
    $productSnapshot = [
        'name' => $product->name,
        'slug' => $product->slug,
        'manufacturer' => $product->manufacturer,
        'scope_status' => $product->scope_status->value,
        'classification_status' => $product->classification_status->value,
        'scope_rationale' => $product->scope_rationale,
        'updated_at' => $product->updated_at?->toIso8601String(),
    ];

    $this->actingAs($auditor)
        ->get(route('products.assistant.show', $product))
        ->assertOk();

    $this->actingAs($auditor)
        ->post(route('products.assistant.messages.store', $product), [
            'content' => 'Is this product in CRA scope?',
        ])
        ->assertRedirect();

    $conversation = AiConversation::query()
        ->where('product_id', $product->id)
        ->where('user_id', $auditor->id)
        ->first();

    expect($conversation)->not->toBeNull()
        ->and(AiMessage::query()->where('conversation_id', $conversation->id)->count())->toBe(2);

    $product->refresh();

    expect([
        'name' => $product->name,
        'slug' => $product->slug,
        'manufacturer' => $product->manufacturer,
        'scope_status' => $product->scope_status->value,
        'classification_status' => $product->classification_status->value,
        'scope_rationale' => $product->scope_rationale,
        'updated_at' => $product->updated_at?->toIso8601String(),
    ])->toBe($productSnapshot)
        ->and(OrgPolicy::query()->where('organization_id', $organization->id)->count())
        ->toBe($policyCountBefore);
    expect(AuditLog::query()
        ->where('event_type', AuditEventType::AiRequestCompleted->value)
        ->where('user_id', $auditor->id)
        ->exists())->toBeTrue();
});

test('viewer cannot open another users conversation', function () {
    config(['ai.enabled' => true]);

    ['owner' => $owner, 'viewer' => $viewer, 'product' => $product] = makeAiRbacFixture();

    $service = app(AiAssistantService::class);
    $conversation = $service->startConversation($product, $owner);
    $service->sendMessage($conversation, $owner, 'Owner private thread');

    $this->actingAs($viewer)
        ->get(route('products.assistant.conversations.show', [
            'product' => $product,
            'conversation' => $conversation,
        ]))
        ->assertNotFound();
});

test('viewer can ask grounded Q&A and receives stub context without manage side effects', function () {
    config(['ai.enabled' => true]);

    ['organization' => $organization, 'owner' => $owner, 'viewer' => $viewer, 'product' => $product] = makeAiRbacFixture();

    OrgPolicy::query()->create([
        'organization_id' => $organization->id,
        'policy_type' => PolicyType::Support,
        'title' => 'Support Policy For Grounding',
        'status' => PolicyStatus::Approved,
        'version_label' => '1.0',
        'body' => 'Body stays in messages not product mutations',
        'approved_at' => now(),
        'approved_by' => $owner->id,
    ]);

    $productUpdatedAt = $product->updated_at?->toIso8601String();

    $this->actingAs($viewer)
        ->post(route('products.assistant.messages.store', $product), [
            'content' => 'Summarise support policy status',
        ])
        ->assertRedirect();

    $conversation = AiConversation::query()
        ->where('product_id', $product->id)
        ->where('user_id', $viewer->id)
        ->firstOrFail();

    $assistant = AiMessage::query()
        ->where('conversation_id', $conversation->id)
        ->where('role', 'assistant')
        ->firstOrFail();

    expect($assistant->content)->toContain('AI RBAC Product')
        ->and($assistant->content)->toContain('Support Policy For Grounding')
        ->and($product->fresh()->updated_at?->toIso8601String())->toBe($productUpdatedAt)
        ->and($product->fresh()->name)->toBe('AI RBAC Product');
});

test('message validation rejects empty content over HTTP', function () {
    config(['ai.enabled' => true]);

    ['viewer' => $viewer, 'product' => $product] = makeAiRbacFixture();

    $this->actingAs($viewer)
        ->from(route('products.assistant.show', $product))
        ->post(route('products.assistant.messages.store', $product), [
            'content' => '   ',
        ])
        ->assertSessionHasErrors('content');

    expect(AiConversation::query()->count())->toBe(0);
});
