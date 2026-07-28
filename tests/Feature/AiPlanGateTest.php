<?php

use App\Enums\BillingStatus;
use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\SubscriptionPlan;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Support\Translations;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User, product: Product}
 */
function makeAiPlanGateFixture(string $plan = 'free'): array
{
    test()->seed([RolePermissionSeeder::class]);
    Config::set('ai.enabled', true);
    Config::set('ai.provider', 'stub');

    $organization = Organization::query()->create([
        'name' => 'AI Plan Gate Org',
        'slug' => 'ai-plan-gate-org-' . uniqid(),
        'is_active' => true,
        'locale' => 'en',
        'subscription_plan' => $plan,
        'billing_status' => BillingStatus::Active->value,
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
        'name' => 'AI Plan Product',
        'slug' => 'ai-plan-product-' . uniqid(),
        'product_type' => ProductType::Software->value,
        'licensing_model' => LicensingModel::Paid->value,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => false,
        'scope_status' => ScopeStatus::LikelyInScope->value,
        'classification_status' => ClassificationStatus::Unclassified->value,
    ]);

    return compact('organization', 'owner', 'product');
}

test('free plan cannot use AI and shares can_use_ai false', function () {
    ['organization' => $organization, 'owner' => $owner, 'product' => $product] = makeAiPlanGateFixture(
        SubscriptionPlan::Free->value,
    );

    expect($organization->canUseAi())->toBeFalse();

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->where('organization.can_use_ai', false)
            ->where('auth.user.can_manage_billing', true));

    $this->actingAs($owner)
        ->post(route('products.assistant.messages.store', $product), [
            'content' => 'Hello AI',
        ])
        ->assertSessionHasErrors('assistant');

    expect(session('errors')->first('assistant'))
        ->toBe(Translations::get('assistant.plan_locked', locale: 'en'));
});

test('paid plan can use AI and shares can_use_ai true', function () {
    $smallPlan = SubscriptionPlan::Small->value;

    ['organization' => $organization, 'owner' => $owner, 'product' => $product] = makeAiPlanGateFixture(
        $smallPlan,
    );

    expect($organization->canUseAi())->toBeTrue();

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->where('organization.can_use_ai', true)
            ->where('organization.subscription_plan', $smallPlan));

    $this->actingAs($owner)
        ->post(route('products.assistant.messages.store', $product), [
            'content' => 'Hello AI',
        ])
        ->assertRedirect();
});

test('assistant page remains reachable on free plan', function () {
    ['owner' => $owner, 'product' => $product] = makeAiPlanGateFixture(
        SubscriptionPlan::Free->value,
    );

    $this->actingAs($owner)
        ->get(route('products.assistant.show', $product))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('products/assistant/Show')
            ->where('ai_enabled', true));
});
