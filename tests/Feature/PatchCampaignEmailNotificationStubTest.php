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
use App\Jobs\SendPatchCampaignCustomerNotificationJob;
use App\Mail\PatchCampaignCustomerNotification;
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
use App\Support\CustomerContactEmail;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     organization: Organization,
 *     owner: User,
 *     product: Product,
 *     campaign: PatchCampaign,
 *     targetWithEmail: PatchCampaignTarget,
 *     targetWithoutEmail: PatchCampaignTarget
 * }
 */
function makeCampaignNotificationFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Notify Org',
        'slug' => 'notify-org',
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
        'name' => 'Notify Product',
        'slug' => 'notify-product',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => false,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
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

    $customerWithEmail = Customer::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Email Customer',
        'primary_contact' => 'Ops Desk <ops@example.com>',
        'criticality' => CustomerCriticality::High,
        'is_active' => true,
    ]);

    $customerWithoutEmail = Customer::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Phone Customer',
        'primary_contact' => 'Call +359 88 000 000',
        'criticality' => CustomerCriticality::Medium,
        'is_active' => true,
    ]);

    $deploymentWithEmail = ProductDeployment::query()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customerWithEmail->id,
        'product_id' => $product->id,
        'product_version_id' => $versionOld->id,
        'environment' => DeploymentEnvironment::Production,
        'internet_exposure' => true,
        'custom_modifications' => false,
        'end_of_support_exception' => false,
    ]);

    $deploymentWithoutEmail = ProductDeployment::query()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customerWithoutEmail->id,
        'product_id' => $product->id,
        'product_version_id' => $versionOld->id,
        'environment' => DeploymentEnvironment::Staging,
        'internet_exposure' => false,
        'custom_modifications' => false,
        'end_of_support_exception' => false,
    ]);

    $campaign = PatchCampaign::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'target_version_id' => $versionTarget->id,
        'title' => 'Notify rollout',
        'status' => PatchCampaignStatus::Active,
        'started_at' => now(),
        'notes' => 'Please update promptly.',
    ]);

    $targetWithEmail = PatchCampaignTarget::query()->create([
        'campaign_id' => $campaign->id,
        'deployment_id' => $deploymentWithEmail->id,
        'status' => PatchCampaignTargetStatus::Pending,
    ]);

    $targetWithoutEmail = PatchCampaignTarget::query()->create([
        'campaign_id' => $campaign->id,
        'deployment_id' => $deploymentWithoutEmail->id,
        'status' => PatchCampaignTargetStatus::Pending,
    ]);

    return [
        'organization' => $organization,
        'owner' => $owner,
        'product' => $product,
        'campaign' => $campaign,
        'targetWithEmail' => $targetWithEmail,
        'targetWithoutEmail' => $targetWithoutEmail,
    ];
}

test('customer contact email extractor finds address in free text', function () {
    expect(CustomerContactEmail::extract('Ops Desk <ops@example.com>'))
        ->toBe('ops@example.com')
        ->and(CustomerContactEmail::extract('plain@example.org'))
        ->toBe('plain@example.org')
        ->and(CustomerContactEmail::extract('no email here'))
        ->toBeNull();
});

test('owner can queue stub email notifications for pending targets with contact email', function () {
    Mail::fake();
    $fixture = makeCampaignNotificationFixture();

    $this->actingAs($fixture['owner'])
        ->post(route('products.campaigns.notify', [
            $fixture['product'],
            $fixture['campaign'],
        ]))
        ->assertRedirect(route('products.campaigns.show', [
            $fixture['product'],
            $fixture['campaign'],
        ]));

    Mail::assertSent(PatchCampaignCustomerNotification::class, function (PatchCampaignCustomerNotification $mail, ) use ($fixture): bool {
        return $mail->hasTo('ops@example.com')
            && $mail->campaign->is($fixture['campaign'])
            && $mail->customerName === 'Email Customer';
    });

    expect($fixture['targetWithEmail']->fresh()->status)
        ->toBe(PatchCampaignTargetStatus::Notified)
        ->and($fixture['targetWithEmail']->fresh()->notified_at)->not->toBeNull()
        ->and($fixture['targetWithEmail']->fresh()->notification_note)
        ->toContain('ops@example.com')
        ->and($fixture['targetWithoutEmail']->fresh()->status)
        ->toBe(PatchCampaignTargetStatus::Pending);

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::PatchCampaignNotificationsQueued)
        ->exists())->toBeTrue();
});

test('notify action dispatches queued jobs when queue is not sync', function () {
    Queue::fake();
    $fixture = makeCampaignNotificationFixture();

    $this->actingAs($fixture['owner'])
        ->post(route('products.campaigns.notify', [
            $fixture['product'],
            $fixture['campaign'],
        ]))
        ->assertRedirect();

    Queue::assertPushed(SendPatchCampaignCustomerNotificationJob::class, 1);
    Queue::assertPushed(
        SendPatchCampaignCustomerNotificationJob::class,
        fn(SendPatchCampaignCustomerNotificationJob $job) => $job->targetId === $fixture['targetWithEmail']->id,
    );

    expect($fixture['targetWithEmail']->fresh()->status)
        ->toBe(PatchCampaignTargetStatus::Pending);
});

test('notify is forbidden for draft campaigns and when notifications are disabled', function () {
    $fixture = makeCampaignNotificationFixture();
    $fixture['campaign']->update(['status' => PatchCampaignStatus::Draft]);

    $this->actingAs($fixture['owner'])
        ->from(route('products.campaigns.show', [$fixture['product'], $fixture['campaign']]))
        ->post(route('products.campaigns.notify', [
            $fixture['product'],
            $fixture['campaign'],
        ]))
        ->assertRedirect(route('products.campaigns.show', [
            $fixture['product'],
            $fixture['campaign'],
        ]))
        ->assertSessionHasErrors('status');

    $fixture['campaign']->update([
        'status' => PatchCampaignStatus::Active,
        'started_at' => now(),
    ]);

    config(['customer_notifications.enabled' => false]);

    $this->actingAs($fixture['owner'])
        ->from(route('products.campaigns.show', [$fixture['product'], $fixture['campaign']]))
        ->post(route('products.campaigns.notify', [
            $fixture['product'],
            $fixture['campaign'],
        ]))
        ->assertRedirect(route('products.campaigns.show', [
            $fixture['product'],
            $fixture['campaign'],
        ]))
        ->assertSessionHasErrors('notifications');
});
