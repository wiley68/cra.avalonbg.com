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

uses(RefreshDatabase::class);

/**
 * @return array{
 *     organization: Organization,
 *     owner: User,
 *     product: Product,
 *     customer: Customer,
 *     versionOld: ProductVersion,
 *     versionTarget: ProductVersion,
 *     campaign: PatchCampaign,
 *     deployment: ProductDeployment,
 *     target: PatchCampaignTarget
 * }
 */
function makeActiveCampaignWithTarget(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Target Status Org',
        'slug' => 'target-status-org',
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
        'name' => 'Target Product',
        'slug' => 'target-product',
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
        'title' => 'Active rollout',
        'status' => PatchCampaignStatus::Active,
        'started_at' => now(),
        'created_by' => $owner->id,
    ]);

    $target = PatchCampaignTarget::query()->create([
        'campaign_id' => $campaign->id,
        'deployment_id' => $deployment->id,
        'status' => PatchCampaignTargetStatus::Pending,
    ]);

    return compact(
        'organization',
        'owner',
        'product',
        'customer',
        'versionOld',
        'versionTarget',
        'campaign',
        'deployment',
        'target',
    );
}

test('owner can mark target notified with note and audit', function () {
    [
        'owner' => $owner,
        'product' => $product,
        'campaign' => $campaign,
        'target' => $target,
    ] = makeActiveCampaignWithTarget();

    $this->actingAs($owner)
        ->put(route('products.campaigns.targets.update', [$product, $campaign, $target]), [
            'status' => PatchCampaignTargetStatus::Notified->value,
            'notification_note' => 'Emailed security@acme.test',
        ])
        ->assertRedirect(route('products.campaigns.show', [$product, $campaign]));

    $target->refresh();

    expect($target->status)->toBe(PatchCampaignTargetStatus::Notified)
        ->and($target->notified_at)->not->toBeNull()
        ->and($target->notification_note)->toBe('Emailed security@acme.test')
        ->and(AuditLog::query()->where('event_type', AuditEventType::CampaignTargetUpdated)->count())->toBe(1);
});

test('marking target updated syncs deployment version', function () {
    [
        'owner' => $owner,
        'product' => $product,
        'campaign' => $campaign,
        'deployment' => $deployment,
        'versionOld' => $versionOld,
        'versionTarget' => $versionTarget,
        'target' => $target,
    ] = makeActiveCampaignWithTarget();

    expect($deployment->product_version_id)->toBe($versionOld->id);

    $this->actingAs($owner)
        ->put(route('products.campaigns.targets.update', [$product, $campaign, $target]), [
            'status' => PatchCampaignTargetStatus::Updated->value,
            'notification_note' => 'Confirmed on call',
        ])
        ->assertRedirect(route('products.campaigns.show', [$product, $campaign]));

    $target->refresh();
    $deployment->refresh();

    expect($target->status)->toBe(PatchCampaignTargetStatus::Updated)
        ->and($target->confirmed_at)->not->toBeNull()
        ->and($deployment->product_version_id)->toBe($versionTarget->id)
        ->and($deployment->last_confirmed_at)->not->toBeNull();
});

test('marking target excepted does not sync deployment version', function () {
    [
        'owner' => $owner,
        'product' => $product,
        'campaign' => $campaign,
        'deployment' => $deployment,
        'versionOld' => $versionOld,
        'target' => $target,
    ] = makeActiveCampaignWithTarget();

    $this->actingAs($owner)
        ->put(route('products.campaigns.targets.update', [$product, $campaign, $target]), [
            'status' => PatchCampaignTargetStatus::Excepted->value,
            'notification_note' => 'Contract exception',
        ])
        ->assertRedirect(route('products.campaigns.show', [$product, $campaign]));

    $target->refresh();
    $deployment->refresh();

    expect($target->status)->toBe(PatchCampaignTargetStatus::Excepted)
        ->and($target->confirmed_at)->not->toBeNull()
        ->and($deployment->product_version_id)->toBe($versionOld->id)
        ->and($deployment->last_confirmed_at)->toBeNull();
});

test('target status cannot be updated on draft campaign', function () {
    [
        'owner' => $owner,
        'organization' => $organization,
        'product' => $product,
        'deployment' => $deployment,
        'versionTarget' => $versionTarget,
    ] = makeActiveCampaignWithTarget();

    $draft = PatchCampaign::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'target_version_id' => $versionTarget->id,
        'title' => 'Still draft',
        'status' => PatchCampaignStatus::Draft,
        'created_by' => $owner->id,
    ]);

    $target = PatchCampaignTarget::query()->create([
        'campaign_id' => $draft->id,
        'deployment_id' => $deployment->id,
        'status' => PatchCampaignTargetStatus::Pending,
    ]);

    $this->actingAs($owner)
        ->from(route('products.campaigns.show', [$product, $draft]))
        ->put(route('products.campaigns.targets.update', [$product, $draft, $target]), [
            'status' => PatchCampaignTargetStatus::Notified->value,
        ])
        ->assertRedirect(route('products.campaigns.show', [$product, $draft]))
        ->assertSessionHasErrors('status');
});

test('viewer cannot update target status', function () {
    [
        'organization' => $organization,
        'product' => $product,
        'campaign' => $campaign,
        'target' => $target,
    ] = makeActiveCampaignWithTarget();

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

    $this->actingAs($viewer)
        ->put(route('products.campaigns.targets.update', [$product, $campaign, $target]), [
            'status' => PatchCampaignTargetStatus::Notified->value,
        ])
        ->assertForbidden();
});
