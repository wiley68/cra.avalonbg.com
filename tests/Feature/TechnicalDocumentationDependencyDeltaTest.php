<?php

use App\Enums\ClassificationStatus;
use App\Enums\ComponentSupportStatus;
use App\Enums\LicensingModel;
use App\Enums\PackageEcosystem;
use App\Enums\ProductType;
use App\Enums\ProductVersionState;
use App\Enums\ScopeStatus;
use App\Enums\SupportStatus;
use App\Enums\TechnicalDocumentationSectionKey;
use App\Enums\TechnicalDocumentationSectionSource;
use App\Enums\TechnicalDocumentationStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductComponent;
use App\Models\ProductVersion;
use App\Models\Role;
use App\Models\TechnicalDocumentationPackage;
use App\Models\User;
use App\Services\TechnicalDocumentationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

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
function makeTechDocDependencyDeltaFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Tech Doc Dep Delta Org',
        'slug' => 'tech-doc-dep-delta-org-' . uniqid(),
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
        'name' => 'Tech Doc Dep Delta Product',
        'slug' => 'tech-doc-dep-delta-product-' . uniqid(),
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

    // Shared (unchanged)
    ProductComponent::query()->create([
        'product_id' => $product->id,
        'product_version_id' => $versionA->id,
        'name' => 'shared/lib',
        'package_ecosystem' => PackageEcosystem::Composer,
        'version' => '1.0.0',
        'purl' => 'pkg:composer/shared/lib@1.0.0',
        'is_direct' => true,
        'is_dev' => false,
        'support_status' => ComponentSupportStatus::Unknown,
    ]);
    ProductComponent::query()->create([
        'product_id' => $product->id,
        'product_version_id' => $versionB->id,
        'name' => 'shared/lib',
        'package_ecosystem' => PackageEcosystem::Composer,
        'version' => '1.0.0',
        'purl' => 'pkg:composer/shared/lib@1.0.0',
        'is_direct' => true,
        'is_dev' => false,
        'support_status' => ComponentSupportStatus::Unknown,
    ]);

    // Removed in B
    ProductComponent::query()->create([
        'product_id' => $product->id,
        'product_version_id' => $versionA->id,
        'name' => 'old/pkg',
        'package_ecosystem' => PackageEcosystem::Composer,
        'version' => '0.9.0',
        'purl' => 'pkg:composer/old/pkg@0.9.0',
        'is_direct' => true,
        'is_dev' => false,
        'support_status' => ComponentSupportStatus::Unknown,
    ]);

    // Added in B
    ProductComponent::query()->create([
        'product_id' => $product->id,
        'product_version_id' => $versionB->id,
        'name' => 'new/pkg',
        'package_ecosystem' => PackageEcosystem::Composer,
        'version' => '2.1.0',
        'purl' => 'pkg:composer/new/pkg@2.1.0',
        'is_direct' => true,
        'is_dev' => false,
        'support_status' => ComponentSupportStatus::Unknown,
    ]);

    // Version changed
    ProductComponent::query()->create([
        'product_id' => $product->id,
        'product_version_id' => $versionA->id,
        'name' => 'guzzlehttp/guzzle',
        'package_ecosystem' => PackageEcosystem::Composer,
        'version' => '7.0.0',
        'purl' => 'pkg:composer/guzzlehttp/guzzle@7.0.0',
        'is_direct' => true,
        'is_dev' => false,
        'support_status' => ComponentSupportStatus::Unknown,
    ]);
    ProductComponent::query()->create([
        'product_id' => $product->id,
        'product_version_id' => $versionB->id,
        'name' => 'guzzlehttp/guzzle',
        'package_ecosystem' => PackageEcosystem::Composer,
        'version' => '7.8.0',
        'purl' => 'pkg:composer/guzzlehttp/guzzle@7.8.0',
        'is_direct' => true,
        'is_dev' => false,
        'support_status' => ComponentSupportStatus::Unknown,
    ]);

    return compact('organization', 'owner', 'product', 'versionA', 'versionB');
}

function publishVersionPinnedTechDoc(
    User $owner,
    Product $product,
    ProductVersion $version,
    string $title,
    string $versionLabel,
): TechnicalDocumentationPackage {
    test()->actingAs($owner)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => $title,
            'version_label' => $versionLabel,
            'locale' => 'en',
            'product_version_id' => $version->id,
            'inherit_from_previous' => true,
        ])
        ->assertRedirect();

    $package = TechnicalDocumentationPackage::query()
        ->where('product_id', $product->id)
        ->where('title', $title)
        ->firstOrFail()
        ->load('sections');

    $package->sections()
        ->whereIn('source', [
            TechnicalDocumentationSectionSource::Authored->value,
            TechnicalDocumentationSectionSource::Linked->value,
        ])
        ->update([
            'is_applicable' => false,
            'override_reason' => 'N/A for dependency delta fixture.',
            'body_markdown' => null,
        ]);

    $package->sections()
        ->where('section_key', TechnicalDocumentationSectionKey::Architecture->value)
        ->update([
            'is_applicable' => true,
            'override_reason' => null,
            'body_markdown' => '## Architecture\n\nStub.',
        ]);

    test()->actingAs($owner)
        ->post(route('products.technical-documentation.publish', [$product, $package]))
        ->assertRedirect();

    return $package->fresh(['sections']);
}

test('edit exposes dependency delta between product versions', function () {
    [
        'owner' => $owner,
        'product' => $product,
        'versionA' => $versionA,
        'versionB' => $versionB,
    ] = makeTechDocDependencyDeltaFixture();

    $parent = publishVersionPinnedTechDoc($owner, $product, $versionA, 'Parent v1 docs', '1.0');

    test()->actingAs($owner)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => 'Child v2 docs',
            'version_label' => '2.0',
            'locale' => 'en',
            'product_version_id' => $versionB->id,
            'inherit_from_previous' => true,
        ])
        ->assertRedirect();

    $child = TechnicalDocumentationPackage::query()
        ->where('title', 'Child v2 docs')
        ->firstOrFail();

    expect($child->supersedes_id)->toBe($parent->id);

    $parentVersionNumber = '1.0.0';
    $currentVersionNumber = '2.0.0';
    $fromVersion = '7.0.0';
    $toVersion = '7.8.0';
    $addedName = 'new/pkg';
    $removedName = 'old/pkg';
    $changedName = 'guzzlehttp/guzzle';

    $this->actingAs($owner)
        ->get(route('products.technical-documentation.edit', [$product, $child]))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('products/technical-documentation/Edit')
            ->where('package.dependency_delta.available', true)
            ->where('package.dependency_delta.parent_version_number', $parentVersionNumber)
            ->where('package.dependency_delta.current_version_number', $currentVersionNumber)
            ->where('package.dependency_delta.counts.added', 1)
            ->where('package.dependency_delta.counts.removed', 1)
            ->where('package.dependency_delta.counts.changed', 1)
            ->where('package.dependency_delta.counts.unchanged', 1)
            ->where('package.dependency_delta.added.0.name', $addedName)
            ->where('package.dependency_delta.removed.0.name', $removedName)
            ->where('package.dependency_delta.changed.0.name', $changedName)
            ->where('package.dependency_delta.changed.0.from_version', $fromVersion)
            ->where('package.dependency_delta.changed.0.to_version', $toVersion));
});

test('dependency delta requires product versions on both packages', function () {
    ['owner' => $owner, 'product' => $product, 'versionB' => $versionB] = makeTechDocDependencyDeltaFixture();

    test()->actingAs($owner)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => 'Product-wide parent',
            'version_label' => '1.0',
            'locale' => 'en',
        ])
        ->assertRedirect();

    $parent = TechnicalDocumentationPackage::query()
        ->where('title', 'Product-wide parent')
        ->firstOrFail()
        ->load('sections');

    $parent->sections()
        ->whereIn('source', [
            TechnicalDocumentationSectionSource::Authored->value,
            TechnicalDocumentationSectionSource::Linked->value,
        ])
        ->update([
            'is_applicable' => false,
            'override_reason' => 'N/A',
            'body_markdown' => null,
        ]);

    $parent->sections()
        ->where('section_key', TechnicalDocumentationSectionKey::Architecture->value)
        ->update([
            'is_applicable' => true,
            'body_markdown' => '## Architecture',
        ]);

    test()->actingAs($owner)
        ->post(route('products.technical-documentation.publish', [$product, $parent]))
        ->assertRedirect();

    test()->actingAs($owner)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => 'Versioned child',
            'version_label' => '2.0',
            'locale' => 'en',
            'product_version_id' => $versionB->id,
            'inherit_from_previous' => true,
        ])
        ->assertRedirect();

    $child = TechnicalDocumentationPackage::query()
        ->where('title', 'Versioned child')
        ->firstOrFail();

    $delta = app(TechnicalDocumentationService::class)->dependencyDelta($child);

    expect($delta)->not->toBeNull()
        ->and($delta['available'])->toBeFalse()
        ->and($delta['unavailable_reason'])->toBe('missing_product_versions');
});

test('dependency delta is null without supersedes', function () {
    ['owner' => $owner, 'product' => $product, 'versionA' => $versionA] = makeTechDocDependencyDeltaFixture();

    $package = publishVersionPinnedTechDoc($owner, $product, $versionA, 'Only package', '1.0');

    expect($package->supersedes_id)->toBeNull()
        ->and(app(TechnicalDocumentationService::class)->dependencyDelta($package))->toBeNull();
});
