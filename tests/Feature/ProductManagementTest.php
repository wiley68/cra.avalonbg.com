<?php

use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ProductVersionState;
use App\Enums\ScopeStatus;
use App\Enums\SupportStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVersion;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makePlatformAdminUser(): User
{
    test()->seed([RolePermissionSeeder::class]);

    return User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => true,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);
}

/**
 * @return array{0: Organization, 1: User}
 */
function makeOrgWithOwner(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Acme Soft',
        'slug' => 'acme-soft',
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

function makeOrgDeveloper(Organization $organization): User
{
    $developer = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $role = Role::query()->where('slug', 'developer')->firstOrFail();

    $organization->users()->attach($developer->id, [
        'role_id' => $role->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $developer;
}

function productPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Payment Module',
        'slug' => 'payment-module',
        'product_line' => 'Commerce',
        'description' => 'WooCommerce payment module',
        'intended_purpose' => 'Accept card payments',
        'product_type' => ProductType::Software->value,
        'manufacturer' => 'Acme Soft',
        'trademark' => 'AcmePay',
        'licensing_model' => LicensingModel::Paid->value,
        'has_remote_data_processing' => true,
        'has_network_connectivity' => true,
        'deployment_model' => 'WordPress plugin',
        'support_period_notes' => '2 years',
        'end_of_support_policy' => 'Security fixes only after EOS',
        'product_owner_user_id' => null,
        'security_contact_user_id' => null,
        'scope_status' => ScopeStatus::LikelyInScope->value,
        'scope_rationale' => 'Commercial software with network connectivity',
        'classification_status' => ClassificationStatus::Unclassified->value,
        'classification_rationale' => 'Initial assessment pending',
        'classification_next_review_at' => now()->addYear()->toDateString(),
    ], $overrides);
}

function versionPayload(array $overrides = []): array
{
    return array_merge([
        'version_number' => '1.0.0',
        'release_date' => now()->toDateString(),
        'state' => ProductVersionState::Draft->value,
        'support_status' => SupportStatus::Supported->value,
        'security_support_deadline' => now()->addYear()->toDateString(),
        'git_ref' => 'v1.0.0',
        'build_identifier' => 'build-1',
        'artifact_hash' => 'abc123',
        'changelog' => 'Initial release',
        'previous_version_id' => null,
    ], $overrides);
}

test('organization owner can create and list products', function () {
    [$organization, $owner] = makeOrgWithOwner();

    $this->actingAs($owner)
        ->get(route('products.index'))
        ->assertOk();

    $response = $this->actingAs($owner)->post(route('products.store'), productPayload([
        'skip_scope_wizard' => true,
    ]));

    $product = Product::query()->where('slug', 'payment-module')->first();

    expect($product)->not->toBeNull();
    expect($product->organization_id)->toBe($organization->id);
    $response->assertRedirect(route('products.edit', $product));
});

test('developer can view products but cannot create', function () {
    [$organization] = makeOrgWithOwner();
    $developer = makeOrgDeveloper($organization);

    $this->actingAs($developer)
        ->get(route('products.index'))
        ->assertOk();

    $this->actingAs($developer)
        ->post(route('products.store'), productPayload())
        ->assertForbidden();
});

test('platform admin cannot access products', function () {
    makeOrgWithOwner();
    $admin = makePlatformAdminUser();

    $this->actingAs($admin)
        ->get(route('products.index'))
        ->assertForbidden();
});

test('organization owner can update and delete products', function () {
    [$organization, $owner] = makeOrgWithOwner();

    $this->actingAs($owner)->post(route('products.store'), productPayload());
    $product = Product::query()->firstOrFail();

    $this->actingAs($owner)
        ->put(route('products.update', $product), productPayload([
            'name' => 'Payment Module Pro',
            'slug' => 'payment-module-pro',
            'classification_status' => ClassificationStatus::General->value,
        ]))
        ->assertRedirect(route('products.edit', $product));

    expect($product->refresh()->name)->toBe('Payment Module Pro');
    expect($product->classification_status)->toBe(ClassificationStatus::General);

    $this->actingAs($owner)
        ->delete(route('products.destroy', $product))
        ->assertRedirect(route('products.index'));

    $this->assertDatabaseMissing('products', ['id' => $product->id]);
});

test('organization owner can manage product versions', function () {
    [$organization, $owner] = makeOrgWithOwner();

    $this->actingAs($owner)->post(route('products.store'), productPayload());
    $product = Product::query()->firstOrFail();

    $this->actingAs($owner)
        ->post(route('products.versions.store', $product), versionPayload())
        ->assertRedirect(route('products.versions.index', $product));

    $version = ProductVersion::query()->firstOrFail();
    expect($version->version_number)->toBe('1.0.0');
    expect($version->state)->toBe(ProductVersionState::Draft);

    $this->actingAs($owner)
        ->put(route('products.versions.update', [$product, $version]), versionPayload([
            'version_number' => '1.0.1',
            'state' => ProductVersionState::Released->value,
        ]))
        ->assertRedirect(route('products.versions.index', $product));

    expect($version->refresh()->version_number)->toBe('1.0.1');
    expect($version->state)->toBe(ProductVersionState::Released);
});

test('version numbers must be unique per product', function () {
    [$organization, $owner] = makeOrgWithOwner();

    $this->actingAs($owner)->post(route('products.store'), productPayload());
    $product = Product::query()->firstOrFail();

    $this->actingAs($owner)
        ->post(route('products.versions.store', $product), versionPayload())
        ->assertRedirect();

    $this->actingAs($owner)
        ->from(route('products.versions.create', $product))
        ->post(route('products.versions.store', $product), versionPayload())
        ->assertRedirect()
        ->assertSessionHasErrors('version_number');
});

test('deleting a product cascades versions', function () {
    [$organization, $owner] = makeOrgWithOwner();

    $this->actingAs($owner)->post(route('products.store'), productPayload());
    $product = Product::query()->firstOrFail();

    $this->actingAs($owner)
        ->post(route('products.versions.store', $product), versionPayload());

    $versionId = ProductVersion::query()->value('id');

    $this->actingAs($owner)
        ->delete(route('products.destroy', $product))
        ->assertRedirect(route('products.index'));

    $this->assertDatabaseMissing('product_versions', ['id' => $versionId]);
});

test('developer cannot create versions', function () {
    [$organization, $owner] = makeOrgWithOwner();
    $developer = makeOrgDeveloper($organization);

    $this->actingAs($owner)->post(route('products.store'), productPayload());
    $product = Product::query()->firstOrFail();

    $this->actingAs($developer)
        ->get(route('products.versions.index', $product))
        ->assertOk();

    $this->actingAs($developer)
        ->post(route('products.versions.store', $product), versionPayload())
        ->assertForbidden();
});

test('products internal api returns paginated results', function () {
    [$organization, $owner] = makeOrgWithOwner();

    $this->actingAs($owner)->post(route('products.store'), productPayload());

    $this->actingAs($owner)
        ->getJson(route('internal.products.index', [
            'page' => 1,
            'per_page' => 10,
            'sort_by' => 'name',
            'sort_desc' => 0,
            'search' => 'Payment',
        ]))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.slug', 'payment-module');
});

test('product versions internal api returns paginated results', function () {
    [$organization, $owner] = makeOrgWithOwner();

    $this->actingAs($owner)->post(route('products.store'), productPayload());
    $product = Product::query()->firstOrFail();

    $this->actingAs($owner)
        ->post(route('products.versions.store', $product), versionPayload());

    $this->actingAs($owner)
        ->getJson(route('internal.products.versions.index', $product))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.version_number', '1.0.0');
});

test('invalid scope status is rejected', function () {
    [$organization, $owner] = makeOrgWithOwner();

    $this->actingAs($owner)
        ->from(route('products.create'))
        ->post(route('products.store'), productPayload([
            'scope_status' => 'not-a-real-status',
        ]))
        ->assertRedirect()
        ->assertSessionHasErrors('scope_status');
});
