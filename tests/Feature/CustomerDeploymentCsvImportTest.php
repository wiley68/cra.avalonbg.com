<?php

use App\Enums\ClassificationStatus;
use App\Enums\CustomerCriticality;
use App\Enums\DeploymentEnvironment;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ProductVersionState;
use App\Enums\ScopeStatus;
use App\Enums\SupportStatus;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductDeployment;
use App\Models\ProductVersion;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User, product: Product, version: ProductVersion}
 */
function makeCsvImportFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'CSV Import Org',
        'slug' => 'csv-import-org',
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
        'name' => 'CSV Product',
        'slug' => 'csv-product',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => false,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    $version = ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '2.0.0',
        'state' => ProductVersionState::Released,
        'support_status' => SupportStatus::Supported,
    ]);

    return [
        'organization' => $organization,
        'owner' => $owner,
        'product' => $product,
        'version' => $version,
    ];
}

function makeCustomersCsv(string $contents): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'customers-import-');
    file_put_contents($path, $contents);

    return new UploadedFile($path, 'customers.csv', 'text/csv', null, true);
}

function makeDeploymentsCsv(string $contents): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'deployments-import-');
    file_put_contents($path, $contents);

    return new UploadedFile($path, 'deployments.csv', 'text/csv', null, true);
}

test('owner can import customers from csv', function () {
    $fixture = makeCsvImportFixture();

    $csv = <<<'CSV'
name,external_ref,primary_contact,criticality,notes,is_active
Acme Corp,CRM-1,ops@acme.example,high,Tier-1,1
Beta Ltd,,contact@beta.example,medium,,1
CSV;

    $this->actingAs($fixture['owner'])
        ->post(route('customers.import.store'), [
            'file' => makeCustomersCsv($csv),
        ])
        ->assertRedirect(route('customers.index'));

    expect(Customer::query()->where('organization_id', $fixture['organization']->id)->count())->toBe(2)
        ->and(Customer::query()->where('external_ref', 'CRM-1')->value('name'))->toBe('Acme Corp');
});

test('customer csv import upserts existing customer by external ref', function () {
    $fixture = makeCsvImportFixture();

    Customer::query()->create([
        'organization_id' => $fixture['organization']->id,
        'name' => 'Old Name',
        'external_ref' => 'CRM-1',
        'criticality' => CustomerCriticality::Low,
        'is_active' => true,
    ]);

    $csv = <<<'CSV'
name,external_ref,primary_contact,criticality,is_active
Acme Corp,CRM-1,ops@acme.example,high,1
CSV;

    $this->actingAs($fixture['owner'])
        ->post(route('customers.import.store'), [
            'file' => makeCustomersCsv($csv),
        ])
        ->assertRedirect(route('customers.index'));

    $customer = Customer::query()->where('external_ref', 'CRM-1')->firstOrFail();

    expect(Customer::query()->count())->toBe(1)
        ->and($customer->name)->toBe('Acme Corp')
        ->and($customer->criticality)->toBe(CustomerCriticality::High);
});

test('viewer cannot import customers csv', function () {
    $fixture = makeCsvImportFixture();

    $viewer = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $role = Role::query()->where('slug', 'read_only')->firstOrFail();
    $fixture['organization']->users()->attach($viewer->id, [
        'role_id' => $role->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $csv = "name,criticality\nAcme Corp,high\n";

    $this->actingAs($viewer)
        ->post(route('customers.import.store'), [
            'file' => makeCustomersCsv($csv),
        ])
        ->assertForbidden();
});

test('owner can import deployments from csv', function () {
    $fixture = makeCsvImportFixture();

    $customer = Customer::query()->create([
        'organization_id' => $fixture['organization']->id,
        'name' => 'Acme Corp',
        'external_ref' => 'CRM-1',
        'criticality' => CustomerCriticality::High,
        'is_active' => true,
    ]);

    $csv = <<<CSV
customer_name,customer_external_ref,environment,version_number,installation_date,internet_exposure,notes
Acme Corp,CRM-1,production,2.0.0,2026-01-10,1,Primary site
CSV;

    $this->actingAs($fixture['owner'])
        ->post(route('products.deployments.import.store', $fixture['product']), [
            'file' => makeDeploymentsCsv($csv),
        ])
        ->assertRedirect(route('products.deployments.index', $fixture['product']));

    $deployment = ProductDeployment::query()
        ->where('product_id', $fixture['product']->id)
        ->where('customer_id', $customer->id)
        ->firstOrFail();

    expect($deployment->environment)->toBe(DeploymentEnvironment::Production)
        ->and($deployment->product_version_id)->toBe($fixture['version']->id)
        ->and($deployment->internet_exposure)->toBeTrue();
});

test('deployment csv import skips unchanged existing row', function () {
    $fixture = makeCsvImportFixture();

    $customer = Customer::query()->create([
        'organization_id' => $fixture['organization']->id,
        'name' => 'Acme Corp',
        'criticality' => CustomerCriticality::High,
        'is_active' => true,
    ]);

    ProductDeployment::query()->create([
        'organization_id' => $fixture['organization']->id,
        'customer_id' => $customer->id,
        'product_id' => $fixture['product']->id,
        'product_version_id' => $fixture['version']->id,
        'environment' => DeploymentEnvironment::Production,
        'internet_exposure' => true,
        'custom_modifications' => false,
        'end_of_support_exception' => false,
    ]);

    $csv = <<<CSV
customer_name,environment,version_number,internet_exposure
Acme Corp,production,2.0.0,1
CSV;

    $this->actingAs($fixture['owner'])
        ->post(route('products.deployments.import.store', $fixture['product']), [
            'file' => makeDeploymentsCsv($csv),
        ])
        ->assertRedirect(route('products.deployments.index', $fixture['product']));

    expect(ProductDeployment::query()->count())->toBe(1);
});

test('customer import template downloads csv', function () {
    $fixture = makeCsvImportFixture();

    $this->actingAs($fixture['owner'])
        ->get(route('customers.import.template'))
        ->assertOk()
        ->assertHeader('content-disposition');
});
