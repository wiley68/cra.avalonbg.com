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
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User, product: Product}
 */
function makeUsiLocalePairFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'USI Locale Pair Org',
        'slug' => 'usi-locale-pair-org-' . uniqid(),
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
        'name' => 'USI Locale Pair Product',
        'slug' => 'usi-locale-pair-product-' . uniqid(),
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    return compact('organization', 'owner', 'product');
}

function publishMinimalUsiLocalePair(
    User $owner,
    Product $product,
    UserSecurityInstruction $instruction,
    string $body,
): UserSecurityInstruction {
    $instruction->sections()->update(['is_applicable' => false, 'body' => '']);
    $instruction->sections()
        ->where('section_key', UserSecurityInstructionSectionKey::SecureInstallation->value)
        ->update([
            'is_applicable' => true,
            'body' => $body,
        ]);

    test()->actingAs($owner)
        ->post(route('products.security-instructions.publish', [$product, $instruction]))
        ->assertRedirect();

    return $instruction->fresh();
}

test('create pair links EN and BG documents bidirectionally', function () {
    ['owner' => $owner, 'product' => $product] = makeUsiLocalePairFixture();

    $this->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'title' => 'English guide',
            'version_label' => '1.0',
            'locale' => 'en',
        ])
        ->assertRedirect();

    $english = UserSecurityInstruction::query()
        ->where('title', 'English guide')
        ->firstOrFail();

    $this->actingAs($owner)
        ->post(route('products.security-instructions.create-pair', [$product, $english]))
        ->assertRedirect();

    $english->refresh();
    $bulgarian = UserSecurityInstruction::query()->findOrFail($english->paired_instruction_id);

    expect($english->paired_instruction_id)->toBe($bulgarian->id)
        ->and($bulgarian->paired_instruction_id)->toBe($english->id)
        ->and($bulgarian->locale)->toBe('bg')
        ->and($bulgarian->version_label)->toBe('1.0')
        ->and($bulgarian->product_version_id)->toBeNull()
        ->and($bulgarian->status)->toBe(UserSecurityInstructionStatus::Draft);

    $expectedPairedLocale = $bulgarian->locale;

    $this->actingAs($owner)
        ->get(route('products.security-instructions.edit', [$product, $english]))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('products/user-security-instructions/Edit')
            ->where('instruction.paired_instruction_id', $bulgarian->id)
            ->where('instruction.paired_locale', $expectedPairedLocale));
});

test('create pair links an existing unpaired opposite-locale document', function () {
    ['owner' => $owner, 'product' => $product] = makeUsiLocalePairFixture();

    $this->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'title' => 'English guide',
            'version_label' => '1.0',
            'locale' => 'en',
        ]);
    $this->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'use_template' => true,
            'locale' => 'bg',
            'version_label' => '1.0',
        ]);

    $english = UserSecurityInstruction::query()->where('locale', 'en')->firstOrFail();
    $bulgarian = UserSecurityInstruction::query()->where('locale', 'bg')->firstOrFail();

    expect($english->paired_instruction_id)->toBeNull()
        ->and($bulgarian->paired_instruction_id)->toBeNull();

    $this->actingAs($owner)
        ->post(route('products.security-instructions.create-pair', [$product, $english]))
        ->assertRedirect(route('products.security-instructions.edit', [$product, $bulgarian]));

    expect($english->fresh()->paired_instruction_id)->toBe($bulgarian->id)
        ->and($bulgarian->fresh()->paired_instruction_id)->toBe($english->id)
        ->and(UserSecurityInstruction::query()->where('product_id', $product->id)->count())->toBe(2);
});

test('publishing one locale does not retire the paired translation', function () {
    ['owner' => $owner, 'product' => $product] = makeUsiLocalePairFixture();

    $this->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'title' => 'English guide',
            'version_label' => '1.0',
            'locale' => 'en',
        ]);

    $english = UserSecurityInstruction::query()->where('locale', 'en')->firstOrFail();

    $this->actingAs($owner)
        ->post(route('products.security-instructions.create-pair', [$product, $english]))
        ->assertRedirect();

    $english->refresh();
    $bulgarian = UserSecurityInstruction::query()->findOrFail($english->paired_instruction_id);

    $english = publishMinimalUsiLocalePair($owner, $product, $english, 'Install EN.');
    $bulgarian = publishMinimalUsiLocalePair($owner, $product, $bulgarian, 'Инсталирай BG.');

    expect($english->status)->toBe(UserSecurityInstructionStatus::Published)
        ->and($bulgarian->fresh()->status)->toBe(UserSecurityInstructionStatus::Published)
        ->and($english->paired_instruction_id)->toBe($bulgarian->id);
});

test('create pair rejects documents that are already paired', function () {
    ['owner' => $owner, 'product' => $product] = makeUsiLocalePairFixture();

    $this->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'title' => 'English guide',
            'version_label' => '1.0',
            'locale' => 'en',
        ]);

    $english = UserSecurityInstruction::query()->where('locale', 'en')->firstOrFail();

    $this->actingAs($owner)
        ->post(route('products.security-instructions.create-pair', [$product, $english]))
        ->assertRedirect();

    $this->actingAs($owner)
        ->from(route('products.security-instructions.edit', [$product, $english]))
        ->post(route('products.security-instructions.create-pair', [$product, $english]))
        ->assertRedirect(route('products.security-instructions.edit', [$product, $english]))
        ->assertSessionHasErrors('paired_instruction_id');
});

test('version-pinned documents only pair within the same version scope', function () {
    ['owner' => $owner, 'product' => $product] = makeUsiLocalePairFixture();

    $version = ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '9.0.0',
        'state' => ProductVersionState::Released,
        'support_status' => SupportStatus::Supported,
    ]);

    $this->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'title' => 'Pinned EN',
            'version_label' => '1.0',
            'locale' => 'en',
            'product_version_id' => $version->id,
        ]);

    $pinnedEn = UserSecurityInstruction::query()->where('title', 'Pinned EN')->firstOrFail();

    $this->actingAs($owner)
        ->post(route('products.security-instructions.create-pair', [$product, $pinnedEn]))
        ->assertRedirect();

    $pinnedBg = UserSecurityInstruction::query()->findOrFail($pinnedEn->fresh()->paired_instruction_id);

    expect($pinnedBg->locale)->toBe('bg')
        ->and($pinnedBg->product_version_id)->toBe($version->id);
});
