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
 *     versionOld: ProductVersion,
 *     versionTarget: ProductVersion
 * }
 */
function makeCampaignCompletionFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Campaign Complete Org',
        'slug' => 'campaign-complete-org',
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
        'name' => 'Complete Product',
        'slug' => 'complete-product',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => false,
        'scope_status' => ScopeStatus::InsufficientInformation,
        'classification_status' => ClassificationStatus::Unclassified,
    ]);

    $versionOld = ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '3.0.0',
        'state' => ProductVersionState::Released,
        'support_status' => SupportStatus::Supported,
    ]);

    $versionTarget = ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '3.1.0',
        'state' => ProductVersionState::Released,
        'support_status' => SupportStatus::Supported,
    ]);

    return compact('organization', 'owner', 'product', 'versionOld', 'versionTarget');
}

/**
 * @return array{campaign: PatchCampaign, first: PatchCampaignTarget, second: PatchCampaignTarget}
 */
function seedActiveCampaignWithTwoTargets(
    Organization $organization,
    User $owner,
    Product $product,
    ProductVersion $versionOld,
    ProductVersion $versionTarget,
): array {
    $firstCustomer = Customer::query()->create([
        'organization_id' => $organization->id,
        'name' => 'First Customer',
        'criticality' => CustomerCriticality::High,
        'is_active' => true,
    ]);
    $secondCustomer = Customer::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Second Customer',
        'criticality' => CustomerCriticality::Medium,
        'is_active' => true,
    ]);

    $firstDeployment = ProductDeployment::query()->create([
        'organization_id' => $organization->id,
        'customer_id' => $firstCustomer->id,
        'product_id' => $product->id,
        'product_version_id' => $versionOld->id,
        'environment' => DeploymentEnvironment::Production,
    ]);
    $secondDeployment = ProductDeployment::query()->create([
        'organization_id' => $organization->id,
        'customer_id' => $secondCustomer->id,
        'product_id' => $product->id,
        'product_version_id' => $versionOld->id,
        'environment' => DeploymentEnvironment::Staging,
    ]);

    $campaign = PatchCampaign::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'target_version_id' => $versionTarget->id,
        'title' => 'Complete me',
        'status' => PatchCampaignStatus::Active,
        'started_at' => now(),
        'created_by' => $owner->id,
    ]);

    $first = PatchCampaignTarget::query()->create([
        'campaign_id' => $campaign->id,
        'deployment_id' => $firstDeployment->id,
        'status' => PatchCampaignTargetStatus::Pending,
    ]);
    $second = PatchCampaignTarget::query()->create([
        'campaign_id' => $campaign->id,
        'deployment_id' => $secondDeployment->id,
        'status' => PatchCampaignTargetStatus::Pending,
    ]);

    return compact('campaign', 'first', 'second');
}

test('campaign completes when last target is updated', function () {
    $fixture = makeCampaignCompletionFixture();
    $seeded = seedActiveCampaignWithTwoTargets(
        $fixture['organization'],
        $fixture['owner'],
        $fixture['product'],
        $fixture['versionOld'],
        $fixture['versionTarget'],
    );

    $this->actingAs($fixture['owner'])
        ->put(route('products.campaigns.targets.update', [
            $fixture['product'],
            $seeded['campaign'],
            $seeded['first'],
        ]), [
            'status' => PatchCampaignTargetStatus::Updated->value,
        ])
        ->assertRedirect();

    expect($seeded['campaign']->fresh()->status)->toBe(PatchCampaignStatus::Active);

    $this->actingAs($fixture['owner'])
        ->put(route('products.campaigns.targets.update', [
            $fixture['product'],
            $seeded['campaign'],
            $seeded['second'],
        ]), [
            'status' => PatchCampaignTargetStatus::Updated->value,
        ])
        ->assertRedirect();

    $campaign = $seeded['campaign']->fresh();

    expect($campaign->status)->toBe(PatchCampaignStatus::Completed)
        ->and($campaign->completed_at)->not->toBeNull()
        ->and(AuditLog::query()->where('event_type', AuditEventType::PatchCampaignCompleted)->count())->toBe(1);
});

test('campaign completes when targets are mix of updated and excepted', function () {
    $fixture = makeCampaignCompletionFixture();
    $seeded = seedActiveCampaignWithTwoTargets(
        $fixture['organization'],
        $fixture['owner'],
        $fixture['product'],
        $fixture['versionOld'],
        $fixture['versionTarget'],
    );

    $this->actingAs($fixture['owner'])
        ->put(route('products.campaigns.targets.update', [
            $fixture['product'],
            $seeded['campaign'],
            $seeded['first'],
        ]), [
            'status' => PatchCampaignTargetStatus::Updated->value,
        ])
        ->assertRedirect();

    $this->actingAs($fixture['owner'])
        ->put(route('products.campaigns.targets.update', [
            $fixture['product'],
            $seeded['campaign'],
            $seeded['second'],
        ]), [
            'status' => PatchCampaignTargetStatus::Excepted->value,
            'notification_note' => 'Contract exception',
        ])
        ->assertRedirect();

    expect($seeded['campaign']->fresh()->status)->toBe(PatchCampaignStatus::Completed)
        ->and($fixture['versionOld']->id)->toBe(
            ProductDeployment::query()
                ->whereKey($seeded['second']->deployment_id)
                ->value('product_version_id'),
        );
});

test('campaign stays active while any target is still open', function () {
    $fixture = makeCampaignCompletionFixture();
    $seeded = seedActiveCampaignWithTwoTargets(
        $fixture['organization'],
        $fixture['owner'],
        $fixture['product'],
        $fixture['versionOld'],
        $fixture['versionTarget'],
    );

    $this->actingAs($fixture['owner'])
        ->put(route('products.campaigns.targets.update', [
            $fixture['product'],
            $seeded['campaign'],
            $seeded['first'],
        ]), [
            'status' => PatchCampaignTargetStatus::Notified->value,
        ])
        ->assertRedirect();

    $this->actingAs($fixture['owner'])
        ->put(route('products.campaigns.targets.update', [
            $fixture['product'],
            $seeded['campaign'],
            $seeded['second'],
        ]), [
            'status' => PatchCampaignTargetStatus::Updated->value,
        ])
        ->assertRedirect();

    expect($seeded['campaign']->fresh()->status)->toBe(PatchCampaignStatus::Active)
        ->and(AuditLog::query()->where('event_type', AuditEventType::PatchCampaignCompleted)->count())->toBe(0);
});

test('activate with no matching installations completes immediately', function () {
    $fixture = makeCampaignCompletionFixture();

    $customer = Customer::query()->create([
        'organization_id' => $fixture['organization']->id,
        'name' => 'Already current',
        'criticality' => CustomerCriticality::Low,
        'is_active' => true,
    ]);

    ProductDeployment::query()->create([
        'organization_id' => $fixture['organization']->id,
        'customer_id' => $customer->id,
        'product_id' => $fixture['product']->id,
        'product_version_id' => $fixture['versionTarget']->id,
        'environment' => DeploymentEnvironment::Production,
    ]);

    $this->actingAs($fixture['owner'])
        ->post(route('products.campaigns.store', $fixture['product']), [
            'title' => 'Nothing to patch',
            'target_version_id' => $fixture['versionTarget']->id,
            'activate' => true,
        ])
        ->assertRedirect();

    $campaign = PatchCampaign::query()->where('title', 'Nothing to patch')->firstOrFail();

    expect($campaign->status)->toBe(PatchCampaignStatus::Completed)
        ->and($campaign->targets()->count())->toBe(0)
        ->and($campaign->completed_at)->not->toBeNull()
        ->and(AuditLog::query()->where('event_type', AuditEventType::PatchCampaignCompleted)->count())->toBe(1);
});
