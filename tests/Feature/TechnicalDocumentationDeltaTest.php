<?php

use App\Enums\ClassificationStatus;
use App\Enums\EvidenceConfidentiality;
use App\Enums\EvidenceFreshnessStatus;
use App\Enums\EvidenceType;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ProductVersionState;
use App\Enums\ScopeStatus;
use App\Enums\SupportStatus;
use App\Enums\TechnicalDocumentationSectionKey;
use App\Enums\TechnicalDocumentationSectionSource;
use App\Enums\TechnicalDocumentationStatus;
use App\Models\Evidence;
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
 * @return array{organization: Organization, owner: User, product: Product, versionB: ProductVersion}
 */
function makeTechDocDeltaFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Tech Doc Delta Org',
        'slug' => 'tech-doc-delta-org-' . uniqid(),
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
        'name' => 'Tech Doc Delta Product',
        'slug' => 'tech-doc-delta-product-' . uniqid(),
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
        'manufacturer' => 'Avalon Labs',
    ]);

    $versionB = ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '2.0.0',
        'state' => ProductVersionState::Released,
        'support_status' => SupportStatus::Supported,
    ]);

    return compact('organization', 'owner', 'product', 'versionB');
}

function publishTechDocDeltaParent(User $owner, Product $product): TechnicalDocumentationPackage
{
    test()->actingAs($owner)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => 'Parent docs',
            'version_label' => '1.0',
            'locale' => 'en',
        ])
        ->assertRedirect();

    $package = TechnicalDocumentationPackage::query()
        ->where('product_id', $product->id)
        ->firstOrFail()
        ->load('sections');

    $package->sections()
        ->whereIn('source', [
            TechnicalDocumentationSectionSource::Authored->value,
            TechnicalDocumentationSectionSource::Linked->value,
        ])
        ->update([
            'is_applicable' => false,
            'override_reason' => 'N/A for delta fixture.',
            'body_markdown' => null,
        ]);

    $package->sections()
        ->where('section_key', TechnicalDocumentationSectionKey::Architecture->value)
        ->update([
            'is_applicable' => true,
            'override_reason' => null,
            'body_markdown' => "## Architecture\n\nParent trust boundaries.",
        ]);

    test()->actingAs($owner)
        ->post(route('products.technical-documentation.publish', [$product, $package]))
        ->assertRedirect();

    return $package->fresh(['sections']);
}

test('edit exposes delta props for changed inherited sections and stale evidence', function () {
    ['owner' => $owner, 'product' => $product, 'versionB' => $versionB] = makeTechDocDeltaFixture();

    $parent = publishTechDocDeltaParent($owner, $product);

    Evidence::query()->create([
        'organization_id' => $product->organization_id,
        'product_id' => $product->id,
        'type' => EvidenceType::Other,
        'title' => 'Expired test report',
        'confidentiality' => EvidenceConfidentiality::Internal,
        'freshness_status' => EvidenceFreshnessStatus::Expired,
        'valid_until' => now()->subDay(),
        'uploaded_by' => $owner->id,
    ]);

    Evidence::query()->create([
        'organization_id' => $product->organization_id,
        'product_id' => $product->id,
        'type' => EvidenceType::Other,
        'title' => 'Current design review',
        'confidentiality' => EvidenceConfidentiality::Internal,
        'freshness_status' => EvidenceFreshnessStatus::Current,
        'uploaded_by' => $owner->id,
    ]);

    test()->actingAs($owner)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => 'Child docs',
            'version_label' => '2.0',
            'locale' => 'en',
            'product_version_id' => $versionB->id,
            'inherit_from_previous' => true,
        ])
        ->assertRedirect();

    $child = TechnicalDocumentationPackage::query()
        ->where('title', 'Child docs')
        ->firstOrFail()
        ->load('sections');

    expect($child->supersedes_id)->toBe($parent->id);

    $sections = $child->sections->map(function ($section) {
        $payload = [
            'section_key' => $section->section_key->value,
            'body_markdown' => $section->body_markdown,
            'is_applicable' => $section->is_applicable,
            'override_reason' => $section->override_reason,
            'sort_order' => $section->sort_order,
        ];

        if ($section->section_key === TechnicalDocumentationSectionKey::Architecture) {
            $payload['body_markdown'] = "## Architecture\n\nChild trust boundaries.";
        }

        return $payload;
    })->all();

    test()->actingAs($owner)
        ->put(route('products.technical-documentation.update', [$product, $child]), [
            'title' => 'Child docs',
            'version_label' => '2.0',
            'locale' => 'en',
            'product_version_id' => $versionB->id,
            'sections' => $sections,
        ])
        ->assertRedirect();

    $child->refresh()->load('sections');
    $architecture = $child->sections
        ->firstWhere('section_key', TechnicalDocumentationSectionKey::Architecture);

    expect($architecture?->changed_since_parent)->toBeTrue();

    test()->actingAs($owner)
        ->get(route('products.technical-documentation.edit', [$product, $child]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/technical-documentation/Edit')
            ->where('package.supersedes_id', $parent->id)
            ->where(
                'package.supersedes_sections.architecture.body_markdown',
                "## Architecture\n\nParent trust boundaries.",
            )
            ->where('package.sections.0.section_key', fn($key) => is_string($key))
            ->has('evidenceFreshness')
            ->where('evidenceFreshness.total', 2)
            ->where('evidenceFreshness.expired', 1)
            ->where('evidenceFreshness.stale', 1)
            ->where('evidenceFreshness.current', 1));

    $changed = $child->sections->firstWhere('changed_since_parent', true);
    expect($changed?->section_key)->toBe(TechnicalDocumentationSectionKey::Architecture);
});

test('edit without supersedes still returns evidence freshness summary', function () {
    ['owner' => $owner, 'product' => $product] = makeTechDocDeltaFixture();

    test()->actingAs($owner)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => 'Solo package',
            'version_label' => '1.0',
            'locale' => 'en',
        ])
        ->assertRedirect();

    $package = TechnicalDocumentationPackage::query()
        ->where('title', 'Solo package')
        ->firstOrFail();

    test()->actingAs($owner)
        ->get(route('products.technical-documentation.edit', [$product, $package]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->where('package.supersedes_id', null)
            ->where('evidenceFreshness.total', 0)
            ->where('evidenceFreshness.stale', 0));
});
