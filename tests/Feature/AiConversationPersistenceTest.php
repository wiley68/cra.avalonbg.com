<?php

use App\Enums\AiConversationContextType;
use App\Enums\AiMessageRole;
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
use App\Services\AiAssistantService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User, other: User, product: Product}
 */
function makeAiPersistenceFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'AI Persist Org',
        'slug' => 'ai-persist-org',
        'is_active' => true,
        'locale' => 'en',
    ]);

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $other = User::factory()->create([
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
    $organization->users()->attach($other->id, [
        'role_id' => $ownerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'AI Persist Product',
        'slug' => 'ai-persist-product',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => false,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    return compact('organization', 'owner', 'other', 'product');
}

test('startConversation persists product-scoped conversation for user', function () {
    config(['ai.enabled' => true]);

    ['owner' => $owner, 'product' => $product, 'organization' => $organization] = makeAiPersistenceFixture();

    $service = app(AiAssistantService::class);
    $conversation = $service->startConversation($product, $owner);

    expect($conversation)->toBeInstanceOf(AiConversation::class)
        ->and($conversation->organization_id)->toBe($organization->id)
        ->and($conversation->product_id)->toBe($product->id)
        ->and($conversation->user_id)->toBe($owner->id)
        ->and($conversation->context_type)->toBe(AiConversationContextType::Chat);
});

test('sendMessage appends user and assistant messages with stub metadata', function () {
    config(['ai.enabled' => true]);

    ['owner' => $owner, 'product' => $product] = makeAiPersistenceFixture();

    $service = app(AiAssistantService::class);
    $conversation = $service->startConversation($product, $owner);

    $result = $service->sendMessage($conversation, $owner, 'What evidence is missing?');

    expect($result['user_message']->role)->toBe(AiMessageRole::User)
        ->and($result['user_message']->content)->toBe('What evidence is missing?')
        ->and($result['user_message']->metadata)->toBeNull()
        ->and($result['assistant_message']->role)->toBe(AiMessageRole::Assistant)
        ->and($result['assistant_message']->content)->toContain('What evidence is missing?')
        ->and($result['assistant_message']->metadata)->toMatchArray([
                'provider' => 'stub',
                'model' => 'stub-local-template',
            ]);

    expect(AiMessage::query()->where('conversation_id', $conversation->id)->count())->toBe(2)
        ->and($result['conversation']->messages)->toHaveCount(2);
});

test('sendMessage rejects empty content and other users', function () {
    config(['ai.enabled' => true]);

    ['owner' => $owner, 'other' => $other, 'product' => $product] = makeAiPersistenceFixture();

    $service = app(AiAssistantService::class);
    $conversation = $service->startConversation($product, $owner);

    expect(fn() => $service->sendMessage($conversation, $owner, '   '))
        ->toThrow(ValidationException::class);

    expect(fn() => $service->sendMessage($conversation, $other, 'Hello'))
        ->toThrow(ValidationException::class);

    expect(AiMessage::query()->where('conversation_id', $conversation->id)->count())->toBe(0);
});

test('sendMessage is blocked when AI is disabled', function () {
    config(['ai.enabled' => true]);

    ['owner' => $owner, 'product' => $product] = makeAiPersistenceFixture();

    $service = app(AiAssistantService::class);
    $conversation = $service->startConversation($product, $owner);

    config(['ai.enabled' => false]);

    expect(fn() => $service->sendMessage($conversation, $owner, 'Hello'))
        ->toThrow(ValidationException::class);
});
