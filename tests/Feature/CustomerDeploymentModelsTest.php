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
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('customer deployment campaign models persist enums and relations', function () {
    $organization = Organization::query()->create([
        'name' => 'Deploy Org',
        'slug' => 'deploy-org',
        'is_active' => true,
    ]);

    $user = User::factory()->create();

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

    $oldVersion = ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '1.0.0',
        'state' => ProductVersionState::Released,
        'support_status' => SupportStatus::Supported,
    ]);

    $targetVersion = ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '1.1.0',
        'state' => ProductVersionState::Released,
        'support_status' => SupportStatus::Supported,
    ]);

    $customer = Customer::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Acme Bank',
        'external_ref' => 'CRM-1',
        'primary_contact' => 'ops@acme.example',
        'criticality' => CustomerCriticality::High,
        'is_active' => true,
    ]);

    $deployment = ProductDeployment::query()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'product_id' => $product->id,
        'product_version_id' => $oldVersion->id,
        'environment' => DeploymentEnvironment::Production,
        'internet_exposure' => true,
        'custom_modifications' => false,
        'end_of_support_exception' => false,
    ]);

    $campaign = PatchCampaign::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'target_version_id' => $targetVersion->id,
        'title' => 'Patch to 1.1.0',
        'status' => PatchCampaignStatus::Active,
        'started_at' => now(),
        'created_by' => $user->id,
    ]);

    $target = PatchCampaignTarget::query()->create([
        'campaign_id' => $campaign->id,
        'deployment_id' => $deployment->id,
        'status' => PatchCampaignTargetStatus::Pending,
    ]);

    expect($organization->customers)->toHaveCount(1)
        ->and($customer->criticality)->toBe(CustomerCriticality::High)
        ->and($customer->deployments)->toHaveCount(1)
        ->and($product->deployments)->toHaveCount(1)
        ->and($product->patchCampaigns)->toHaveCount(1)
        ->and($deployment->environment)->toBe(DeploymentEnvironment::Production)
        ->and($deployment->productVersion?->id)->toBe($oldVersion->id)
        ->and($campaign->status)->toBe(PatchCampaignStatus::Active)
        ->and($campaign->targetVersion?->id)->toBe($targetVersion->id)
        ->and($campaign->createdByUser?->id)->toBe($user->id)
        ->and($campaign->targets)->toHaveCount(1)
        ->and($target->status)->toBe(PatchCampaignTargetStatus::Pending)
        ->and($target->deployment?->id)->toBe($deployment->id)
        ->and($oldVersion->deployments)->toHaveCount(1)
        ->and($targetVersion->patchCampaignsAsTarget)->toHaveCount(1);
});

test('product deployment unique constraint is customer product environment', function () {
    $organization = Organization::query()->create([
        'name' => 'Unique Deploy Org',
        'slug' => 'unique-deploy-org',
        'is_active' => true,
    ]);

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Unique Product',
        'slug' => 'unique-product',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => false,
        'scope_status' => ScopeStatus::InsufficientInformation,
        'classification_status' => ClassificationStatus::Unclassified,
    ]);

    $customer = Customer::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Unique Customer',
        'criticality' => CustomerCriticality::Medium,
        'is_active' => true,
    ]);

    ProductDeployment::query()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'product_id' => $product->id,
        'environment' => DeploymentEnvironment::Staging,
    ]);

    expect(fn() => ProductDeployment::query()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'product_id' => $product->id,
        'environment' => DeploymentEnvironment::Staging,
    ]))->toThrow(QueryException::class);

    ProductDeployment::query()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'product_id' => $product->id,
        'environment' => DeploymentEnvironment::Production,
    ]);

    expect(ProductDeployment::query()->count())->toBe(2);
});
