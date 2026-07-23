<?php

use App\Enums\AuditEventType;
use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\TechnicalDocumentationSectionKey;
use App\Enums\TechnicalDocumentationSectionSource;
use App\Enums\TechnicalDocumentationStatus;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Role;
use App\Models\TechnicalDocumentationPackage;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User, product: Product}
 */
function makeTechDocOrgWithOwner(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Tech Doc CRUD Org',
        'slug' => 'tech-doc-crud-org-' . uniqid(),
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
        'name' => 'Tech Doc CRUD Product',
        'slug' => 'tech-doc-crud-product-' . uniqid(),
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    return compact('organization', 'owner', 'product');
}

function makeTechDocOrgViewer(Organization $organization): User
{
    $viewer = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $role = Role::query()->where('slug', 'read_only')->firstOrFail();
    $organization->users()->attach($viewer->id, [
        'role_id' => $role->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $viewer;
}

test('owner can view technical documentation index', function () {
    ['owner' => $owner, 'product' => $product] = makeTechDocOrgWithOwner();

    $this->actingAs($owner)
        ->get(route('products.technical-documentation.index', $product))
        ->assertOk();
});

test('owner can create technical documentation package with seeded sections', function () {
    ['owner' => $owner, 'product' => $product] = makeTechDocOrgWithOwner();

    $this->actingAs($owner)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => 'Technical documentation',
            'version_label' => '1.0',
            'locale' => 'en',
            'notes' => 'Initial draft',
        ])
        ->assertRedirect();

    $package = TechnicalDocumentationPackage::query()
        ->where('product_id', $product->id)
        ->firstOrFail()
        ->load('sections');

    expect($package->title)->toBe('Technical documentation')
        ->and($package->status)->toBe(TechnicalDocumentationStatus::Draft)
        ->and($package->sections)->toHaveCount(18);

    $sbom = $package->sections
        ->firstWhere('section_key', TechnicalDocumentationSectionKey::Sbom);

    expect($sbom?->source)->toBe(TechnicalDocumentationSectionSource::Generated);

    $architecture = $package->sections
        ->firstWhere('section_key', TechnicalDocumentationSectionKey::Architecture);

    expect($architecture?->source)->toBe(TechnicalDocumentationSectionSource::Authored);

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::TechnicalDocumentationCreated)
        ->exists())->toBeTrue();
});

test('create validates required fields', function () {
    ['owner' => $owner, 'product' => $product] = makeTechDocOrgWithOwner();

    $this->actingAs($owner)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => '',
            'version_label' => '',
            'locale' => 'en',
        ])
        ->assertSessionHasErrors(['title', 'version_label']);
});

test('owner can update package metadata', function () {
    ['owner' => $owner, 'product' => $product] = makeTechDocOrgWithOwner();

    $this->actingAs($owner)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => 'Original',
            'version_label' => '1.0',
            'locale' => 'en',
        ])
        ->assertRedirect();

    $package = TechnicalDocumentationPackage::query()
        ->where('product_id', $product->id)
        ->firstOrFail();

    $this->actingAs($owner)
        ->put(route('products.technical-documentation.update', [$product, $package]), [
            'title' => 'Updated title',
            'version_label' => '1.1',
            'locale' => 'bg',
            'notes' => 'Updated notes',
        ])
        ->assertRedirect(route('products.technical-documentation.edit', [$product, $package]));

    $package->refresh();

    expect($package->title)->toBe('Updated title')
        ->and($package->version_label)->toBe('1.1')
        ->and($package->locale)->toBe('bg')
        ->and($package->notes)->toBe('Updated notes');
});

test('owner can delete draft package', function () {
    ['owner' => $owner, 'product' => $product] = makeTechDocOrgWithOwner();

    $this->actingAs($owner)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => 'To delete',
            'version_label' => '1.0',
            'locale' => 'en',
        ])
        ->assertRedirect();

    $package = TechnicalDocumentationPackage::query()
        ->where('product_id', $product->id)
        ->firstOrFail();

    $this->actingAs($owner)
        ->delete(route('products.technical-documentation.destroy', [$product, $package]))
        ->assertRedirect(route('products.technical-documentation.index', $product));

    expect(TechnicalDocumentationPackage::query()->whereKey($package->id)->exists())->toBeFalse();
});

test('viewer can open index and api but cannot create', function () {
    ['organization' => $organization, 'owner' => $owner, 'product' => $product] = makeTechDocOrgWithOwner();
    $viewer = makeTechDocOrgViewer($organization);

    $this->actingAs($owner)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => 'Visible package',
            'version_label' => '1.0',
            'locale' => 'en',
        ])
        ->assertRedirect();

    $this->actingAs($viewer)
        ->get(route('products.technical-documentation.index', $product))
        ->assertOk();

    $this->actingAs($viewer)
        ->getJson(route('internal.products.technical-documentation.index', $product))
        ->assertOk()
        ->assertJsonPath('total', 1);

    $this->actingAs($viewer)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => 'Forbidden',
            'version_label' => '1.0',
            'locale' => 'en',
        ])
        ->assertForbidden();
});

test('edit page lists seeded sections', function () {
    ['owner' => $owner, 'product' => $product] = makeTechDocOrgWithOwner();

    $this->actingAs($owner)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => 'Edit me',
            'version_label' => '1.0',
            'locale' => 'en',
        ])
        ->assertRedirect();

    $package = TechnicalDocumentationPackage::query()
        ->where('product_id', $product->id)
        ->firstOrFail();

    $this->actingAs($owner)
        ->get(route('products.technical-documentation.edit', [$product, $package]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/technical-documentation/Edit')
            ->has('package.sections', 18)
            ->where('package.title', 'Edit me'));
});
