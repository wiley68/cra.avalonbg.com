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
use App\Models\PatchCampaignTarget;
use App\Models\Product;
use App\Models\ProductDeployment;
use App\Models\ProductVersion;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     organization: Organization,
 *     owner: User,
 *     viewer: User,
 *     product: Product,
 *     versionOld: ProductVersion,
 *     versionTarget: ProductVersion
 * }
 */
function makePhase22FlowFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Phase 2.2 Flow Org',
        'slug' => 'phase-22-flow-org',
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

    $viewer = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);
    $viewerRole = Role::query()->where('slug', 'read_only')->firstOrFail();
    $organization->users()->attach($viewer->id, [
        'role_id' => $viewerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Flow Product',
        'slug' => 'flow-product',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => false,
        'scope_status' => ScopeStatus::InsufficientInformation,
        'classification_status' => ClassificationStatus::Unclassified,
    ]);

    $versionOld = ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '2.0.0',
        'state' => ProductVersionState::Released,
        'support_status' => SupportStatus::Supported,
    ]);

    $versionTarget = ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '2.1.0',
        'state' => ProductVersionState::Released,
        'support_status' => SupportStatus::Supported,
    ]);

    return compact(
        'organization',
        'owner',
        'viewer',
        'product',
        'versionOld',
        'versionTarget',
    );
}

/**
 * @return array{customer: Customer, deployment: ProductDeployment}
 */
function createFlowCustomerAndDeployments(
    TestCase $test,
    User $owner,
    Organization $organization,
    Product $product,
    ProductVersion $versionOld,
    ProductVersion $versionTarget,
): array {
    $test->actingAs($owner)
        ->post(route('customers.store'), [
            'name' => 'Flow Customer',
            'criticality' => CustomerCriticality::High->value,
            'is_active' => true,
            'primary_contact' => 'sec@flow.example',
        ])
        ->assertRedirect();

    $customer = Customer::query()->where('name', 'Flow Customer')->firstOrFail();

    $patchedCustomer = Customer::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Already Patched',
        'criticality' => CustomerCriticality::Low,
        'is_active' => true,
    ]);

    $test->actingAs($owner)
        ->post(route('products.deployments.store', $product), [
            'customer_id' => $customer->id,
            'product_version_id' => $versionOld->id,
            'environment' => DeploymentEnvironment::Production->value,
            'internet_exposure' => true,
            'custom_modifications' => false,
            'end_of_support_exception' => false,
        ])
        ->assertRedirect(route('products.deployments.index', $product));

    $deployment = ProductDeployment::query()
        ->where('customer_id', $customer->id)
        ->firstOrFail();

    ProductDeployment::query()->create([
        'organization_id' => $organization->id,
        'customer_id' => $patchedCustomer->id,
        'product_id' => $product->id,
        'product_version_id' => $versionTarget->id,
        'environment' => DeploymentEnvironment::Production,
    ]);

    $unknownCustomer = Customer::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Unknown Version Site',
        'criticality' => CustomerCriticality::Medium,
        'is_active' => true,
    ]);

    ProductDeployment::query()->create([
        'organization_id' => $organization->id,
        'customer_id' => $unknownCustomer->id,
        'product_id' => $product->id,
        'product_version_id' => null,
        'environment' => DeploymentEnvironment::Staging,
    ]);

    return [
        'customer' => $customer,
        'deployment' => $deployment,
    ];
}

/**
 * @return array{campaign: PatchCampaign, target: PatchCampaignTarget}
 */
function activateFlowCampaign(
    TestCase $test,
    User $owner,
    Product $product,
    ProductVersion $versionTarget,
    ProductDeployment $deployment,
): array {
    $test->actingAs($owner)
        ->post(route('products.campaigns.store', $product), [
            'title' => 'July security rollout',
            'target_version_id' => $versionTarget->id,
            'notes' => 'Patch critical CVE',
            'activate' => true,
        ])
        ->assertRedirect();

    $campaign = PatchCampaign::query()->where('title', 'July security rollout')->firstOrFail();

    expect($campaign->status)->toBe(PatchCampaignStatus::Active)
        ->and($campaign->targets()->count())->toBe(2)
        ->and(
            $campaign->targets()->where('deployment_id', $deployment->id)->exists(),
        )->toBeTrue();

    $test->actingAs($owner)
        ->get(route('products.campaigns.show', [$product, $campaign]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/campaigns/Show')
            ->where('campaign.id', $campaign->id)
            ->has('campaign.targets', 2)
            ->where('canManage', true));

    $target = PatchCampaignTarget::query()
        ->where('campaign_id', $campaign->id)
        ->where('deployment_id', $deployment->id)
        ->firstOrFail();

    return [
        'campaign' => $campaign,
        'target' => $target,
    ];
}

function notifyAndUpdateFlowTarget(
    TestCase $test,
    User $owner,
    Product $product,
    PatchCampaign $campaign,
    PatchCampaignTarget $target,
    ProductDeployment $deployment,
    ProductVersion $versionTarget,
): void {
    $test->actingAs($owner)
        ->put(route('products.campaigns.targets.update', [$product, $campaign, $target]), [
            'status' => PatchCampaignTargetStatus::Notified->value,
            'notification_note' => 'Emailed sec@flow.example',
        ])
        ->assertRedirect(route('products.campaigns.show', [$product, $campaign]));

    $target->refresh();

    expect($target->status)->toBe(PatchCampaignTargetStatus::Notified)
        ->and($target->notified_at)->not->toBeNull()
        ->and($target->notification_note)->toBe('Emailed sec@flow.example');

    $test->actingAs($owner)
        ->put(route('products.campaigns.targets.update', [$product, $campaign, $target]), [
            'status' => PatchCampaignTargetStatus::Updated->value,
            'notification_note' => 'Confirmed on call',
        ])
        ->assertRedirect(route('products.campaigns.show', [$product, $campaign]));

    $deployment->refresh();
    $target->refresh();

    expect($target->status)->toBe(PatchCampaignTargetStatus::Updated)
        ->and($target->confirmed_at)->not->toBeNull()
        ->and($deployment->product_version_id)->toBe($versionTarget->id)
        ->and($deployment->last_confirmed_at)->not->toBeNull();
}

function assertFlowAuditTrail(): void
{
    expect(AuditLog::query()->where('event_type', AuditEventType::CustomerCreated)->count())->toBe(1)
        ->and(AuditLog::query()->where('event_type', AuditEventType::DeploymentCreated)->count())->toBe(1)
        ->and(AuditLog::query()->where('event_type', AuditEventType::PatchCampaignCreated)->count())->toBe(1)
        ->and(AuditLog::query()->where('event_type', AuditEventType::PatchCampaignActivated)->count())->toBe(1)
        ->and(AuditLog::query()->where('event_type', AuditEventType::CampaignTargetUpdated)->count())->toBe(2);
}

/**
 * @return array{
 *     customer: Customer,
 *     deployment: ProductDeployment,
 *     campaign: PatchCampaign,
 *     target: PatchCampaignTarget,
 *     draftCampaign: PatchCampaign
 * }
 */
function seedViewerManagedRecords(
    Organization $organization,
    User $owner,
    Product $product,
    ProductVersion $versionOld,
    ProductVersion $versionTarget,
): array {
    $customer = Customer::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Visible Customer',
        'criticality' => CustomerCriticality::Medium,
        'is_active' => true,
    ]);

    $deployment = ProductDeployment::query()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'product_id' => $product->id,
        'product_version_id' => $versionOld->id,
        'environment' => DeploymentEnvironment::Production,
    ]);

    $campaign = PatchCampaign::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'target_version_id' => $versionTarget->id,
        'title' => 'Visible campaign',
        'status' => PatchCampaignStatus::Active,
        'started_at' => now(),
        'created_by' => $owner->id,
    ]);

    $target = PatchCampaignTarget::query()->create([
        'campaign_id' => $campaign->id,
        'deployment_id' => $deployment->id,
        'status' => PatchCampaignTargetStatus::Pending,
    ]);

    $draftCampaign = PatchCampaign::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'target_version_id' => $versionTarget->id,
        'title' => 'Draft campaign',
        'status' => PatchCampaignStatus::Draft,
        'created_by' => $owner->id,
    ]);

    return compact('customer', 'deployment', 'campaign', 'target', 'draftCampaign');
}

function assertViewerCanBrowse(
    TestCase $test,
    User $viewer,
    Product $product,
    PatchCampaign $campaign,
): void {
    $test->actingAs($viewer)
        ->get(route('customers.index'))
        ->assertOk()
        ->assertInertia(fn($page) => $page->where('canManage', false));

    $test->actingAs($viewer)
        ->get(route('products.deployments.index', $product))
        ->assertOk()
        ->assertInertia(fn($page) => $page->where('canManage', false));

    $test->actingAs($viewer)
        ->get(route('products.campaigns.index', $product))
        ->assertOk()
        ->assertInertia(fn($page) => $page->where('canManage', false));

    $test->actingAs($viewer)
        ->get(route('products.campaigns.show', [$product, $campaign]))
        ->assertOk()
        ->assertInertia(fn($page) => $page->where('canManage', false));

    $test->actingAs($viewer)
        ->getJson(route('internal.customers.index'))
        ->assertOk()
        ->assertJsonPath('total', 1);

    $test->actingAs($viewer)
        ->getJson(route('internal.products.deployments.index', $product))
        ->assertOk()
        ->assertJsonPath('total', 1);

    $test->actingAs($viewer)
        ->getJson(route('internal.products.campaigns.index', $product))
        ->assertOk()
        ->assertJsonPath('total', 2);
}

function assertViewerCannotManage(
    TestCase $test,
    User $viewer,
    Product $product,
    ProductVersion $versionTarget,
    Customer $customer,
    ProductDeployment $deployment,
    PatchCampaign $campaign,
    PatchCampaign $draftCampaign,
    PatchCampaignTarget $target,
): void {
    $test->actingAs($viewer)
        ->post(route('customers.store'), [
            'name' => 'Forbidden Customer',
            'criticality' => CustomerCriticality::Low->value,
            'is_active' => true,
        ])
        ->assertForbidden();

    $test->actingAs($viewer)
        ->put(route('customers.update', $customer), [
            'name' => 'Hacked Name',
            'criticality' => CustomerCriticality::Low->value,
            'is_active' => true,
        ])
        ->assertForbidden();

    $test->actingAs($viewer)
        ->delete(route('customers.destroy', $customer))
        ->assertForbidden();

    $test->actingAs($viewer)
        ->post(route('products.deployments.store', $product), [
            'customer_id' => $customer->id,
            'environment' => DeploymentEnvironment::Staging->value,
        ])
        ->assertForbidden();

    $test->actingAs($viewer)
        ->put(route('products.deployments.update', [$product, $deployment]), [
            'customer_id' => $customer->id,
            'environment' => DeploymentEnvironment::Production->value,
            'internet_exposure' => true,
        ])
        ->assertForbidden();

    $test->actingAs($viewer)
        ->delete(route('products.deployments.destroy', [$product, $deployment]))
        ->assertForbidden();

    $test->actingAs($viewer)
        ->post(route('products.campaigns.store', $product), [
            'title' => 'Forbidden campaign',
            'target_version_id' => $versionTarget->id,
        ])
        ->assertForbidden();

    $test->actingAs($viewer)
        ->put(route('products.campaigns.update', [$product, $draftCampaign]), [
            'title' => 'Hacked draft',
            'target_version_id' => $versionTarget->id,
        ])
        ->assertForbidden();

    $test->actingAs($viewer)
        ->post(route('products.campaigns.activate', [$product, $draftCampaign]))
        ->assertForbidden();

    $test->actingAs($viewer)
        ->delete(route('products.campaigns.destroy', [$product, $draftCampaign]))
        ->assertForbidden();

    $test->actingAs($viewer)
        ->put(route('products.campaigns.targets.update', [$product, $campaign, $target]), [
            'status' => PatchCampaignTargetStatus::Notified->value,
            'notification_note' => 'Should fail',
        ])
        ->assertForbidden();

    expect(Customer::query()->count())->toBe(1)
        ->and(ProductDeployment::query()->count())->toBe(1)
        ->and(PatchCampaign::query()->count())->toBe(2)
        ->and($target->fresh()->status)->toBe(PatchCampaignTargetStatus::Pending);
}

test('owner happy path: customer deployment campaign notify update with audit trail', function () {
    /** @var TestCase $this */
    $fixture = makePhase22FlowFixture();

    $created = createFlowCustomerAndDeployments(
        $this,
        $fixture['owner'],
        $fixture['organization'],
        $fixture['product'],
        $fixture['versionOld'],
        $fixture['versionTarget'],
    );

    $activated = activateFlowCampaign(
        $this,
        $fixture['owner'],
        $fixture['product'],
        $fixture['versionTarget'],
        $created['deployment'],
    );

    notifyAndUpdateFlowTarget(
        $this,
        $fixture['owner'],
        $fixture['product'],
        $activated['campaign'],
        $activated['target'],
        $created['deployment'],
        $fixture['versionTarget'],
    );

    assertFlowAuditTrail();
});

test('viewer can browse phase 2.2 lists but cannot manage customers deployments or campaigns', function () {
    /** @var TestCase $this */
    $fixture = makePhase22FlowFixture();
    $records = seedViewerManagedRecords(
        $fixture['organization'],
        $fixture['owner'],
        $fixture['product'],
        $fixture['versionOld'],
        $fixture['versionTarget'],
    );

    assertViewerCanBrowse(
        $this,
        $fixture['viewer'],
        $fixture['product'],
        $records['campaign'],
    );

    assertViewerCannotManage(
        $this,
        $fixture['viewer'],
        $fixture['product'],
        $fixture['versionTarget'],
        $records['customer'],
        $records['deployment'],
        $records['campaign'],
        $records['draftCampaign'],
        $records['target'],
    );
});
