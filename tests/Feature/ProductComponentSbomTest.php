<?php

use App\Enums\ClassificationStatus;
use App\Enums\ComponentSupportStatus;
use App\Enums\LicensingModel;
use App\Enums\PackageEcosystem;
use App\Enums\ProductType;
use App\Enums\ProductVersionState;
use App\Enums\ScopeStatus;
use App\Enums\SbomFormat;
use App\Enums\SupportStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductComponent;
use App\Models\ProductVersion;
use App\Models\Role;
use App\Models\Sbom;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * @return array{0: Organization, 1: User}
 */
function makeComponentsOrgWithOwner(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Components Org',
        'slug' => 'components-org',
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

    return [$organization, $owner];
}

function makeComponentsOrgReadOnly(Organization $organization): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $role = Role::query()->where('slug', 'read_only')->firstOrFail();

    $organization->users()->attach($user->id, [
        'role_id' => $role->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $user;
}

/**
 * @return array{0: Product, 1: ProductVersion}
 */
function makeProductWithVersionForComponents(Organization $organization, User $owner): array
{
    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Payments Module Components',
        'slug' => 'payments-module-components',
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

    $version = ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '1.0.0',
        'release_date' => now()->toDateString(),
        'state' => ProductVersionState::Draft,
        'support_status' => SupportStatus::Supported,
    ]);

    return [$product, $version];
}

test('owner can create and update a product component', function () {
    [$organization, $owner] = makeComponentsOrgWithOwner();
    [$product, $version] = makeProductWithVersionForComponents($organization, $owner);

    $this->actingAs($owner)
        ->post(route('products.components.store', $product), [
            'product_version_id' => $version->id,
            'name' => 'laravel/framework',
            'supplier' => 'Laravel',
            'package_ecosystem' => PackageEcosystem::Composer->value,
            'version' => 'v11.0.0',
            'licence' => 'MIT',
            'purl' => 'pkg:composer/laravel/framework@v11.0.0',
            'hash' => null,
            'is_direct' => true,
            'is_dev' => false,
            'usage_context' => 'runtime',
            'support_status' => ComponentSupportStatus::Supported->value,
            'notes' => 'Core framework',
        ])
        ->assertRedirect();

    $component = ProductComponent::query()
        ->where('product_id', $product->id)
        ->where('name', 'laravel/framework')
        ->firstOrFail();

    expect($component->package_ecosystem)->toBe(PackageEcosystem::Composer);
    expect($component->product_version_id)->toBe($version->id);

    $this->actingAs($owner)
        ->put(route('products.components.update', [$product, $component]), [
            'product_version_id' => $version->id,
            'name' => 'laravel/framework',
            'supplier' => 'Laravel LLC',
            'package_ecosystem' => PackageEcosystem::Composer->value,
            'version' => 'v11.1.0',
            'licence' => 'MIT',
            'purl' => 'pkg:composer/laravel/framework@v11.1.0',
            'is_direct' => true,
            'is_dev' => false,
            'usage_context' => 'runtime',
            'support_status' => ComponentSupportStatus::Supported->value,
            'notes' => 'Updated',
        ])
        ->assertRedirect();

    expect($component->fresh()->version)->toBe('v11.1.0');
    expect($component->fresh()->supplier)->toBe('Laravel LLC');
});

test('read-only user can view components but cannot create', function () {
    [$organization, $owner] = makeComponentsOrgWithOwner();
    [$product, $version] = makeProductWithVersionForComponents($organization, $owner);
    $viewer = makeComponentsOrgReadOnly($organization);

    $this->actingAs($viewer)
        ->get(route('products.components.index', $product))
        ->assertOk();

    $this->actingAs($viewer)
        ->post(route('products.components.store', $product), [
            'product_version_id' => $version->id,
            'name' => 'forbidden/package',
            'package_ecosystem' => PackageEcosystem::Composer->value,
            'support_status' => ComponentSupportStatus::Unknown->value,
        ])
        ->assertForbidden();
});

test('owner can import cyclonedx json sbom', function () {
    Storage::fake('local');

    [$organization, $owner] = makeComponentsOrgWithOwner();
    [$product, $version] = makeProductWithVersionForComponents($organization, $owner);

    $contents = file_get_contents(base_path('tests/Fixtures/sbom-cyclonedx-sample.json'));
    $file = UploadedFile::fake()->createWithContent('bom.json', $contents);

    $this->actingAs($owner)
        ->post(route('products.components.import.store', $product), [
            'product_version_id' => $version->id,
            'file' => $file,
        ])
        ->assertRedirect(route('products.components.index', [
            'product' => $product,
            'version_id' => $version->id,
        ]));

    expect(Sbom::query()->where('product_id', $product->id)->count())->toBe(1);
    expect(ProductComponent::query()->where('product_version_id', $version->id)->count())->toBe(2);

    $guzzle = ProductComponent::query()
        ->where('purl', 'pkg:composer/guzzlehttp/guzzle@7.8.1')
        ->firstOrFail();

    expect($guzzle->licence)->toBe('MIT');
    expect($guzzle->package_ecosystem)->toBe(PackageEcosystem::Composer);
    expect($guzzle->sbom_id)->not->toBeNull();

    $sbom = Sbom::query()->firstOrFail();
    expect($sbom->format)->toBe(SbomFormat::CycloneDxJson);
    expect($sbom->component_count)->toBe(2);
    expect(Storage::disk('local')->exists($sbom->storage_path))->toBeTrue();
});

test('owner can import composer.lock and upsert on reimport', function () {
    Storage::fake('local');

    [$organization, $owner] = makeComponentsOrgWithOwner();
    [$product, $version] = makeProductWithVersionForComponents($organization, $owner);

    $contents = file_get_contents(base_path('tests/Fixtures/composer.lock.sample.json'));
    $file = UploadedFile::fake()->createWithContent('composer.lock', $contents);

    $this->actingAs($owner)
        ->post(route('products.components.import.store', $product), [
            'product_version_id' => $version->id,
            'format' => SbomFormat::ComposerLock->value,
            'file' => $file,
        ])
        ->assertRedirect();

    expect(ProductComponent::query()->where('product_version_id', $version->id)->count())->toBe(3);

    $pest = ProductComponent::query()
        ->where('name', 'pestphp/pest')
        ->firstOrFail();

    expect($pest->is_dev)->toBeTrue();
    expect($pest->purl)->toBe('pkg:composer/pestphp/pest@v3.0.0');

    $guzzle = ProductComponent::query()
        ->where('name', 'guzzlehttp/guzzle')
        ->firstOrFail();

    $guzzle->update(['notes' => 'manual note', 'licence' => 'Apache-2.0']);

    $reimport = UploadedFile::fake()->createWithContent('composer.lock', $contents);

    $this->actingAs($owner)
        ->post(route('products.components.import.store', $product), [
            'product_version_id' => $version->id,
            'file' => $reimport,
        ])
        ->assertRedirect();

    expect(ProductComponent::query()->where('product_version_id', $version->id)->count())->toBe(3);
    expect(Sbom::query()->where('product_id', $product->id)->count())->toBe(2);

    $guzzleFresh = $guzzle->fresh();
    expect($guzzleFresh->licence)->toBe('MIT');
    expect($guzzleFresh->notes)->toBeNull();
});

test('internal api lists components filtered by version', function () {
    [$organization, $owner] = makeComponentsOrgWithOwner();
    [$product, $version] = makeProductWithVersionForComponents($organization, $owner);

    $otherVersion = ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '2.0.0',
        'state' => ProductVersionState::Draft,
        'support_status' => SupportStatus::Supported,
    ]);

    ProductComponent::query()->create([
        'product_id' => $product->id,
        'product_version_id' => $version->id,
        'name' => 'pkg/a',
        'package_ecosystem' => PackageEcosystem::Composer,
        'version' => '1.0.0',
        'purl' => 'pkg:composer/pkg/a@1.0.0',
        'is_direct' => true,
        'is_dev' => false,
        'support_status' => ComponentSupportStatus::Unknown,
    ]);

    ProductComponent::query()->create([
        'product_id' => $product->id,
        'product_version_id' => $otherVersion->id,
        'name' => 'pkg/b',
        'package_ecosystem' => PackageEcosystem::Npm,
        'version' => '2.0.0',
        'purl' => 'pkg:npm/pkg/b@2.0.0',
        'is_direct' => true,
        'is_dev' => false,
        'support_status' => ComponentSupportStatus::Unknown,
    ]);

    $this->actingAs($owner)
        ->getJson(route('internal.products.components.index', [
            'product' => $product,
            'version_id' => $version->id,
        ]))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.name', 'pkg/a');
});

test('owner can delete a component', function () {
    [$organization, $owner] = makeComponentsOrgWithOwner();
    [$product, $version] = makeProductWithVersionForComponents($organization, $owner);

    $component = ProductComponent::query()->create([
        'product_id' => $product->id,
        'product_version_id' => $version->id,
        'name' => 'to-delete',
        'package_ecosystem' => PackageEcosystem::Other,
        'support_status' => ComponentSupportStatus::Unknown,
    ]);

    $this->actingAs($owner)
        ->delete(route('products.components.destroy', [$product, $component]))
        ->assertRedirect(route('products.components.index', $product));

    expect(ProductComponent::query()->whereKey($component->id)->exists())->toBeFalse();
});
