<?php

use App\Enums\IntegrationAuthType;
use App\Enums\IntegrationCategory;
use App\Enums\IntegrationConnectionStatus;
use App\Enums\IntegrationProvider;
use App\Enums\IntegrationSyncSchedule;
use App\Enums\VcsAuthType;
use App\Enums\VcsConnectionStatus;
use App\Enums\VcsProvider;
use App\Enums\VcsSyncSchedule;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Models\OrganizationVcsConnection;
use App\Models\Role;
use App\Models\User;
use App\Services\OpsQueueHealthHintService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User}
 */
function makeOpsQueueHintFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Ops Hint Org',
        'slug' => 'ops-hint-org-' . uniqid(),
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

    return compact('organization', 'owner');
}

test('ops queue hint is null when scheduled sync is off', function () {
    config(['queue.default' => 'sync']);

    ['organization' => $organization, 'owner' => $owner] = makeOpsQueueHintFixture();

    OrganizationVcsConnection::query()->create([
        'organization_id' => $organization->id,
        'provider' => VcsProvider::Github,
        'auth_type' => VcsAuthType::Pat,
        'token' => 'ghp_ops',
        'label' => 'GitHub',
        'status' => VcsConnectionStatus::Active,
        'sync_schedule' => VcsSyncSchedule::Off,
        'last_verified_at' => now(),
    ]);

    expect(app(OpsQueueHealthHintService::class)->hintForOrganization($organization))->toBeNull();

    $this->actingAs($owner)
        ->get(route('integrations.health.index'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->where('opsQueueHint', null));

    $this->actingAs($owner)
        ->get(route('settings.integrations.edit'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('settings/Integrations')
            ->where('opsQueueHint', null));
});

test('ops queue hint warns when queue is sync and scheduled sync is enabled', function () {
    config(['queue.default' => 'sync']);

    ['organization' => $organization, 'owner' => $owner] = makeOpsQueueHintFixture();

    OrganizationIntegration::query()->create([
        'organization_id' => $organization->id,
        'provider' => IntegrationProvider::Snyk,
        'category' => IntegrationCategory::Scanner,
        'auth_type' => IntegrationAuthType::ApiToken,
        'credentials' => ['api_token' => 'token'],
        'label' => 'Snyk',
        'status' => IntegrationConnectionStatus::Active,
        'sync_schedule' => IntegrationSyncSchedule::Hourly,
        'last_verified_at' => now(),
    ]);

    $expectedHint = [
        'level' => OpsQueueHealthHintService::LEVEL_FAIL,
        'code' => OpsQueueHealthHintService::CODE_QUEUE_SYNC,
    ];

    $hint = app(OpsQueueHealthHintService::class)->hintForOrganization($organization);

    expect($hint)->toBe($expectedHint);

    $this->actingAs($owner)
        ->get(route('integrations.health.index'))
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($expectedHint) {
            $page->where('opsQueueHint', $expectedHint);
        });

    $this->actingAs($owner)
        ->get(route('settings.integrations.edit'))
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($expectedHint) {
            $page->where('opsQueueHint', $expectedHint);
        });
});

test('ops queue hint detects stale pending database jobs', function () {
    config(['queue.default' => 'database']);

    ['organization' => $organization, 'owner' => $owner] = makeOpsQueueHintFixture();

    OrganizationVcsConnection::query()->create([
        'organization_id' => $organization->id,
        'provider' => VcsProvider::Github,
        'auth_type' => VcsAuthType::Pat,
        'token' => 'ghp_ops',
        'label' => 'GitHub',
        'status' => VcsConnectionStatus::Active,
        'sync_schedule' => VcsSyncSchedule::Daily,
        'last_verified_at' => now(),
    ]);

    expect(app(OpsQueueHealthHintService::class)->hintForOrganization($organization))->toBeNull();

    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => '{}',
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->subMinutes(OpsQueueHealthHintService::STALE_JOB_MINUTES + 5)->getTimestamp(),
        'created_at' => now()->subMinutes(OpsQueueHealthHintService::STALE_JOB_MINUTES + 5)->getTimestamp(),
    ]);

    $expectedHint = [
        'level' => OpsQueueHealthHintService::LEVEL_FAIL,
        'code' => OpsQueueHealthHintService::CODE_STALE_JOBS,
    ];

    $hint = app(OpsQueueHealthHintService::class)->hintForOrganization($organization);

    expect($hint)->toBe($expectedHint);

    $this->actingAs($owner)
        ->get(route('integrations.health.index'))
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($expectedHint) {
            $page->where('opsQueueHint', $expectedHint);
        });
});

test('ops queue hint i18n keys exist in en and bg', function () {
    $en = json_decode((string) file_get_contents(lang_path('en.json')), true);
    $bg = json_decode((string) file_get_contents(lang_path('bg.json')), true);

    $codes = [
        OpsQueueHealthHintService::CODE_QUEUE_SYNC,
        OpsQueueHealthHintService::CODE_STALE_JOBS,
    ];

    foreach ($codes as $code) {
        $enValue = $en['integrations']['health']['ops_hints'][$code] ?? null;
        $bgValue = $bg['integrations']['health']['ops_hints'][$code] ?? null;

        expect($enValue)->toBeString()->not->toBe('')
            ->and($bgValue)->toBeString()->not->toBe('')
            ->and($enValue)->not->toBe($bgValue);
    }
});
