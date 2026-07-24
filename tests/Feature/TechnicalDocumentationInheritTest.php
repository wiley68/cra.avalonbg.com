<?php

use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ProductVersionState;
use App\Enums\ScopeStatus;
use App\Enums\SupportStatus;
use App\Enums\TechnicalDocumentationSectionKey;
use App\Enums\TechnicalDocumentationSectionSource;
use App\Enums\TechnicalDocumentationStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVersion;
use App\Models\Role;
use App\Models\TechnicalDocumentationPackage;
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
function makeTechDocInheritFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Tech Doc Inherit Org',
        'slug' => 'tech-doc-inherit-org-' . uniqid(),
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
        'name' => 'Tech Doc Inherit Product',
        'slug' => 'tech-doc-inherit-product-' . uniqid(),
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
        'manufacturer' => 'Avalon Labs',
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

function publishTechDocPackage(
    User $owner,
    Product $product,
    TechnicalDocumentationPackage $package,
): TechnicalDocumentationPackage {
    $package->sections()
        ->whereIn('source', [
            TechnicalDocumentationSectionSource::Authored->value,
            TechnicalDocumentationSectionSource::Linked->value,
        ])
        ->update([
            'is_applicable' => false,
            'override_reason' => 'N/A for inherit fixture.',
            'body_markdown' => null,
        ]);

    $package->sections()
        ->where('section_key', TechnicalDocumentationSectionKey::Architecture->value)
        ->update([
            'is_applicable' => true,
            'override_reason' => null,
            'body_markdown' => "## Architecture\n\nInherited trust boundaries.",
        ]);

    $package->sections()
        ->where('section_key', TechnicalDocumentationSectionKey::IntendedPurpose->value)
        ->update([
            'is_applicable' => true,
            'override_reason' => null,
            'body_markdown' => 'Secure industrial gateway for SMEs.',
        ]);

    test()->actingAs($owner)
        ->post(route('products.technical-documentation.publish', [$product, $package]))
        ->assertRedirect();

    return $package->fresh(['sections']);
}

test('version-pinned package inherits authored sections from product-wide published', function () {
    ['owner' => $owner, 'product' => $product, 'versionB' => $versionB] = makeTechDocInheritFixture();

    test()->actingAs($owner)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => 'Product-wide docs',
            'version_label' => '1.0',
            'locale' => 'en',
            'inherit_from_previous' => false,
        ])
        ->assertRedirect();

    $parent = TechnicalDocumentationPackage::query()
        ->where('product_id', $product->id)
        ->whereNull('product_version_id')
        ->firstOrFail()
        ->load('sections');

    $parent = publishTechDocPackage($owner, $product, $parent);

    test()->actingAs($owner)
        ->get(route('products.technical-documentation.create', $product))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/technical-documentation/Create')
            ->where('hasPublishedPrevious', true));

    test()->actingAs($owner)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => 'v2 docs',
            'version_label' => '2.0',
            'locale' => 'en',
            'product_version_id' => $versionB->id,
            'inherit_from_previous' => true,
        ])
        ->assertRedirect();

    $child = TechnicalDocumentationPackage::query()
        ->where('product_id', $product->id)
        ->where('product_version_id', $versionB->id)
        ->firstOrFail()
        ->load('sections');

    expect($child->supersedes_id)->toBe($parent->id);

    $architecture = $child->sections
        ->firstWhere('section_key', TechnicalDocumentationSectionKey::Architecture);
    $purpose = $child->sections
        ->firstWhere('section_key', TechnicalDocumentationSectionKey::IntendedPurpose);

    expect($architecture?->body_markdown)->toContain('Inherited trust boundaries')
        ->and($architecture?->changed_since_parent)->toBeFalse()
        ->and($purpose?->body_markdown)->toBe('Secure industrial gateway for SMEs.')
        ->and($purpose?->changed_since_parent)->toBeFalse();
});

test('inherit false does not copy bodies but still links supersedes for version pin', function () {
    ['owner' => $owner, 'product' => $product, 'versionB' => $versionB] = makeTechDocInheritFixture();

    test()->actingAs($owner)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => 'Product-wide docs',
            'version_label' => '1.0',
            'locale' => 'en',
        ])
        ->assertRedirect();

    $parent = TechnicalDocumentationPackage::query()
        ->where('product_id', $product->id)
        ->firstOrFail()
        ->load('sections');
    $parent = publishTechDocPackage($owner, $product, $parent);

    test()->actingAs($owner)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => 'Fresh v2',
            'version_label' => '2.0',
            'locale' => 'en',
            'product_version_id' => $versionB->id,
            'inherit_from_previous' => false,
        ])
        ->assertRedirect();

    $child = TechnicalDocumentationPackage::query()
        ->where('title', 'Fresh v2')
        ->firstOrFail()
        ->load('sections');

    expect($child->supersedes_id)->toBe($parent->id);

    $architecture = $child->sections
        ->firstWhere('section_key', TechnicalDocumentationSectionKey::Architecture);

    expect($architecture?->body_markdown)->toBeNull();
});

test('editing inherited authored section marks changed_since_parent', function () {
    ['owner' => $owner, 'product' => $product, 'versionB' => $versionB] = makeTechDocInheritFixture();

    test()->actingAs($owner)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => 'Product-wide docs',
            'version_label' => '1.0',
            'locale' => 'en',
        ])
        ->assertRedirect();

    $parent = TechnicalDocumentationPackage::query()
        ->where('product_id', $product->id)
        ->firstOrFail()
        ->load('sections');
    publishTechDocPackage($owner, $product, $parent);

    test()->actingAs($owner)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => 'v2 docs',
            'version_label' => '2.0',
            'locale' => 'en',
            'product_version_id' => $versionB->id,
            'inherit_from_previous' => true,
        ])
        ->assertRedirect();

    $child = TechnicalDocumentationPackage::query()
        ->where('title', 'v2 docs')
        ->firstOrFail()
        ->load('sections');

    $sections = $child->sections->map(function ($section) {
        $payload = [
            'section_key' => $section->section_key->value,
            'body_markdown' => $section->body_markdown,
            'is_applicable' => $section->is_applicable,
            'override_reason' => $section->override_reason,
            'sort_order' => $section->sort_order,
        ];

        if ($section->section_key === TechnicalDocumentationSectionKey::Architecture) {
            $payload['body_markdown'] = "## Architecture\n\nUpdated for v2.";
        }

        return $payload;
    })->all();

    test()->actingAs($owner)
        ->put(route('products.technical-documentation.update', [$product, $child]), [
            'title' => 'v2 docs',
            'version_label' => '2.0',
            'locale' => 'en',
            'product_version_id' => $versionB->id,
            'sections' => $sections,
        ])
        ->assertRedirect();

    $child->refresh()->load('sections');
    $architecture = $child->sections
        ->firstWhere('section_key', TechnicalDocumentationSectionKey::Architecture);
    $purpose = $child->sections
        ->firstWhere('section_key', TechnicalDocumentationSectionKey::IntendedPurpose);

    expect($architecture?->changed_since_parent)->toBeTrue()
        ->and($purpose?->changed_since_parent)->toBeFalse();

    test()->actingAs($owner)
        ->get(route('products.technical-documentation.edit', [$product, $child]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->where('package.supersedes_id', $child->supersedes_id)
            ->has('package.supersedes_sections.architecture')
            ->where(
                'package.supersedes_sections.architecture.body_markdown',
                "## Architecture\n\nInherited trust boundaries.",
            ));
});

test('same-scope successor inherits from previous published sibling', function () {
    ['owner' => $owner, 'product' => $product, 'versionA' => $versionA] = makeTechDocInheritFixture();

    test()->actingAs($owner)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => 'v1 docs A',
            'version_label' => '1.0',
            'locale' => 'en',
            'product_version_id' => $versionA->id,
        ])
        ->assertRedirect();

    $first = TechnicalDocumentationPackage::query()
        ->where('title', 'v1 docs A')
        ->firstOrFail()
        ->load('sections');
    $first = publishTechDocPackage($owner, $product, $first);

    test()->actingAs($owner)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => 'v1 docs B',
            'version_label' => '1.1',
            'locale' => 'en',
            'product_version_id' => $versionA->id,
            'inherit_from_previous' => true,
        ])
        ->assertRedirect();

    $second = TechnicalDocumentationPackage::query()
        ->where('title', 'v1 docs B')
        ->firstOrFail()
        ->load('sections');

    expect($second->supersedes_id)->toBe($first->id);

    $architecture = $second->sections
        ->firstWhere('section_key', TechnicalDocumentationSectionKey::Architecture);

    expect($architecture?->body_markdown)->toContain('Inherited trust boundaries');
});
