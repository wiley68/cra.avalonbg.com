<?php

use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ProductVersionState;
use App\Enums\ScopeStatus;
use App\Enums\SupportStatus;
use App\Enums\UserSecurityInstructionSectionKey;
use App\Enums\UserSecurityInstructionStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVersion;
use App\Models\Role;
use App\Models\User;
use App\Models\UserSecurityInstruction;
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
function makeUsiVersionPinFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'USI Version Pin Org',
        'slug' => 'usi-version-pin-org-' . uniqid(),
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
        'name' => 'USI Version Pin Product',
        'slug' => 'usi-version-pin-product-' . uniqid(),
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
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

function fillMinimalPublishableSections(UserSecurityInstruction $instruction): void
{
    $instruction->sections()->update([
        'is_applicable' => false,
        'body' => '',
    ]);
    $instruction->sections()
        ->where('section_key', UserSecurityInstructionSectionKey::SecureInstallation->value)
        ->update([
            'is_applicable' => true,
            'body' => 'Install securely.',
        ]);
}

test('owner can create version-pinned instructions', function () {
    ['owner' => $owner, 'product' => $product, 'versionA' => $versionA] = makeUsiVersionPinFixture();

    $this->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'title' => 'Pinned guide',
            'version_label' => '1.0',
            'locale' => 'en',
            'product_version_id' => $versionA->id,
        ])
        ->assertRedirect();

    $instruction = UserSecurityInstruction::query()
        ->where('product_id', $product->id)
        ->firstOrFail();

    expect($instruction->product_version_id)->toBe($versionA->id);

    $this->actingAs($owner)
        ->get(route('products.security-instructions.edit', [$product, $instruction]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/user-security-instructions/Edit')
            ->where('instruction.product_version_id', $versionA->id)
            ->where('instruction.product_version_number', '1.0.0')
            ->has('versions', 2));
});

test('product_version_id must belong to the product', function () {
    ['owner' => $owner, 'product' => $product] = makeUsiVersionPinFixture();

    $otherProduct = Product::query()->create([
        'organization_id' => $product->organization_id,
        'name' => 'Other product',
        'slug' => 'other-product-' . uniqid(),
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
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
        ->post(route('products.security-instructions.store', $product), [
            'title' => 'Bad pin',
            'version_label' => '1.0',
            'locale' => 'en',
            'product_version_id' => $foreignVersion->id,
        ])
        ->assertSessionHasErrors('product_version_id');
});

test('publish retires only same product version and locale siblings', function () {
    [
        'owner' => $owner,
        'product' => $product,
        'versionA' => $versionA,
        'versionB' => $versionB,
    ] = makeUsiVersionPinFixture();

    $this->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'title' => 'Wide first',
            'version_label' => '1.0',
            'locale' => 'en',
        ]);
    $wide = UserSecurityInstruction::query()->where('title', 'Wide first')->firstOrFail();
    fillMinimalPublishableSections($wide);
    $this->actingAs($owner)
        ->post(route('products.security-instructions.publish', [$product, $wide]))
        ->assertRedirect();

    $this->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'title' => 'Pinned A',
            'version_label' => '1.0',
            'locale' => 'en',
            'product_version_id' => $versionA->id,
        ]);
    $pinnedA = UserSecurityInstruction::query()->where('title', 'Pinned A')->firstOrFail();
    fillMinimalPublishableSections($pinnedA);
    $this->actingAs($owner)
        ->post(route('products.security-instructions.publish', [$product, $pinnedA]))
        ->assertRedirect();

    $this->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'title' => 'Pinned B',
            'version_label' => '1.0',
            'locale' => 'en',
            'product_version_id' => $versionB->id,
        ]);
    $pinnedB = UserSecurityInstruction::query()->where('title', 'Pinned B')->firstOrFail();
    fillMinimalPublishableSections($pinnedB);
    $this->actingAs($owner)
        ->post(route('products.security-instructions.publish', [$product, $pinnedB]))
        ->assertRedirect();

    expect($wide->fresh()->status)->toBe(UserSecurityInstructionStatus::Published)
        ->and($pinnedA->fresh()->status)->toBe(UserSecurityInstructionStatus::Published)
        ->and($pinnedB->fresh()->status)->toBe(UserSecurityInstructionStatus::Published);

    $this->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'title' => 'Pinned A v2',
            'version_label' => '2.0',
            'locale' => 'en',
            'product_version_id' => $versionA->id,
        ]);
    $pinnedA2 = UserSecurityInstruction::query()->where('title', 'Pinned A v2')->firstOrFail();
    fillMinimalPublishableSections($pinnedA2);
    $this->actingAs($owner)
        ->post(route('products.security-instructions.publish', [$product, $pinnedA2]))
        ->assertRedirect();

    expect($pinnedA->fresh()->status)->toBe(UserSecurityInstructionStatus::Retired)
        ->and($pinnedA2->fresh()->status)->toBe(UserSecurityInstructionStatus::Published)
        ->and($wide->fresh()->status)->toBe(UserSecurityInstructionStatus::Published)
        ->and($pinnedB->fresh()->status)->toBe(UserSecurityInstructionStatus::Published);
});

test('api can filter by product version and product-wide scope', function () {
    [
        'owner' => $owner,
        'product' => $product,
        'versionA' => $versionA,
    ] = makeUsiVersionPinFixture();

    $this->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'title' => 'Wide',
            'version_label' => '1.0',
            'locale' => 'en',
        ]);

    $this->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'title' => 'Pinned',
            'version_label' => '1.0',
            'locale' => 'en',
            'product_version_id' => $versionA->id,
        ]);

    $this->actingAs($owner)
        ->getJson(route('internal.products.security-instructions.index', $product) . '?product_wide=1')
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.title', 'Wide')
        ->assertJsonPath('data.0.product_version_id', null);

    $this->actingAs($owner)
        ->getJson(route('internal.products.security-instructions.index', [
            'product' => $product,
            'product_version_id' => $versionA->id,
        ]))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.title', 'Pinned')
        ->assertJsonPath('data.0.product_version_number', '1.0.0');
});

test('owner can update product version pin while draft', function () {
    [
        'owner' => $owner,
        'product' => $product,
        'versionA' => $versionA,
        'versionB' => $versionB,
    ] = makeUsiVersionPinFixture();

    $this->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'title' => 'Editable pin',
            'version_label' => '1.0',
            'locale' => 'en',
            'product_version_id' => $versionA->id,
        ]);

    $instruction = UserSecurityInstruction::query()
        ->where('product_id', $product->id)
        ->firstOrFail()
        ->load('sections');

    $sections = $instruction->sections->map(fn($section) => [
        'section_key' => $section->section_key->value,
        'body' => $section->body,
        'title_override' => null,
        'is_applicable' => true,
        'sort_order' => $section->sort_order,
    ])->all();

    $this->actingAs($owner)
        ->put(route('products.security-instructions.update', [$product, $instruction]), [
            'title' => 'Editable pin',
            'version_label' => '1.0',
            'locale' => 'en',
            'notes' => null,
            'product_version_id' => $versionB->id,
            'sections' => $sections,
        ])
        ->assertRedirect();

    expect($instruction->fresh()->product_version_id)->toBe($versionB->id);
});
