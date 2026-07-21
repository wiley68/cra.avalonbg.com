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

uses(RefreshDatabase::class);

/**
 * @return array{
 *     organization: Organization,
 *     owner: User,
 *     product: Product,
 *     customer: Customer,
 *     versionSupported: ProductVersion,
 *     versionUnsupported: ProductVersion,
 *     versionExpiredSecurity: ProductVersion
 * }
 */
function makeUnsupportedDeploymentsFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Unsupported Deployments Org',
        'slug' => 'unsupported-deployments-org',
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
        'name' => 'Unsupported Deployments Product',
        'slug' => 'unsupported-deployments-product',
        'manufacturer' => 'Acme Soft',
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

    $customer = Customer::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Contoso Ltd',
        'criticality' => CustomerCriticality::Medium,
        'is_active' => true,
    ]);

    $exceptionCustomer = Customer::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Fabrikam Inc',
        'criticality' => CustomerCriticality::Low,
        'is_active' => true,
    ]);

    $versionSupported = ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '2.0.0',
        'state' => ProductVersionState::Released,
        'support_status' => SupportStatus::Supported,
    ]);

    $versionUnsupported = ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '1.0.0',
        'state' => ProductVersionState::EndOfSupport,
        'support_status' => SupportStatus::Unsupported,
    ]);

    $versionExpiredSecurity = ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '1.5.0',
        'state' => ProductVersionState::Released,
        'support_status' => SupportStatus::SecurityOnly,
        'security_support_deadline' => now()->subDays(10)->toDateString(),
    ]);

    return compact(
        'organization',
        'owner',
        'product',
        'customer',
        'exceptionCustomer',
        'versionSupported',
        'versionUnsupported',
        'versionExpiredSecurity',
    );
}

test('owner can open unsupported installations cross-check page', function () {
    $fixture = makeUnsupportedDeploymentsFixture();

    $this->actingAs($fixture['owner'])
        ->get(route('products.deployments.unsupported', $fixture['product']))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/deployments/Unsupported')
            ->has('product')
            ->where('canManage', true));
});

test('internal api lists only deployments on unsupported or expired versions', function () {
    $fixture = makeUnsupportedDeploymentsFixture();

    ProductDeployment::query()->create([
        'organization_id' => $fixture['organization']->id,
        'customer_id' => $fixture['customer']->id,
        'product_id' => $fixture['product']->id,
        'product_version_id' => $fixture['versionSupported']->id,
        'environment' => DeploymentEnvironment::Production,
        'internet_exposure' => false,
        'custom_modifications' => false,
        'end_of_support_exception' => false,
    ]);

    $unsupportedDeployment = ProductDeployment::query()->create([
        'organization_id' => $fixture['organization']->id,
        'customer_id' => $fixture['customer']->id,
        'product_id' => $fixture['product']->id,
        'product_version_id' => $fixture['versionUnsupported']->id,
        'environment' => DeploymentEnvironment::Staging,
        'internet_exposure' => true,
        'custom_modifications' => false,
        'end_of_support_exception' => false,
    ]);

    $expiredSecurityDeployment = ProductDeployment::query()->create([
        'organization_id' => $fixture['organization']->id,
        'customer_id' => $fixture['customer']->id,
        'product_id' => $fixture['product']->id,
        'product_version_id' => $fixture['versionExpiredSecurity']->id,
        'environment' => DeploymentEnvironment::Other,
        'internet_exposure' => false,
        'custom_modifications' => false,
        'end_of_support_exception' => false,
    ]);

    ProductDeployment::query()->create([
        'organization_id' => $fixture['organization']->id,
        'customer_id' => $fixture['exceptionCustomer']->id,
        'product_id' => $fixture['product']->id,
        'product_version_id' => $fixture['versionUnsupported']->id,
        'environment' => DeploymentEnvironment::Production,
        'internet_exposure' => false,
        'custom_modifications' => false,
        'end_of_support_exception' => true,
    ]);

    $response = $this->actingAs($fixture['owner'])
        ->getJson(route('internal.products.deployments.index', [
            'product' => $fixture['product'],
            'unsupported_only' => '1',
            'per_page' => 50,
        ]));

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('total', 2);

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($unsupportedDeployment->id, $expiredSecurityDeployment->id)
        ->and($response->json('data.0.support_status'))->toBeString();
});

test('deployments index exposes unsupported count', function () {
    $fixture = makeUnsupportedDeploymentsFixture();

    ProductDeployment::query()->create([
        'organization_id' => $fixture['organization']->id,
        'customer_id' => $fixture['customer']->id,
        'product_id' => $fixture['product']->id,
        'product_version_id' => $fixture['versionUnsupported']->id,
        'environment' => DeploymentEnvironment::Production,
        'internet_exposure' => false,
        'custom_modifications' => false,
        'end_of_support_exception' => false,
    ]);

    $this->actingAs($fixture['owner'])
        ->get(route('products.deployments.index', $fixture['product']))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/deployments/Index')
            ->where('unsupportedCount', 1));
});

test('readiness report warns when unsupported installations remain', function () {
    $fixture = makeUnsupportedDeploymentsFixture();

    ProductDeployment::query()->create([
        'organization_id' => $fixture['organization']->id,
        'customer_id' => $fixture['customer']->id,
        'product_id' => $fixture['product']->id,
        'product_version_id' => $fixture['versionUnsupported']->id,
        'environment' => DeploymentEnvironment::Production,
        'internet_exposure' => false,
        'custom_modifications' => false,
        'end_of_support_exception' => false,
    ]);

    $this->actingAs($fixture['owner'])
        ->get(route('products.readiness.show', $fixture['product']))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->has('report.gaps')
            ->where(
                'report.gaps',
                fn($gaps) => collect($gaps)->contains(
                    fn($gap) => $gap['message_key'] === 'products.readiness.gaps.unsupported_deployments'
                    && $gap['link'] === 'deployments-unsupported',
                ),
            ));
});

test('end of support exception excludes deployment from unsupported cross-check', function () {
    $fixture = makeUnsupportedDeploymentsFixture();

    ProductDeployment::query()->create([
        'organization_id' => $fixture['organization']->id,
        'customer_id' => $fixture['customer']->id,
        'product_id' => $fixture['product']->id,
        'product_version_id' => $fixture['versionUnsupported']->id,
        'environment' => DeploymentEnvironment::Production,
        'internet_exposure' => false,
        'custom_modifications' => false,
        'end_of_support_exception' => true,
    ]);

    $this->actingAs($fixture['owner'])
        ->get(route('products.deployments.index', $fixture['product']))
        ->assertOk()
        ->assertInertia(fn($page) => $page->where('unsupportedCount', 0));
});
