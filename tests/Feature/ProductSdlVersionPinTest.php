<?php

use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ProductVersionState;
use App\Enums\ScopeStatus;
use App\Enums\SdlRunStatus;
use App\Enums\SdlStage;
use App\Enums\SupportStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVersion;
use App\Models\Role;
use App\Models\SdlRun;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     organization: Organization,
 *     owner: User,
 *     product: Product,
 *     versionA: ProductVersion,
 *     versionB: ProductVersion
 * }
 */
function makeSdlVersionPinFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'SDL Version Pin Org',
        'slug' => 'sdl-version-pin-org-' . uniqid(),
        'is_active' => true,
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
        'name' => 'SDL Version Pin Product',
        'slug' => 'sdl-version-pin-product-' . uniqid(),
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

    $versionA = ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '1.0.0',
        'state' => ProductVersionState::Released,
        'support_status' => SupportStatus::Supported,
    ]);

    $versionB = ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '2.0.0',
        'state' => ProductVersionState::Released,
        'support_status' => SupportStatus::Supported,
    ]);

    return compact('organization', 'owner', 'product', 'versionA', 'versionB');
}

test('owner can create and edit version-pinned sdl runs', function () {
    [
        'owner' => $owner,
        'product' => $product,
        'versionA' => $versionA,
    ] = makeSdlVersionPinFixture();

    $this->actingAs($owner)
        ->post(route('products.sdl.store', $product), [
            'title' => 'Pinned release run',
            'status' => SdlRunStatus::Draft->value,
            'current_stage' => SdlStage::Requirement->value,
            'product_version_id' => $versionA->id,
        ])
        ->assertRedirect();

    $run = SdlRun::query()
        ->where('product_id', $product->id)
        ->where('title', 'Pinned release run')
        ->firstOrFail();

    expect($run->product_version_id)->toBe($versionA->id);

    $this->actingAs($owner)
        ->get(route('products.sdl.edit', [$product, $run]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/sdl/Edit')
            ->where('run.product_version_id', $versionA->id)
            ->where('run.version_number', '1.0.0')
            ->has('versions', 2));

    $this->actingAs($owner)
        ->get(route('products.sdl.index', $product))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/sdl/Index')
            ->has('versions', 2));
});

test('product_version_id must belong to the product for sdl runs', function () {
    ['owner' => $owner, 'product' => $product] = makeSdlVersionPinFixture();

    $otherProduct = Product::query()->create([
        'organization_id' => $product->organization_id,
        'name' => 'Other SDL product',
        'slug' => 'other-sdl-product-' . uniqid(),
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => true,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    $foreignVersion = ProductVersion::query()->create([
        'product_id' => $otherProduct->id,
        'version_number' => '9.9.9',
        'state' => ProductVersionState::Released,
        'support_status' => SupportStatus::Supported,
    ]);

    $this->actingAs($owner)
        ->from(route('products.sdl.create', $product))
        ->post(route('products.sdl.store', $product), [
            'title' => 'Bad pin',
            'status' => SdlRunStatus::Draft->value,
            'current_stage' => SdlStage::Requirement->value,
            'product_version_id' => $foreignVersion->id,
        ])
        ->assertRedirect(route('products.sdl.create', $product))
        ->assertSessionHasErrors('product_version_id');
});

test('api can filter sdl runs by product version and product-wide scope', function () {
    [
        'owner' => $owner,
        'product' => $product,
        'versionA' => $versionA,
        'versionB' => $versionB,
    ] = makeSdlVersionPinFixture();

    $this->actingAs($owner)
        ->post(route('products.sdl.store', $product), [
            'title' => 'Wide SDL',
            'status' => SdlRunStatus::Draft->value,
            'current_stage' => SdlStage::Requirement->value,
        ])
        ->assertRedirect();

    $this->actingAs($owner)
        ->post(route('products.sdl.store', $product), [
            'title' => 'Pinned A',
            'status' => SdlRunStatus::Draft->value,
            'current_stage' => SdlStage::Requirement->value,
            'product_version_id' => $versionA->id,
        ])
        ->assertRedirect();

    $this->actingAs($owner)
        ->post(route('products.sdl.store', $product), [
            'title' => 'Pinned B',
            'status' => SdlRunStatus::Draft->value,
            'current_stage' => SdlStage::Requirement->value,
            'product_version_id' => $versionB->id,
        ])
        ->assertRedirect();

    $this->actingAs($owner)
        ->getJson(route('internal.products.sdl.index', $product) . '?product_wide=1')
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.title', 'Wide SDL')
        ->assertJsonPath('data.0.product_version_id', null);

    $this->actingAs($owner)
        ->getJson(route('internal.products.sdl.index', [
            'product' => $product,
            'product_version_id' => $versionA->id,
        ]))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.title', 'Pinned A')
        ->assertJsonPath('data.0.version_number', '1.0.0');

    $this->actingAs($owner)
        ->getJson(route('internal.products.sdl.index', $product) . '?search=1.0.0')
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.title', 'Pinned A');
});

test('owner can change and clear sdl version pin while editable', function () {
    [
        'owner' => $owner,
        'product' => $product,
        'versionA' => $versionA,
        'versionB' => $versionB,
    ] = makeSdlVersionPinFixture();

    $this->actingAs($owner)
        ->post(route('products.sdl.store', $product), [
            'title' => 'Editable pin',
            'status' => SdlRunStatus::Draft->value,
            'current_stage' => SdlStage::Requirement->value,
            'product_version_id' => $versionA->id,
        ])
        ->assertRedirect();

    $run = SdlRun::query()
        ->where('product_id', $product->id)
        ->where('title', 'Editable pin')
        ->firstOrFail();

    $this->actingAs($owner)
        ->put(route('products.sdl.update', [$product, $run]), [
            'title' => 'Editable pin',
            'status' => SdlRunStatus::InProgress->value,
            'current_stage' => SdlStage::Requirement->value,
            'product_version_id' => $versionB->id,
        ])
        ->assertRedirect();

    expect($run->fresh()->product_version_id)->toBe($versionB->id);

    $this->actingAs($owner)
        ->put(route('products.sdl.update', [$product, $run]), [
            'title' => 'Editable pin',
            'status' => SdlRunStatus::InProgress->value,
            'current_stage' => SdlStage::Requirement->value,
            'product_version_id' => null,
        ])
        ->assertRedirect();

    expect($run->fresh()->product_version_id)->toBeNull();
});
