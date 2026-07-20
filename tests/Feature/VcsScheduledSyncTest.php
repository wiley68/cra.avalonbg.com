<?php

use App\Enums\AuditEventType;
use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\VcsAuthType;
use App\Enums\VcsConnectionStatus;
use App\Enums\VcsProvider;
use App\Enums\VcsSyncSchedule;
use App\Jobs\SyncProductRepositoryJob;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationVcsConnection;
use App\Models\Product;
use App\Models\ProductRepository;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User, connection: OrganizationVcsConnection, repository: ProductRepository}
 */
function makeScheduledSyncFixture(VcsSyncSchedule $schedule = VcsSyncSchedule::Hourly): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Schedule Org',
        'slug' => 'schedule-org',
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

    $connection = OrganizationVcsConnection::query()->create([
        'organization_id' => $organization->id,
        'provider' => VcsProvider::Github,
        'auth_type' => VcsAuthType::Pat,
        'token' => 'ghp_schedule_token',
        'label' => 'GitHub',
        'status' => VcsConnectionStatus::Active,
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

    $repository = ProductRepository::query()->create([
        'product_id' => $product->id,
        'connection_id' => $connection->id,
        'external_id' => '1',
        'full_name' => 'acme/widget',
        'remote_url' => 'https://github.com/acme/widget',
        'default_branch' => 'main',
        'last_synced_at' => null,
    ]);

    return compact('organization', 'owner', 'connection', 'repository');
}

test('owner can update sync schedule from integrations settings', function () {
    ['owner' => $owner, 'connection' => $connection] = makeScheduledSyncFixture(VcsSyncSchedule::Off);

    $this->actingAs($owner)
        ->put(route('settings.integrations.sync-schedule.update', $connection), [
            'sync_schedule' => VcsSyncSchedule::Daily->value,
        ])
        ->assertRedirect();

    expect($connection->fresh()->sync_schedule)->toBe(VcsSyncSchedule::Daily)
        ->and(AuditLog::query()->where('event_type', AuditEventType::VcsConnectionUpdated)->count())->toBe(1);

    $this->actingAs($owner)
        ->get(route('settings.integrations.edit'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->where('connections.0.sync_schedule', 'daily'));
});

test('read-only user cannot update sync schedule', function () {
    ['organization' => $organization, 'connection' => $connection] = makeScheduledSyncFixture();

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
        ->put(route('settings.integrations.sync-schedule.update', $connection), [
            'sync_schedule' => VcsSyncSchedule::Daily->value,
        ])
        ->assertForbidden();

    expect($connection->fresh()->sync_schedule)->toBe(VcsSyncSchedule::Hourly);
});

test('vcs sync scheduled command dispatches due repositories', function () {
    Queue::fake();

    ['repository' => $repository] = makeScheduledSyncFixture(VcsSyncSchedule::Hourly);

    $this->artisan('vcs:sync-scheduled')
        ->expectsOutputToContain('Dispatched 1 VCS sync job(s)')
        ->assertSuccessful();

    Queue::assertPushed(SyncProductRepositoryJob::class, function (SyncProductRepositoryJob $job) use ($repository) {
        return $job->repositoryId === $repository->id
            && $job->triggeredByUserId === null;
    });
});

test('vcs sync scheduled command skips repositories not due', function () {
    Queue::fake();

    ['repository' => $repository] = makeScheduledSyncFixture(VcsSyncSchedule::Hourly);
    $repository->update(['last_synced_at' => now()->subMinutes(10)]);

    $this->artisan('vcs:sync-scheduled')
        ->expectsOutputToContain('Dispatched 0 VCS sync job(s); skipped 1')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

test('vcs sync scheduled command skips off schedule and inactive organizations', function () {
    Queue::fake();

    ['organization' => $organization, 'repository' => $offRepo] = makeScheduledSyncFixture(VcsSyncSchedule::Off);

    $inactiveOrg = Organization::query()->create([
        'name' => 'Inactive Schedule Org',
        'slug' => 'inactive-schedule-org',
        'is_active' => false,
    ]);

    $inactiveConnection = OrganizationVcsConnection::query()->create([
        'organization_id' => $inactiveOrg->id,
        'provider' => VcsProvider::Github,
        'auth_type' => VcsAuthType::Pat,
        'token' => 'ghp_inactive',
        'label' => 'GitHub',
        'status' => VcsConnectionStatus::Active,
        'sync_schedule' => VcsSyncSchedule::Hourly,
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

    ProductRepository::query()->create([
        'product_id' => $inactiveProduct->id,
        'connection_id' => $inactiveConnection->id,
        'external_id' => '2',
        'full_name' => 'acme/inactive',
        'remote_url' => 'https://github.com/acme/inactive',
        'default_branch' => 'main',
    ]);

    $this->artisan('vcs:sync-scheduled')
        ->expectsOutputToContain('Dispatched 0 VCS sync job(s)')
        ->assertSuccessful();

    Queue::assertNothingPushed();
    expect($organization->is_active)->toBeTrue()
        ->and($offRepo->connection->sync_schedule)->toBe(VcsSyncSchedule::Off);
});

test('daily schedule is due after twenty four hours', function () {
    Queue::fake();

    ['repository' => $repository] = makeScheduledSyncFixture(VcsSyncSchedule::Daily);
    $repository->update(['last_synced_at' => now()->subHours(25)]);

    $this->artisan('vcs:sync-scheduled')->assertSuccessful();

    Queue::assertPushed(SyncProductRepositoryJob::class, 1);
});

test('vcs sync scheduled is registered on the hourly schedule', function () {
    $events = Illuminate\Support\Facades\Schedule::events();

    $match = collect($events)->first(
        fn($event) => str_contains($event->command ?? '', 'vcs:sync-scheduled'),
    );

    expect($match)->not->toBeNull()
        ->and($match->expression)->toBe('0 * * * *');
});
