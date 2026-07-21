<?php

use App\Enums\AuditEventType;
use App\Enums\ClassificationStatus;
use App\Enums\CustomerCriticality;
use App\Enums\DeploymentEnvironment;
use App\Enums\LicensingModel;
use App\Enums\PatchCampaignStatus;
use App\Enums\PatchCampaignTargetNotificationChannel;
use App\Enums\PatchCampaignTargetNotificationEventType;
use App\Enums\PatchCampaignTargetStatus;
use App\Enums\ProductType;
use App\Enums\ProductVersionState;
use App\Enums\ScopeStatus;
use App\Enums\SupportStatus;
use App\Jobs\SendPatchCampaignCustomerNotificationJob;
use App\Mail\PatchCampaignCustomerNotification;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\PatchCampaign;
use App\Models\PatchCampaignTarget;
use App\Models\PatchCampaignTargetNotificationEvent;
use App\Models\Product;
use App\Models\ProductDeployment;
use App\Models\ProductVersion;
use App\Models\Role;
use App\Models\User;
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
 *     target: PatchCampaignTarget
 * }
 */
function makeNotificationLogFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Notification Log Org',
        'slug' => 'notification-log-org',
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
        'name' => 'Notification Log Product',
        'slug' => 'notification-log-product',
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
        'version_number' => '2.0.0',
        'state' => ProductVersionState::Released,
        'support_status' => SupportStatus::Supported,
    ]);

    $customer = Customer::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Log Customer',
        'primary_contact' => 'ops@log.example',
        'criticality' => CustomerCriticality::Medium,
        'is_active' => true,
    ]);

    $deployment = ProductDeployment::query()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'product_id' => $product->id,
        'product_version_id' => $versionOld->id,
        'environment' => DeploymentEnvironment::Production,
        'internet_exposure' => false,
        'custom_modifications' => false,
        'end_of_support_exception' => false,
    ]);

    $campaign = PatchCampaign::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'target_version_id' => $versionTarget->id,
        'title' => 'Log Campaign',
        'status' => PatchCampaignStatus::Active,
        'started_at' => now(),
        'created_by' => $owner->id,
    ]);

    $target = PatchCampaignTarget::query()->create([
        'campaign_id' => $campaign->id,
        'deployment_id' => $deployment->id,
        'status' => PatchCampaignTargetStatus::Pending,
    ]);

    return compact('organization', 'owner', 'product', 'campaign', 'target');
}

test('manual target status update appends notification log event', function () {
    $fixture = makeNotificationLogFixture();

    $this->actingAs($fixture['owner'])
        ->put(route('products.campaigns.targets.update', [
            'product' => $fixture['product'],
            'campaign' => $fixture['campaign'],
            'target' => $fixture['target'],
        ]), [
            'status' => PatchCampaignTargetStatus::Notified->value,
            'notification_note' => 'Called customer contact',
        ])
        ->assertRedirect();

    $event = PatchCampaignTargetNotificationEvent::query()
        ->where('patch_campaign_target_id', $fixture['target']->id)
        ->sole();

    expect($event->event_type)->toBe(PatchCampaignTargetNotificationEventType::StatusChanged)
        ->and($event->channel)->toBe(PatchCampaignTargetNotificationChannel::Manual)
        ->and($event->status_before)->toBe(PatchCampaignTargetStatus::Pending->value)
        ->and($event->status_after)->toBe(PatchCampaignTargetStatus::Notified->value)
        ->and($event->body)->toBe('Called customer contact')
        ->and($event->created_by)->toBe($fixture['owner']->id)
        ->and($event->updated_at ?? null)->toBeNull();
});

test('campaign show payload includes notification events per target', function () {
    $fixture = makeNotificationLogFixture();

    PatchCampaignTargetNotificationEvent::query()->create([
        'patch_campaign_target_id' => $fixture['target']->id,
        'event_type' => PatchCampaignTargetNotificationEventType::EmailQueued,
        'channel' => PatchCampaignTargetNotificationChannel::Email,
        'status_before' => PatchCampaignTargetStatus::Pending->value,
        'status_after' => PatchCampaignTargetStatus::Notified->value,
        'recipient' => 'ops@log.example',
        'created_by' => $fixture['owner']->id,
        'created_at' => now(),
    ]);

    $this->actingAs($fixture['owner'])
        ->get(route('products.campaigns.show', [
            'product' => $fixture['product'],
            'campaign' => $fixture['campaign'],
        ]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/campaigns/Show')
            ->has('campaign.targets.0.notification_events', 1)
            ->where(
                'campaign.targets.0.notification_events.0.event_type',
                PatchCampaignTargetNotificationEventType::EmailQueued->value,
            ));
});

test('queue notifications appends email queued events and job appends email sent event', function () {
    config(['customer_notifications.enabled' => true]);
    Mail::fake();
    Queue::fake();

    $fixture = makeNotificationLogFixture();

    $this->actingAs($fixture['owner'])
        ->post(route('products.campaigns.notify', [
            'product' => $fixture['product'],
            'campaign' => $fixture['campaign'],
        ]))
        ->assertRedirect();

    Queue::assertPushed(SendPatchCampaignCustomerNotificationJob::class, 1);

    $queuedEvent = PatchCampaignTargetNotificationEvent::query()
        ->where('patch_campaign_target_id', $fixture['target']->id)
        ->where('event_type', PatchCampaignTargetNotificationEventType::EmailQueued)
        ->sole();

    expect($queuedEvent->recipient)->toBe('ops@log.example')
        ->and($queuedEvent->channel)->toBe(PatchCampaignTargetNotificationChannel::Email);

    $job = new SendPatchCampaignCustomerNotificationJob(
        $fixture['target']->id,
        $fixture['owner']->id,
    );
    $job->handle();

    Mail::assertSent(PatchCampaignCustomerNotification::class, 1);

    $sentEvent = PatchCampaignTargetNotificationEvent::query()
        ->where('patch_campaign_target_id', $fixture['target']->id)
        ->where('event_type', PatchCampaignTargetNotificationEventType::EmailSent)
        ->sole();

    expect($sentEvent->recipient)->toBe('ops@log.example')
        ->and($sentEvent->status_after)->toBe(PatchCampaignTargetStatus::Notified->value)
        ->and($sentEvent->body)->toContain('ops@log.example');

    expect(
        PatchCampaignTargetNotificationEvent::query()
            ->where('patch_campaign_target_id', $fixture['target']->id)
            ->count(),
    )->toBe(2);
});

test('notification events are append-only and deleted with target', function () {
    $fixture = makeNotificationLogFixture();

    PatchCampaignTargetNotificationEvent::query()->create([
        'patch_campaign_target_id' => $fixture['target']->id,
        'event_type' => PatchCampaignTargetNotificationEventType::StatusChanged,
        'channel' => PatchCampaignTargetNotificationChannel::Manual,
        'status_before' => PatchCampaignTargetStatus::Pending->value,
        'status_after' => PatchCampaignTargetStatus::Notified->value,
        'created_at' => now(),
    ]);

    expect(PatchCampaignTargetNotificationEvent::query()->count())->toBe(1);

    $fixture['target']->delete();

    expect(PatchCampaignTargetNotificationEvent::query()->count())->toBe(0);
});
