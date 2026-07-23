<?php

use App\Enums\ClassificationStatus;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ProductVersionState;
use App\Enums\ScopeStatus;
use App\Enums\SupportStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductIncident;
use App\Models\ProductVersion;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{0: Organization, 1: User}
 */
function makeIncidentsOrgWithOwner(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Incidents Org',
        'slug' => 'incidents-org',
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

    return [$organization, $owner];
}

function makeIncidentsOrgReadOnly(Organization $organization): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $role = Role::query()->where('slug', 'read_only')->firstOrFail();

    $organization->users()->attach($user->id, [
        'role_id' => $role->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $user;
}

/**
 * @return array{0: Product, 1: ProductVersion}
 */
function makeProductWithVersionForIncidents(Organization $organization, User $owner): array
{
    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Gateway Incidents',
        'slug' => 'gateway-incidents',
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

    $version = ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '2.0.0',
        'release_date' => now()->toDateString(),
        'state' => ProductVersionState::Draft,
        'support_status' => SupportStatus::Supported,
    ]);

    return [$product, $version];
}

test('owner can create incident with affected versions', function () {
    [$organization, $owner] = makeIncidentsOrgWithOwner();
    [$product, $version] = makeProductWithVersionForIncidents($organization, $owner);

    $this->actingAs($owner)
        ->post(route('products.incidents.store', $product), [
            'title' => 'Suspicious auth spikes',
            'summary' => 'Elevated failed logins from unusual geography.',
            'status' => IncidentStatus::Open->value,
            'severity' => IncidentSeverity::High->value,
            'awareness_at' => '2026-07-22T09:00',
            'detected_at' => '2026-07-22T08:30',
            'owner_user_id' => $owner->id,
            'version_ids' => [$version->id],
        ])
        ->assertRedirect();

    $incident = ProductIncident::query()
        ->where('product_id', $product->id)
        ->where('title', 'Suspicious auth spikes')
        ->firstOrFail();

    expect($incident->organization_id)->toBe($organization->id)
        ->and($incident->status)->toBe(IncidentStatus::Open)
        ->and($incident->severity)->toBe(IncidentSeverity::High)
        ->and($incident->owner_user_id)->toBe($owner->id)
        ->and($incident->versions()->pluck('product_versions.id')->all())->toContain($version->id);
});

test('read-only user can view incidents but cannot create', function () {
    [$organization, $owner] = makeIncidentsOrgWithOwner();
    [$product] = makeProductWithVersionForIncidents($organization, $owner);
    $viewer = makeIncidentsOrgReadOnly($organization);

    $this->actingAs($viewer)
        ->get(route('products.incidents.index', $product))
        ->assertOk();

    $this->actingAs($viewer)
        ->post(route('products.incidents.store', $product), [
            'title' => 'Forbidden',
            'status' => IncidentStatus::Open->value,
            'severity' => IncidentSeverity::Low->value,
        ])
        ->assertForbidden();
});

test('owner can update incident status and timestamps', function () {
    [$organization, $owner] = makeIncidentsOrgWithOwner();
    [$product, $version] = makeProductWithVersionForIncidents($organization, $owner);

    $incident = ProductIncident::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'API abuse pattern',
        'status' => IncidentStatus::Open,
        'severity' => IncidentSeverity::Medium,
        'awareness_at' => now()->subHours(2),
    ]);

    $this->actingAs($owner)
        ->put(route('products.incidents.update', [$product, $incident]), [
            'title' => 'API abuse pattern',
            'status' => IncidentStatus::Investigating->value,
            'severity' => IncidentSeverity::High->value,
            'summary' => 'Rate-limit bypass suspected.',
            'owner_user_id' => $owner->id,
            'awareness_at' => now()->subHours(2)->format('Y-m-d\TH:i'),
            'classified_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'root_cause' => 'Missing WAF rule',
            'version_ids' => [$version->id],
        ])
        ->assertRedirect();

    $fresh = $incident->fresh();
    expect($fresh->status)->toBe(IncidentStatus::Investigating)
        ->and($fresh->severity)->toBe(IncidentSeverity::High)
        ->and($fresh->owner_user_id)->toBe($owner->id)
        ->and($fresh->root_cause)->toBe('Missing WAF rule')
        ->and($fresh->versions()->pluck('product_versions.id')->all())->toContain($version->id);
});

test('internal api lists incidents for product', function () {
    [$organization, $owner] = makeIncidentsOrgWithOwner();
    [$product] = makeProductWithVersionForIncidents($organization, $owner);

    ProductIncident::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Credential stuffing wave',
        'status' => IncidentStatus::Contained,
        'severity' => IncidentSeverity::Critical,
        'awareness_at' => now()->subDay(),
        'detected_at' => now()->subDay()->subHour(),
    ]);

    $this->actingAs($owner)
        ->getJson(route('internal.products.incidents.index', $product))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.title', 'Credential stuffing wave')
        ->assertJsonPath('data.0.status', IncidentStatus::Contained->value)
        ->assertJsonPath('data.0.severity', IncidentSeverity::Critical->value);
});

test('owner can delete incident', function () {
    [$organization, $owner] = makeIncidentsOrgWithOwner();
    [$product] = makeProductWithVersionForIncidents($organization, $owner);

    $incident = ProductIncident::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Disposable incident',
        'status' => IncidentStatus::Cancelled,
        'severity' => IncidentSeverity::Low,
    ]);

    $this->actingAs($owner)
        ->delete(route('products.incidents.destroy', [$product, $incident]))
        ->assertRedirect(route('products.incidents.index', $product));

    expect(ProductIncident::query()->whereKey($incident->id)->exists())->toBeFalse();
});

test('incident edit page is available for owner', function () {
    [$organization, $owner] = makeIncidentsOrgWithOwner();
    [$product] = makeProductWithVersionForIncidents($organization, $owner);

    $incident = ProductIncident::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Edit page incident',
        'status' => IncidentStatus::Open,
        'severity' => IncidentSeverity::Medium,
        'awareness_at' => now()->subHours(3),
        'detected_at' => now()->subHours(4),
    ]);

    $this->actingAs($owner)
        ->get(route('products.incidents.edit', [$product, $incident]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/incidents/Edit')
            ->where('incident.id', $incident->id)
            ->where('canManage', true)
            ->has('incident.timeline_events', 0)
            ->where('incident.awareness_at', $incident->awareness_at?->format('Y-m-d\TH:i')));
});

test('owner can append timeline event', function () {
    [$organization, $owner] = makeIncidentsOrgWithOwner();
    [$product] = makeProductWithVersionForIncidents($organization, $owner);

    $incident = ProductIncident::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Timeline append incident',
        'status' => IncidentStatus::Investigating,
        'severity' => IncidentSeverity::High,
    ]);

    $occurredAt = '2026-07-22T14:30';

    $this->actingAs($owner)
        ->post(route('products.incidents.timeline.store', [$product, $incident]), [
            'occurred_at' => $occurredAt,
            'label' => 'Customer report',
            'notes' => 'Support ticket #441 received.',
        ])
        ->assertRedirect(route('products.incidents.edit', [$product, $incident]));

    $event = $incident->timelineEvents()->first();

    expect($event)->not->toBeNull()
        ->and($event->label)->toBe('Customer report')
        ->and($event->notes)->toBe('Support ticket #441 received.')
        ->and($event->created_by)->toBe($owner->id)
        ->and($event->occurred_at->format('Y-m-d\TH:i'))->toBe($occurredAt);

    $this->actingAs($owner)
        ->get(route('products.incidents.edit', [$product, $incident]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/incidents/Edit')
            ->has('incident.timeline_events', 1)
            ->where('incident.timeline_events.0.label', 'Customer report')
            ->where('incident.timeline_events.0.created_by', $owner->name));
});

test('read-only user cannot append timeline event', function () {
    [$organization, $owner] = makeIncidentsOrgWithOwner();
    [$product] = makeProductWithVersionForIncidents($organization, $owner);
    $viewer = makeIncidentsOrgReadOnly($organization);

    $incident = ProductIncident::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Viewer timeline block',
        'status' => IncidentStatus::Open,
        'severity' => IncidentSeverity::Low,
    ]);

    $this->actingAs($viewer)
        ->post(route('products.incidents.timeline.store', [$product, $incident]), [
            'occurred_at' => '2026-07-22T10:00',
            'label' => 'Forbidden event',
        ])
        ->assertForbidden();

    expect($incident->timelineEvents()->count())->toBe(0);
});
