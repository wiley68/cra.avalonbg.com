<?php

use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\UserSecurityInstructionSectionKey;
use App\Enums\UserSecurityInstructionStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\UserSecurityInstruction;
use App\Models\UserSecurityInstructionSection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, product: Product}
 */
function makeUsiProduct(): array
{
    $organization = Organization::query()->create([
        'name' => 'USI Org',
        'slug' => 'usi-org-' . uniqid(),
        'is_active' => true,
        'locale' => 'en',
    ]);

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'USI Product',
        'slug' => 'usi-product-' . uniqid(),
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    return compact('organization', 'product');
}

test('user security instruction persists with sections and casts', function () {
    ['organization' => $organization, 'product' => $product] = makeUsiProduct();

    $instruction = UserSecurityInstruction::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'product_version_id' => null,
        'title' => 'Security instructions 1.0',
        'status' => UserSecurityInstructionStatus::Draft,
        'version_label' => '1.0',
        'locale' => 'en',
        'notes' => null,
    ]);

    expect($instruction->status)->toBe(UserSecurityInstructionStatus::Draft)
        ->and($instruction->isEditable())->toBeTrue()
        ->and($product->userSecurityInstructions()->count())->toBe(1);

    $section = UserSecurityInstructionSection::query()->create([
        'instruction_id' => $instruction->id,
        'section_key' => UserSecurityInstructionSectionKey::SecureInstallation,
        'title_override' => null,
        'body' => 'Install with TLS enabled.',
        'sort_order' => UserSecurityInstructionSectionKey::SecureInstallation->defaultSortOrder(),
        'is_applicable' => true,
    ]);

    expect($section->section_key)->toBe(UserSecurityInstructionSectionKey::SecureInstallation)
        ->and($section->is_applicable)->toBeTrue()
        ->and($instruction->sections()->count())->toBe(1)
        ->and(UserSecurityInstructionSectionKey::ordered())->toHaveCount(14);
});

test('section key is unique per instruction', function () {
    ['organization' => $organization, 'product' => $product] = makeUsiProduct();

    $instruction = UserSecurityInstruction::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Doc',
        'status' => UserSecurityInstructionStatus::Draft,
        'version_label' => '1.0',
        'locale' => 'bg',
    ]);

    UserSecurityInstructionSection::query()->create([
        'instruction_id' => $instruction->id,
        'section_key' => UserSecurityInstructionSectionKey::Logging,
        'body' => 'First',
        'sort_order' => 1,
        'is_applicable' => true,
    ]);

    expect(fn() => UserSecurityInstructionSection::query()->create([
        'instruction_id' => $instruction->id,
        'section_key' => UserSecurityInstructionSectionKey::Logging,
        'body' => 'Duplicate',
        'sort_order' => 2,
        'is_applicable' => true,
    ]))->toThrow(QueryException::class);
});
