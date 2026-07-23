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
function makeUsiDiffFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'USI Diff Org',
        'slug' => 'usi-diff-org-' . uniqid(),
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
        'name' => 'USI Diff Product',
        'slug' => 'usi-diff-product-' . uniqid(),
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    return compact('organization', 'owner', 'product');
}

function publishMinimalUsi(
    User $owner,
    Product $product,
    UserSecurityInstruction $instruction,
    string $installBody,
): UserSecurityInstruction {
    $instruction->sections()->update(['is_applicable' => false, 'body' => '']);
    $instruction->sections()
        ->where('section_key', UserSecurityInstructionSectionKey::SecureInstallation->value)
        ->update([
            'is_applicable' => true,
            'body' => $installBody,
        ]);

    test()->actingAs($owner)
        ->post(route('products.security-instructions.publish', [$product, $instruction]))
        ->assertRedirect();

    return $instruction->fresh(['sections', 'supersedes']);
}

test('publishing a successor sets supersedes_id to the previous published document', function () {
    ['owner' => $owner, 'product' => $product] = makeUsiDiffFixture();

    test()->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'title' => 'Instructions v1',
            'version_label' => '1.0',
            'locale' => 'en',
        ])
        ->assertRedirect();

    $first = UserSecurityInstruction::query()
        ->where('product_id', $product->id)
        ->where('title', 'Instructions v1')
        ->firstOrFail();

    $first = publishMinimalUsi($owner, $product, $first, 'Install v1 securely.');

    expect($first->status)->toBe(UserSecurityInstructionStatus::Published)
        ->and($first->supersedes_id)->toBeNull();

    test()->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'title' => 'Instructions v2',
            'version_label' => '2.0',
            'locale' => 'en',
        ])
        ->assertRedirect();

    $second = UserSecurityInstruction::query()
        ->where('product_id', $product->id)
        ->where('title', 'Instructions v2')
        ->firstOrFail();

    expect($second->supersedes_id)->toBe($first->id);

    $second = publishMinimalUsi($owner, $product, $second, 'Install v2 securely.');

    expect($first->fresh()->status)->toBe(UserSecurityInstructionStatus::Retired)
        ->and($second->status)->toBe(UserSecurityInstructionStatus::Published)
        ->and($second->supersedes_id)->toBe($first->id);
});

test('edit page includes supersedes sections for markdown diff', function () {
    ['owner' => $owner, 'product' => $product] = makeUsiDiffFixture();

    test()->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'title' => 'Instructions v1',
            'version_label' => '1.0',
            'locale' => 'en',
        ]);

    $first = UserSecurityInstruction::query()
        ->where('title', 'Instructions v1')
        ->firstOrFail();
    $first = publishMinimalUsi($owner, $product, $first, "Install securely.\nUse TLS.");

    test()->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'title' => 'Instructions v2',
            'version_label' => '2.0',
            'locale' => 'en',
        ]);

    $second = UserSecurityInstruction::query()
        ->where('title', 'Instructions v2')
        ->firstOrFail();

    $expectedSupersedesTitle = $first->title . ' (' . $first->version_label . ')';
    $expectedInstallBody = $first->sections
        ->firstWhere('section_key', UserSecurityInstructionSectionKey::SecureInstallation)
            ?->body;

    $this->actingAs($owner)
        ->get(route('products.security-instructions.edit', [$product, $second]))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('products/user-security-instructions/Edit')
            ->where('instruction.supersedes_id', $first->id)
            ->where('instruction.supersedes_title', $expectedSupersedesTitle)
            ->where(
                'instruction.supersedes_sections.secure_installation.body',
                $expectedInstallBody,
            )
            ->where(
                'instruction.supersedes_sections.secure_installation.is_applicable',
                true,
            ));
});

test('version-pinned publish does not supersede product-wide published instructions', function () {
    ['owner' => $owner, 'product' => $product] = makeUsiDiffFixture();

    $version = ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '3.0.0',
        'state' => ProductVersionState::Released,
        'support_status' => SupportStatus::Supported,
    ]);

    test()->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'title' => 'Product-wide',
            'version_label' => '1.0',
            'locale' => 'en',
        ]);

    $wide = UserSecurityInstruction::query()->where('title', 'Product-wide')->firstOrFail();
    publishMinimalUsi($owner, $product, $wide, 'Wide install.');

    test()->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'title' => 'Pinned A',
            'version_label' => '1.0',
            'locale' => 'en',
            'product_version_id' => $version->id,
        ]);

    $pinned = UserSecurityInstruction::query()->where('title', 'Pinned A')->firstOrFail();

    expect($pinned->supersedes_id)->toBeNull();

    $pinned = publishMinimalUsi($owner, $product, $pinned, 'Pinned install.');

    expect($wide->fresh()->status)->toBe(UserSecurityInstructionStatus::Published)
        ->and($pinned->status)->toBe(UserSecurityInstructionStatus::Published)
        ->and($pinned->supersedes_id)->toBeNull();
});
