<?php

use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\TechnicalDocumentationSectionKey;
use App\Enums\TechnicalDocumentationSectionSource;
use App\Enums\TechnicalDocumentationStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\TechnicalDocumentationPackage;
use App\Models\TechnicalDocumentationSection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, product: Product}
 */
function makeTechDocProduct(): array
{
    $organization = Organization::query()->create([
        'name' => 'Tech Doc Org',
        'slug' => 'tech-doc-org-' . uniqid(),
        'is_active' => true,
        'locale' => 'en',
    ]);

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Tech Doc Product',
        'slug' => 'tech-doc-product-' . uniqid(),
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    return compact('organization', 'product');
}

test('technical documentation package persists with sections and casts', function () {
    ['organization' => $organization, 'product' => $product] = makeTechDocProduct();

    $package = TechnicalDocumentationPackage::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'product_version_id' => null,
        'title' => 'Technical documentation 1.0',
        'status' => TechnicalDocumentationStatus::Draft,
        'version_label' => '1.0',
        'locale' => 'en',
        'notes' => null,
    ]);

    expect($package->status)->toBe(TechnicalDocumentationStatus::Draft)
        ->and($package->isEditable())->toBeTrue()
        ->and($package->isPublished())->toBeFalse()
        ->and($product->technicalDocumentationPackages()->count())->toBe(1)
        ->and($organization->technicalDocumentationPackages()->count())->toBe(1);

    $section = TechnicalDocumentationSection::query()->create([
        'package_id' => $package->id,
        'section_key' => TechnicalDocumentationSectionKey::Architecture,
        'source' => TechnicalDocumentationSectionKey::Architecture->defaultSource(),
        'body_markdown' => 'High-level architecture notes.',
        'generated_payload' => null,
        'sort_order' => TechnicalDocumentationSectionKey::Architecture->defaultSortOrder(),
        'is_applicable' => true,
        'override_reason' => null,
        'changed_since_parent' => false,
    ]);

    expect($section->section_key)->toBe(TechnicalDocumentationSectionKey::Architecture)
        ->and($section->source)->toBe(TechnicalDocumentationSectionSource::Authored)
        ->and($section->is_applicable)->toBeTrue()
        ->and($section->changed_since_parent)->toBeFalse()
        ->and($package->sections()->count())->toBe(1)
        ->and(TechnicalDocumentationSectionKey::ordered())->toHaveCount(18)
        ->and(TechnicalDocumentationSectionKey::Sbom->defaultSource())->toBe(TechnicalDocumentationSectionSource::Generated)
        ->and(TechnicalDocumentationSectionKey::UserSecurityInstructions->defaultSource())
        ->toBe(TechnicalDocumentationSectionSource::Linked);
});

test('section key is unique per technical documentation package', function () {
    ['organization' => $organization, 'product' => $product] = makeTechDocProduct();

    $package = TechnicalDocumentationPackage::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Doc',
        'status' => TechnicalDocumentationStatus::Draft,
        'version_label' => '1.0',
        'locale' => 'bg',
    ]);

    TechnicalDocumentationSection::query()->create([
        'package_id' => $package->id,
        'section_key' => TechnicalDocumentationSectionKey::Sbom,
        'source' => TechnicalDocumentationSectionSource::Generated,
        'body_markdown' => null,
        'generated_payload' => ['component_count' => 3],
        'sort_order' => 1,
        'is_applicable' => true,
        'changed_since_parent' => false,
    ]);

    expect(fn() => TechnicalDocumentationSection::query()->create([
        'package_id' => $package->id,
        'section_key' => TechnicalDocumentationSectionKey::Sbom,
        'source' => TechnicalDocumentationSectionSource::Generated,
        'body_markdown' => null,
        'generated_payload' => ['component_count' => 4],
        'sort_order' => 2,
        'is_applicable' => true,
        'changed_since_parent' => false,
    ]))->toThrow(QueryException::class);
});

test('generated payload casts to array on technical documentation section', function () {
    ['organization' => $organization, 'product' => $product] = makeTechDocProduct();

    $package = TechnicalDocumentationPackage::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Doc',
        'status' => TechnicalDocumentationStatus::UnderReview,
        'version_label' => '1.0',
        'locale' => 'en',
    ]);

    $section = TechnicalDocumentationSection::query()->create([
        'package_id' => $package->id,
        'section_key' => TechnicalDocumentationSectionKey::ProductIdentification,
        'source' => TechnicalDocumentationSectionSource::Generated,
        'generated_payload' => [
            'name' => 'Tech Doc Product',
            'manufacturer' => 'Acme',
        ],
        'sort_order' => TechnicalDocumentationSectionKey::ProductIdentification->defaultSortOrder(),
        'is_applicable' => true,
    ]);

    $section->refresh();

    expect($section->generated_payload)->toBe([
        'name' => 'Tech Doc Product',
        'manufacturer' => 'Acme',
    ])
        ->and($package->isEditable())->toBeTrue();
});
