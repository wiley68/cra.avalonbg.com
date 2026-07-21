<?php

use App\Enums\AuditEventType;
use App\Enums\ClassificationStatus;
use App\Enums\CustomerCriticality;
use App\Enums\DeploymentEnvironment;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ProductVersionState;
use App\Enums\ScopeStatus;
use App\Enums\SupportStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductDeployment;
use App\Models\ProductVersion;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User, product: Product, customer: Customer, version: ProductVersion}
 */
function makeDeploymentFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Deploy CRUD Org',
        'slug' => 'deploy-crud-org',
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

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Deploy Product',
        'slug' => 'deploy-product',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => false,
        'scope_status' => ScopeStatus::InsufficientInformation,
        'classification_status' => ClassificationStatus::Unclassified,
    ]);

    $customer = Customer::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Acme Customer',
        'criticality' => CustomerCriticality::High,
        'is_active' => true,
    ]);

    $version = ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '1.0.0',
        'state' => ProductVersionState::Released,
        'support_status' => SupportStatus::Supported,
    ]);

    return compact('organization', 'owner', 'product', 'customer', 'version');
}

test('owner can create product deployment with audit', function () {
    ['owner' => $owner, 'product' => $product, 'customer' => $customer, 'version' => $version] = makeDeploymentFixture();

    $this->actingAs($owner)
        ->post(route('products.deployments.store', $product), [
            'customer_id' => $customer->id,
            'product_version_id' => $version->id,
            'environment' => DeploymentEnvironment::Production->value,
            'internet_exposure' => true,
            'custom_modifications' => false,
            'end_of_support_exception' => false,
        ])
        ->assertRedirect(route('products.deployments.index', $product));

    $deployment = ProductDeployment::query()->first();

    expect($deployment)->not->toBeNull()
        ->and($deployment->product_id)->toBe($product->id)
        ->and($deployment->customer_id)->toBe($customer->id)
        ->and($deployment->environment)->toBe(DeploymentEnvironment::Production)
        ->and($deployment->internet_exposure)->toBeTrue()
        ->and(AuditLog::query()->where('event_type', AuditEventType::DeploymentCreated)->count())->toBe(1);
});

test('duplicate customer product environment is rejected', function () {
    ['owner' => $owner, 'product' => $product, 'customer' => $customer, 'organization' => $organization] = makeDeploymentFixture();

    ProductDeployment::query()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'product_id' => $product->id,
        'environment' => DeploymentEnvironment::Staging,
    ]);

    $this->actingAs($owner)
        ->from(route('products.deployments.create', $product))
        ->post(route('products.deployments.store', $product), [
            'customer_id' => $customer->id,
            'environment' => DeploymentEnvironment::Staging->value,
        ])
        ->assertRedirect(route('products.deployments.create', $product))
        ->assertSessionHasErrors('environment');
});

test('owner can update and delete deployment with audit', function () {
    ['owner' => $owner, 'product' => $product, 'customer' => $customer, 'organization' => $organization] = makeDeploymentFixture();

    $deployment = ProductDeployment::query()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'product_id' => $product->id,
        'environment' => DeploymentEnvironment::Other,
        'internet_exposure' => false,
    ]);

    $this->actingAs($owner)
        ->put(route('products.deployments.update', [$product, $deployment]), [
            'customer_id' => $customer->id,
            'environment' => DeploymentEnvironment::Other->value,
            'internet_exposure' => true,
            'update_channel' => 'manual',
            'custom_modifications' => true,
            'end_of_support_exception' => false,
        ])
        ->assertRedirect(route('products.deployments.index', $product));

    expect($deployment->fresh()->internet_exposure)->toBeTrue()
        ->and($deployment->fresh()->update_channel)->toBe('manual')
        ->and(AuditLog::query()->where('event_type', AuditEventType::DeploymentUpdated)->count())->toBe(1);

    $this->actingAs($owner)
        ->delete(route('products.deployments.destroy', [$product, $deployment]))
        ->assertRedirect(route('products.deployments.index', $product));

    expect(ProductDeployment::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('event_type', AuditEventType::DeploymentDeleted)->count())->toBe(1);
});

test('viewer can list deployments but cannot create', function () {
    ['organization' => $organization, 'product' => $product, 'customer' => $customer] = makeDeploymentFixture();

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

    ProductDeployment::query()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'product_id' => $product->id,
        'environment' => DeploymentEnvironment::Production,
    ]);

    $this->actingAs($viewer)
        ->get(route('products.deployments.index', $product))
        ->assertOk()
        ->assertInertia(fn($page) => $page->where('canManage', false));

    $this->actingAs($viewer)
        ->getJson(route('internal.products.deployments.index', $product))
        ->assertOk()
        ->assertJsonPath('total', 1);

    $this->actingAs($viewer)
        ->post(route('products.deployments.store', $product), [
            'customer_id' => $customer->id,
            'environment' => DeploymentEnvironment::Staging->value,
        ])
        ->assertForbidden();
});

test('internal api searches deployments by customer name', function () {
    ['owner' => $owner, 'product' => $product, 'customer' => $customer, 'organization' => $organization] = makeDeploymentFixture();

    ProductDeployment::query()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'product_id' => $product->id,
        'environment' => DeploymentEnvironment::Production,
    ]);

    $other = Customer::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Zulu Other',
        'criticality' => CustomerCriticality::Low,
        'is_active' => true,
    ]);

    ProductDeployment::query()->create([
        'organization_id' => $organization->id,
        'customer_id' => $other->id,
        'product_id' => $product->id,
        'environment' => DeploymentEnvironment::Staging,
    ]);

    $this->actingAs($owner)
        ->getJson(route('internal.products.deployments.index', [
            'product' => $product->id,
            'search' => 'Acme',
        ]))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.customer_name', 'Acme Customer');
});
