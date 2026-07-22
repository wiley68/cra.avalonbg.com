<?php

use App\Enums\AiProviderDriver;
use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User, viewer: User, outsider: User, product: Product}
 */
function makeAssistantUiFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'AI UI Org',
        'slug' => 'ai-ui-org',
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

    $outsider = User::factory()->create([
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

    $otherOrg = Organization::query()->create([
        'name' => 'Other AI Org',
        'slug' => 'other-ai-org',
        'is_active' => true,
        'locale' => 'en',
    ]);
    $otherOrg->users()->attach($outsider->id, [
        'role_id' => $ownerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'AI UI Product',
        'slug' => 'ai-ui-product',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => false,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    return compact('organization', 'owner', 'viewer', 'outsider', 'product');
}

test('owner can open product assistant chat with disclaimer props', function () {
    config(['ai.enabled' => true]);

    ['owner' => $owner, 'product' => $product] = makeAssistantUiFixture();

    $this->actingAs($owner)
        ->get(route('products.assistant.show', $product))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('products/assistant/Show')
            ->where('ai_enabled', true)
            ->where('provider', AiProviderDriver::Stub->value)
            ->where('product.id', $product->id)
            ->where('conversation', null));
});

test('viewer can send a message and see persisted conversation', function () {
    config(['ai.enabled' => true]);

    ['viewer' => $viewer, 'product' => $product] = makeAssistantUiFixture();

    $this->actingAs($viewer)
        ->post(route('products.assistant.messages.store', $product), [
            'content' => 'What controls are incomplete?',
        ])
        ->assertRedirect();

    $conversation = AiConversation::query()
        ->where('product_id', $product->id)
        ->where('user_id', $viewer->id)
        ->first();

    expect($conversation)->not->toBeNull()
        ->and(AiMessage::query()->where('conversation_id', $conversation->id)->count())->toBe(2);

    $this->actingAs($viewer)
        ->get(route('products.assistant.conversations.show', [
            'product' => $product,
            'conversation' => $conversation,
        ]))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('products/assistant/Show')
            ->where('conversation.id', $conversation->id)
            ->has('conversation.messages', 2));
});

test('outsider cannot open another organization assistant', function () {
    config(['ai.enabled' => true]);

    ['outsider' => $outsider, 'product' => $product] = makeAssistantUiFixture();

    $this->actingAs($outsider)
        ->get(route('products.assistant.show', $product))
        ->assertNotFound();
});

test('assistant show works when AI is disabled but posting is rejected', function () {
    config(['ai.enabled' => false]);

    ['owner' => $owner, 'product' => $product] = makeAssistantUiFixture();

    $this->actingAs($owner)
        ->get(route('products.assistant.show', $product))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->where('ai_enabled', false));

    $this->actingAs($owner)
        ->from(route('products.assistant.show', $product))
        ->post(route('products.assistant.messages.store', $product), [
            'content' => 'Hello',
        ])
        ->assertSessionHasErrors('assistant');

    expect(AiConversation::query()->count())->toBe(0);
});
