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
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     organization: Organization,
 *     owner: User,
 *     viewer: User,
 *     product: Product,
 *     campaign: PatchCampaign,
 *     target: PatchCampaignTarget
 * }
 */
function makeCampaignExportFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Campaign Export Org',
        'slug' => 'campaign-export-org',
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
        'name' => 'Export Product',
        'slug' => 'export-product',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => false,
        'scope_status' => ScopeStatus::InsufficientInformation,
        'classification_status' => ClassificationStatus::Unclassified,
    ]);

    $versionOld = ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '4.0.0',
        'state' => ProductVersionState::Released,
        'support_status' => SupportStatus::Supported,
    ]);

    $versionTarget = ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '4.1.0',
        'state' => ProductVersionState::Released,
        'support_status' => SupportStatus::Supported,
    ]);

    $customer = Customer::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Export Customer',
        'external_ref' => 'CRM-77',
        'primary_contact' => 'ops@export.example',
        'criticality' => CustomerCriticality::High,
        'is_active' => true,
    ]);

    $deployment = ProductDeployment::query()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'product_id' => $product->id,
        'product_version_id' => $versionOld->id,
        'environment' => DeploymentEnvironment::Production,
        'internet_exposure' => true,
    ]);

    $campaign = PatchCampaign::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'target_version_id' => $versionTarget->id,
        'title' => 'Export campaign',
        'status' => PatchCampaignStatus::Active,
        'started_at' => now(),
        'created_by' => $owner->id,
    ]);

    $target = PatchCampaignTarget::query()->create([
        'campaign_id' => $campaign->id,
        'deployment_id' => $deployment->id,
        'status' => PatchCampaignTargetStatus::Notified,
        'notified_at' => now(),
        'notification_note' => 'Emailed ops@export.example',
    ]);

    return compact('organization', 'owner', 'viewer', 'product', 'campaign', 'target');
}

/**
 * @return list<list<string|null>>
 */
function readCampaignExportSheet(string $binary): array
{
    $path = tempnam(sys_get_temp_dir(), 'campaign-xlsx-');
    expect($path)->not->toBeFalse();

    file_put_contents($path, $binary);

    try {
        $spreadsheet = IOFactory::load($path);

        return $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
    } finally {
        @unlink($path);
    }
}

test('owner can export affected customers xlsx from campaign', function () {
    $fixture = makeCampaignExportFixture();

    $response = $this->actingAs($fixture['owner'])
        ->get(route('products.campaigns.export', [
            $fixture['product'],
            $fixture['campaign'],
        ]));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('.xlsx')
        ->and($response->headers->get('content-type'))
        ->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $rows = readCampaignExportSheet($response->streamedContent());

    expect($rows[0][0])->toBe('Customer')
        ->and($rows[1][0])->toBe('Export Customer')
        ->and($rows[1][1])->toBe('CRM-77')
        ->and($rows[1][2])->toBe('ops@export.example')
        ->and($rows[1][3])->toBe('high')
        ->and($rows[1][4])->toBe('production')
        ->and($rows[1][5])->toBe('4.0.0')
        ->and($rows[1][6])->toBe('4.1.0')
        ->and($rows[1][7])->toBe('notified')
        ->and($rows[1][11])->toBe('Emailed ops@export.example')
        ->and(AuditLog::query()->where('event_type', AuditEventType::PatchCampaignExported)->count())->toBe(1);
});

test('viewer can export campaign xlsx', function () {
    $fixture = makeCampaignExportFixture();

    $this->actingAs($fixture['viewer'])
        ->get(route('products.campaigns.export', [
            $fixture['product'],
            $fixture['campaign'],
        ]))
        ->assertOk();

    expect(AuditLog::query()->where('event_type', AuditEventType::PatchCampaignExported)->count())->toBe(1);
});

test('export xlsx includes header row when campaign has no targets', function () {
    $fixture = makeCampaignExportFixture();
    $fixture['target']->delete();

    $response = $this->actingAs($fixture['owner'])
        ->get(route('products.campaigns.export', [
            $fixture['product'],
            $fixture['campaign'],
        ]));

    $response->assertOk();
    $rows = readCampaignExportSheet($response->streamedContent());

    expect($rows)->toHaveCount(1)
        ->and($rows[0][0])->toBe('Customer')
        ->and($rows[0][1])->toBe('External ref');
});
