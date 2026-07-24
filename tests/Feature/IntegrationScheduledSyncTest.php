<?php

use App\Enums\AuditEventType;
use App\Enums\ClassificationStatus;
use App\Enums\IntegrationAuthType;
use App\Enums\IntegrationCategory;
use App\Enums\IntegrationConnectionStatus;
use App\Enums\IntegrationProvider;
use App\Enums\IntegrationSyncSchedule;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Jobs\SyncProductIntegrationJob;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Models\Product;
use App\Models\ProductIntegrationLink;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     organization: Organization,
 *     owner: User,
 *     integration: OrganizationIntegration,
 *     link: ProductIntegrationLink
 * }
 */
function makeIntegrationScheduledSyncFixture(
    IntegrationSyncSchedule $schedule = IntegrationSyncSchedule::Hourly,
): array {
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Integration Schedule Org',
        'slug' => 'integration-schedule-org-' . uniqid(),
        'is_active' => true,
        'locale' => 'en',
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

    $integration = OrganizationIntegration::query()->create([
        'organization_id' => $organization->id,
        'provider' => IntegrationProvider::Jira,
        'category' => IntegrationCategory::Alm,
        'auth_type' => IntegrationAuthType::ApiToken,
        'credentials' => [
            'base_url' => 'https://acme.atlassian.net',
            'email' => 'owner@acme.example',
            'api_token' => 'jira_schedule_token',
        ],
        'label' => 'Jira',
        'status' => IntegrationConnectionStatus::Active,
        'sync_schedule' => $schedule,
        'last_verified_at' => now(),
    ]);

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Schedule Product',
        'slug' => 'schedule-product',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => false,
        'scope_status' => ScopeStatus::InsufficientInformation,
        'classification_status' => ClassificationStatus::Unclassified,
    ]);

    $link = ProductIntegrationLink::query()->create([
        'product_id' => $product->id,
        'integration_id' => $integration->id,
        'external_project_key' => 'CRA',
        'external_label' => 'CRA board',
        'last_synced_at' => null,
    ]);

    return compact('organization', 'owner', 'integration', 'link');
}

test('owner can update integration sync schedule from integrations settings', function () {
    ['owner' => $owner, 'integration' => $integration] = makeIntegrationScheduledSyncFixture(
        IntegrationSyncSchedule::Off,
    );

    $this->actingAs($owner)
        ->put(route('settings.integrations.providers.sync-schedule.update', $integration), [
            'sync_schedule' => IntegrationSyncSchedule::Daily->value,
        ])
        ->assertRedirect();

    expect($integration->fresh()->sync_schedule)->toBe(IntegrationSyncSchedule::Daily)
        ->and(AuditLog::query()->where('event_type', AuditEventType::IntegrationUpdated)->count())->toBe(1);

    $this->actingAs($owner)
        ->get(route('settings.integrations.edit'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->where('integrations.0.sync_schedule', 'daily'));
});

test('read-only user cannot update integration sync schedule', function () {
    ['organization' => $organization, 'integration' => $integration] = makeIntegrationScheduledSyncFixture();

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
        ->put(route('settings.integrations.providers.sync-schedule.update', $integration), [
            'sync_schedule' => IntegrationSyncSchedule::Daily->value,
        ])
        ->assertForbidden();

    expect($integration->fresh()->sync_schedule)->toBe(IntegrationSyncSchedule::Hourly);
});

test('integrations sync scheduled command dispatches due links', function () {
    Queue::fake();

    ['link' => $link] = makeIntegrationScheduledSyncFixture(IntegrationSyncSchedule::Hourly);

    $this->artisan('integrations:sync-scheduled')
        ->expectsOutputToContain('Dispatched 1 integration sync job(s)')
        ->assertSuccessful();

    Queue::assertPushed(SyncProductIntegrationJob::class, function (SyncProductIntegrationJob $job) use ($link) {
        return $job->linkId === $link->id
            && $job->triggeredByUserId === null;
    });
});

test('integrations sync scheduled command skips links not due', function () {
    Queue::fake();

    ['link' => $link] = makeIntegrationScheduledSyncFixture(IntegrationSyncSchedule::Hourly);
    $link->update(['last_synced_at' => now()->subMinutes(10)]);

    $this->artisan('integrations:sync-scheduled')
        ->expectsOutputToContain('Dispatched 0 integration sync job(s); skipped 1')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

test('integrations sync scheduled command skips off schedule and inactive organizations', function () {
    Queue::fake();

    ['organization' => $organization, 'link' => $offLink] = makeIntegrationScheduledSyncFixture(
        IntegrationSyncSchedule::Off,
    );

    $inactiveOrg = Organization::query()->create([
        'name' => 'Inactive Integration Schedule Org',
        'slug' => 'inactive-integration-schedule-org',
        'is_active' => false,
        'locale' => 'en',
    ]);

    $inactiveIntegration = OrganizationIntegration::query()->create([
        'organization_id' => $inactiveOrg->id,
        'provider' => IntegrationProvider::Jira,
        'category' => IntegrationCategory::Alm,
        'auth_type' => IntegrationAuthType::ApiToken,
        'credentials' => [
            'base_url' => 'https://inactive.atlassian.net',
            'email' => 'owner@inactive.example',
            'api_token' => 'jira_inactive',
        ],
        'label' => 'Jira',
        'status' => IntegrationConnectionStatus::Active,
        'sync_schedule' => IntegrationSyncSchedule::Hourly,
        'last_verified_at' => now(),
    ]);

    $inactiveProduct = Product::query()->create([
        'organization_id' => $inactiveOrg->id,
        'name' => 'Inactive Product',
        'slug' => 'inactive-product',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => false,
        'scope_status' => ScopeStatus::InsufficientInformation,
        'classification_status' => ClassificationStatus::Unclassified,
    ]);

    ProductIntegrationLink::query()->create([
        'product_id' => $inactiveProduct->id,
        'integration_id' => $inactiveIntegration->id,
        'external_project_key' => 'OFF',
        'external_label' => 'Inactive',
    ]);

    $this->artisan('integrations:sync-scheduled')
        ->expectsOutputToContain('Dispatched 0 integration sync job(s)')
        ->assertSuccessful();

    Queue::assertNothingPushed();
    expect($organization->is_active)->toBeTrue()
        ->and($offLink->integration->sync_schedule)->toBe(IntegrationSyncSchedule::Off);
});

test('daily integration schedule is due after twenty four hours', function () {
    Queue::fake();

    ['link' => $link] = makeIntegrationScheduledSyncFixture(IntegrationSyncSchedule::Daily);
    $link->update(['last_synced_at' => now()->subHours(25)]);

    $this->artisan('integrations:sync-scheduled')->assertSuccessful();

    Queue::assertPushed(SyncProductIntegrationJob::class, 1);
});

test('integrations sync scheduled is registered on the hourly schedule', function () {
    $events = Illuminate\Support\Facades\Schedule::events();

    $match = collect($events)->first(
        fn($event) => str_contains($event->command ?? '', 'integrations:sync-scheduled'),
    );

    expect($match)->not->toBeNull()
        ->and($match->expression)->toBe('0 * * * *');
});
