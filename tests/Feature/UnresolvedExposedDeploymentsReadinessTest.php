<?php

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
function makeDeploymentsReadinessFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Deployments Readiness Org',
        'slug' => 'deployments-readiness-org',
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
        'name' => 'Deployments Readiness Product',
        'slug' => 'deployments-readiness-product',
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

    $versionOld = ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '1.0.0',
        'state' => ProductVersionState::Released,
        'support_status' => SupportStatus::Supported,
    ]);

    $versionTarget = ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '1.0.1',
        'state' => ProductVersionState::Released,
        'support_status' => SupportStatus::Supported,
    ]);

    return [
        'organization' => $organization,
        'owner' => $owner,
        'product' => $product,
        'versionOld' => $versionOld,
        'versionTarget' => $versionTarget,
    ];
}

/**
 * @param  array{organization: Organization, product: Product, versionOld: ProductVersion, versionTarget: ProductVersion}  $fixture
 * @return array{campaign: PatchCampaign, highTarget: PatchCampaignTarget, mediumTarget: PatchCampaignTarget}
 */
function seedActiveCampaignWithTargets(array $fixture): array
{
    $highCustomer = Customer::query()->create([
        'organization_id' => $fixture['organization']->id,
        'name' => 'High Crit Customer',
        'criticality' => CustomerCriticality::High,
        'is_active' => true,
    ]);

    $mediumCustomer = Customer::query()->create([
        'organization_id' => $fixture['organization']->id,
        'name' => 'Medium Crit Customer',
        'criticality' => CustomerCriticality::Medium,
        'is_active' => true,
    ]);

    $highDeployment = ProductDeployment::query()->create([
        'organization_id' => $fixture['organization']->id,
        'customer_id' => $highCustomer->id,
        'product_id' => $fixture['product']->id,
        'product_version_id' => $fixture['versionOld']->id,
        'environment' => DeploymentEnvironment::Production,
        'internet_exposure' => true,
        'custom_modifications' => false,
        'end_of_support_exception' => false,
    ]);

    $mediumDeployment = ProductDeployment::query()->create([
        'organization_id' => $fixture['organization']->id,
        'customer_id' => $mediumCustomer->id,
        'product_id' => $fixture['product']->id,
        'product_version_id' => $fixture['versionOld']->id,
        'environment' => DeploymentEnvironment::Production,
        'internet_exposure' => false,
        'custom_modifications' => false,
        'end_of_support_exception' => false,
    ]);

    $campaign = PatchCampaign::query()->create([
        'organization_id' => $fixture['organization']->id,
        'product_id' => $fixture['product']->id,
        'target_version_id' => $fixture['versionTarget']->id,
        'title' => 'Security rollout',
        'status' => PatchCampaignStatus::Active,
        'started_at' => now(),
        'created_by' => null,
    ]);

    $highTarget = PatchCampaignTarget::query()->create([
        'campaign_id' => $campaign->id,
        'deployment_id' => $highDeployment->id,
        'status' => PatchCampaignTargetStatus::Pending,
    ]);

    $mediumTarget = PatchCampaignTarget::query()->create([
        'campaign_id' => $campaign->id,
        'deployment_id' => $mediumDeployment->id,
        'status' => PatchCampaignTargetStatus::Pending,
    ]);

    return [
        'campaign' => $campaign,
        'highTarget' => $highTarget,
        'mediumTarget' => $mediumTarget,
    ];
}

test('no active campaign keeps deployments readiness section clear', function () {
    $fixture = makeDeploymentsReadinessFixture();

    $this->actingAs($fixture['owner'])
        ->get(route('products.readiness.show', $fixture['product']))
        ->assertOk()
        ->assertInertia(function ($page) {
            $props = $page->toArray()['props'];
            $sections = collect($props['report']['sections']);
            $gaps = collect($props['report']['gaps']);
            $deployments = $sections->firstWhere('key', 'deployments');

            expect($deployments['status'])->toBe('pass')
                ->and($deployments['summary'])->toBe('no_active_campaign')
                ->and($gaps->contains(
                    fn($gap) => $gap['message_key'] === 'products.readiness.gaps.unresolved_exposed_deployments',
                ))->toBeFalse();
        });
});

test('active campaign with unresolved high-criticality target adds readiness warn gap', function () {
    $fixture = makeDeploymentsReadinessFixture();
    seedActiveCampaignWithTargets($fixture);

    $this->actingAs($fixture['owner'])
        ->get(route('products.readiness.show', $fixture['product']))
        ->assertOk()
        ->assertInertia(function ($page) {
            $props = $page->toArray()['props'];
            $sections = collect($props['report']['sections']);
            $gaps = collect($props['report']['gaps']);
            $deployments = $sections->firstWhere('key', 'deployments');

            expect($deployments['status'])->toBe('warn')
                ->and($deployments['summary'])->toBe('unresolved_high')
                ->and($deployments['metrics']['unresolved_high_criticality'])->toBe(1)
                ->and($deployments['metrics']['active_campaigns'])->toBe(1)
                ->and($gaps->contains(
                    fn($gap) => $gap['message_key'] === 'products.readiness.gaps.unresolved_exposed_deployments'
                    && $gap['section'] === 'deployments'
                    && $gap['status'] === 'warn'
                    && $gap['link'] === 'campaigns',
                ))->toBeTrue();
        });
});

test('medium-criticality unresolved targets alone do not add deployments gap', function () {
    $fixture = makeDeploymentsReadinessFixture();
    ['highTarget' => $highTarget] = seedActiveCampaignWithTargets($fixture);

    $highTarget->update([
        'status' => PatchCampaignTargetStatus::Updated,
        'confirmed_at' => now(),
    ]);

    $this->actingAs($fixture['owner'])
        ->get(route('products.readiness.show', $fixture['product']))
        ->assertOk()
        ->assertInertia(function ($page) {
            $props = $page->toArray()['props'];
            $sections = collect($props['report']['sections']);
            $gaps = collect($props['report']['gaps']);
            $deployments = $sections->firstWhere('key', 'deployments');

            expect($deployments['status'])->toBe('pass')
                ->and($deployments['summary'])->toBe('campaigns_clear')
                ->and($deployments['metrics']['unresolved_high_criticality'])->toBe(0)
                ->and($gaps->contains(
                    fn($gap) => $gap['message_key'] === 'products.readiness.gaps.unresolved_exposed_deployments',
                ))->toBeFalse();
        });
});

test('excepted high-criticality target clears unresolved deployments gap', function () {
    $fixture = makeDeploymentsReadinessFixture();
    ['highTarget' => $highTarget] = seedActiveCampaignWithTargets($fixture);

    $highTarget->update([
        'status' => PatchCampaignTargetStatus::Excepted,
        'confirmed_at' => now(),
        'notification_note' => 'Air-gapped site',
    ]);

    $this->actingAs($fixture['owner'])
        ->get(route('products.readiness.show', $fixture['product']))
        ->assertOk()
        ->assertInertia(function ($page) {
            $props = $page->toArray()['props'];
            $sections = collect($props['report']['sections']);
            $gaps = collect($props['report']['gaps']);
            $deployments = $sections->firstWhere('key', 'deployments');

            expect($deployments['status'])->toBe('pass')
                ->and($deployments['summary'])->toBe('campaigns_clear')
                ->and($gaps->contains(
                    fn($gap) => $gap['message_key'] === 'products.readiness.gaps.unresolved_exposed_deployments',
                ))->toBeFalse();
        });
});
