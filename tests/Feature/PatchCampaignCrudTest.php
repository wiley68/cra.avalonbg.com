<?php

use App\Enums\AuditEventType;
use App\Enums\ClassificationStatus;
use App\Enums\CustomerCriticality;
use App\Enums\DeploymentEnvironment;
use App\Enums\LicensingModel;
use App\Enums\PatchCampaignStatus;
use App\Enums\PatchCampaignTargetStatus;
use App\Enums\ProductType;
use App\Enums\ProductVersionState;
use App\Enums\ScopeStatus;
use App\Enums\SupportStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\PatchCampaign;
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
 *     versionOld: ProductVersion,
 *     versionTarget: ProductVersion
 * }
 */
function makeCampaignFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Campaign CRUD Org',
        'slug' => 'campaign-crud-org',
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
        'name' => 'Campaign Product',
        'slug' => 'campaign-product',
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

    $versionOld = ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '1.0.0',
        'state' => ProductVersionState::Released,
        'support_status' => SupportStatus::Supported,
    ]);

    $versionTarget = ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '1.1.0',
        'state' => ProductVersionState::Released,
        'support_status' => SupportStatus::Supported,
    ]);

    return compact('organization', 'owner', 'product', 'customer', 'versionOld', 'versionTarget');
}

test('owner can create draft campaign with audit', function () {
    ['owner' => $owner, 'product' => $product, 'versionTarget' => $versionTarget] = makeCampaignFixture();

    $this->actingAs($owner)
        ->post(route('products.campaigns.store', $product), [
            'title' => 'Security patch July',
            'target_version_id' => $versionTarget->id,
            'notes' => 'Roll out fix',
        ])
        ->assertRedirect();

    $campaign = PatchCampaign::query()->first();

    expect($campaign)->not->toBeNull()
        ->and($campaign->title)->toBe('Security patch July')
        ->and($campaign->status)->toBe(PatchCampaignStatus::Draft)
        ->and($campaign->target_version_id)->toBe($versionTarget->id)
        ->and($campaign->targets()->count())->toBe(0)
        ->and(AuditLog::query()->where('event_type', AuditEventType::PatchCampaignCreated)->count())->toBe(1);
});

test('activating campaign seeds matching deployments only', function () {
    [
        'owner' => $owner,
        'organization' => $organization,
        'product' => $product,
        'customer' => $customer,
        'versionOld' => $versionOld,
        'versionTarget' => $versionTarget,
    ] = makeCampaignFixture();

    $otherCustomer = Customer::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Already Patched',
        'criticality' => CustomerCriticality::Low,
        'is_active' => true,
    ]);

    $nullVersionCustomer = Customer::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Unknown Version',
        'criticality' => CustomerCriticality::Medium,
        'is_active' => true,
    ]);

    // Should be seeded (old version)
    ProductDeployment::query()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'product_id' => $product->id,
        'product_version_id' => $versionOld->id,
        'environment' => DeploymentEnvironment::Production,
    ]);

    // Should be seeded (null version)
    ProductDeployment::query()->create([
        'organization_id' => $organization->id,
        'customer_id' => $nullVersionCustomer->id,
        'product_id' => $product->id,
        'product_version_id' => null,
        'environment' => DeploymentEnvironment::Staging,
    ]);

    // Should NOT be seeded (already on target)
    ProductDeployment::query()->create([
        'organization_id' => $organization->id,
        'customer_id' => $otherCustomer->id,
        'product_id' => $product->id,
        'product_version_id' => $versionTarget->id,
        'environment' => DeploymentEnvironment::Production,
    ]);

    $campaign = PatchCampaign::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'target_version_id' => $versionTarget->id,
        'title' => 'Activate me',
        'status' => PatchCampaignStatus::Draft,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->post(route('products.campaigns.activate', [$product, $campaign]))
        ->assertRedirect(route('products.campaigns.show', [$product, $campaign]));

    $campaign->refresh();

    expect($campaign->status)->toBe(PatchCampaignStatus::Active)
        ->and($campaign->started_at)->not->toBeNull()
        ->and($campaign->targets()->count())->toBe(2)
        ->and(
            $campaign->targets->every(
                fn($target) => $target->status === PatchCampaignTargetStatus::Pending,
            ),
        )->toBeTrue()
        ->and(AuditLog::query()->where('event_type', AuditEventType::PatchCampaignActivated)->count())->toBe(1);
});

test('create with activate flag seeds immediately', function () {
    [
        'owner' => $owner,
        'organization' => $organization,
        'product' => $product,
        'customer' => $customer,
        'versionOld' => $versionOld,
        'versionTarget' => $versionTarget,
    ] = makeCampaignFixture();

    ProductDeployment::query()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'product_id' => $product->id,
        'product_version_id' => $versionOld->id,
        'environment' => DeploymentEnvironment::Production,
    ]);

    $this->actingAs($owner)
        ->post(route('products.campaigns.store', $product), [
            'title' => 'Instant activate',
            'target_version_id' => $versionTarget->id,
            'activate' => true,
        ])
        ->assertRedirect();

    $campaign = PatchCampaign::query()->first();

    expect($campaign->status)->toBe(PatchCampaignStatus::Active)
        ->and($campaign->targets()->count())->toBe(1)
        ->and(AuditLog::query()->where('event_type', AuditEventType::PatchCampaignCreated)->count())->toBe(1)
        ->and(AuditLog::query()->where('event_type', AuditEventType::PatchCampaignActivated)->count())->toBe(1);
});

test('owner can update and delete draft only', function () {
    [
        'owner' => $owner,
        'organization' => $organization,
        'product' => $product,
        'versionTarget' => $versionTarget,
        'versionOld' => $versionOld,
    ] = makeCampaignFixture();

    $campaign = PatchCampaign::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'target_version_id' => $versionOld->id,
        'title' => 'Draft title',
        'status' => PatchCampaignStatus::Draft,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->put(route('products.campaigns.update', [$product, $campaign]), [
            'title' => 'Updated draft',
            'target_version_id' => $versionTarget->id,
        ])
        ->assertRedirect(route('products.campaigns.show', [$product, $campaign]));

    expect($campaign->fresh()->title)->toBe('Updated draft')
        ->and(AuditLog::query()->where('event_type', AuditEventType::PatchCampaignUpdated)->count())->toBe(1);

    $this->actingAs($owner)
        ->delete(route('products.campaigns.destroy', [$product, $campaign]))
        ->assertRedirect(route('products.campaigns.index', $product));

    expect(PatchCampaign::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('event_type', AuditEventType::PatchCampaignDeleted)->count())->toBe(1);

    $active = PatchCampaign::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'target_version_id' => $versionTarget->id,
        'title' => 'Active campaign',
        'status' => PatchCampaignStatus::Active,
        'started_at' => now(),
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->from(route('products.campaigns.show', [$product, $active]))
        ->delete(route('products.campaigns.destroy', [$product, $active]))
        ->assertRedirect(route('products.campaigns.show', [$product, $active]))
        ->assertSessionHasErrors('status');

    expect(PatchCampaign::query()->whereKey($active->id)->exists())->toBeTrue();
});

test('viewer can list and show campaigns but cannot create or activate', function () {
    [
        'organization' => $organization,
        'product' => $product,
        'versionTarget' => $versionTarget,
        'owner' => $owner,
    ] = makeCampaignFixture();

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

    $campaign = PatchCampaign::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'target_version_id' => $versionTarget->id,
        'title' => 'Visible campaign',
        'status' => PatchCampaignStatus::Draft,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($viewer)
        ->get(route('products.campaigns.index', $product))
        ->assertOk()
        ->assertInertia(fn($page) => $page->where('canManage', false));

    $this->actingAs($viewer)
        ->get(route('products.campaigns.show', [$product, $campaign]))
        ->assertOk();

    $this->actingAs($viewer)
        ->getJson(route('internal.products.campaigns.index', $product))
        ->assertOk()
        ->assertJsonPath('total', 1);

    $this->actingAs($viewer)
        ->post(route('products.campaigns.store', $product), [
            'title' => 'Forbidden',
            'target_version_id' => $versionTarget->id,
        ])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->post(route('products.campaigns.activate', [$product, $campaign]))
        ->assertForbidden();
});
