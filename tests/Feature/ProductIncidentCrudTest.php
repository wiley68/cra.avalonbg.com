<?php

use App\Enums\AuditEventType;
use App\Enums\ClassificationStatus;
use App\Enums\CustomerCriticality;
use App\Enums\DeploymentEnvironment;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ProductVersionState;
use App\Enums\ScopeStatus;
use App\Enums\SupportStatus;
use App\Enums\TaskStatus;
use App\Enums\VulnerabilityBusinessSeverity;
use App\Enums\VulnerabilityDiscoverySource;
use App\Enums\VulnerabilityExploitationStatus;
use App\Enums\VulnerabilityStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductDeployment;
use App\Models\ProductIncident;
use App\Models\ProductVersion;
use App\Models\ProductVulnerability;
use App\Models\Role;
use App\Models\Task;
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

test('owner can attach customers and deployments to incident', function () {
    [$organization, $owner] = makeIncidentsOrgWithOwner();
    [$product, $version] = makeProductWithVersionForIncidents($organization, $owner);

    $customer = Customer::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Acme Bank',
        'criticality' => CustomerCriticality::High,
        'is_active' => true,
    ]);

    $deployment = ProductDeployment::query()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'product_id' => $product->id,
        'product_version_id' => $version->id,
        'environment' => DeploymentEnvironment::Production,
        'internet_exposure' => true,
        'custom_modifications' => false,
        'end_of_support_exception' => false,
    ]);

    $this->actingAs($owner)
        ->post(route('products.incidents.store', $product), [
            'title' => 'Customer impact incident',
            'status' => IncidentStatus::Open->value,
            'severity' => IncidentSeverity::Medium->value,
            'version_ids' => [$version->id],
            'customer_ids' => [$customer->id],
            'deployment_ids' => [$deployment->id],
        ])
        ->assertRedirect();

    $incident = ProductIncident::query()
        ->where('product_id', $product->id)
        ->where('title', 'Customer impact incident')
        ->firstOrFail();

    expect($incident->customers()->pluck('customers.id')->all())->toContain($customer->id)
        ->and($incident->deployments()->pluck('product_deployments.id')->all())->toContain($deployment->id);

    $this->actingAs($owner)
        ->put(route('products.incidents.update', [$product, $incident]), [
            'title' => 'Customer impact incident',
            'status' => IncidentStatus::Investigating->value,
            'severity' => IncidentSeverity::Medium->value,
            'version_ids' => [$version->id],
            'customer_ids' => [],
            'deployment_ids' => [$deployment->id],
        ])
        ->assertRedirect();

    expect($incident->fresh()->customers()->count())->toBe(0)
        ->and($incident->fresh()->deployments()->pluck('product_deployments.id')->all())->toContain($deployment->id);
});

test('incident rejects foreign organization customers and deployments', function () {
    [$organization, $owner] = makeIncidentsOrgWithOwner();
    [$product, $version] = makeProductWithVersionForIncidents($organization, $owner);

    $foreignOrganization = Organization::query()->create([
        'name' => 'Foreign Org',
        'slug' => 'foreign-org-incidents',
        'is_active' => true,
    ]);

    $foreignCustomer = Customer::query()->create([
        'organization_id' => $foreignOrganization->id,
        'name' => 'Foreign Customer',
        'criticality' => CustomerCriticality::Low,
        'is_active' => true,
    ]);

    $foreignProduct = Product::query()->create([
        'organization_id' => $foreignOrganization->id,
        'name' => 'Foreign Product',
        'slug' => 'foreign-product-incidents',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => false,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    $foreignDeployment = ProductDeployment::query()->create([
        'organization_id' => $foreignOrganization->id,
        'customer_id' => $foreignCustomer->id,
        'product_id' => $foreignProduct->id,
        'product_version_id' => null,
        'environment' => DeploymentEnvironment::Staging,
        'internet_exposure' => false,
        'custom_modifications' => false,
        'end_of_support_exception' => false,
    ]);

    $this->actingAs($owner)
        ->post(route('products.incidents.store', $product), [
            'title' => 'Foreign attach blocked',
            'status' => IncidentStatus::Open->value,
            'severity' => IncidentSeverity::Low->value,
            'version_ids' => [$version->id],
            'customer_ids' => [$foreignCustomer->id],
            'deployment_ids' => [$foreignDeployment->id],
        ])
        ->assertSessionHasErrors(['customer_ids.0', 'deployment_ids.0']);

    expect(ProductIncident::query()->where('title', 'Foreign attach blocked')->exists())->toBeFalse();
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

test('owner can create vulnerability from incident with incident_investigation discovery', function () {
    [$organization, $owner] = makeIncidentsOrgWithOwner();
    [$product, $version] = makeProductWithVersionForIncidents($organization, $owner);

    $incident = ProductIncident::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Auth bypass incident',
        'summary' => 'Suspicious session reuse.',
        'status' => IncidentStatus::Investigating,
        'severity' => IncidentSeverity::Critical,
        'awareness_at' => now()->subHours(5),
        'detected_at' => now()->subHours(6),
        'corrective_measures' => 'Revoke sessions',
        'owner_user_id' => $owner->id,
    ]);
    $incident->versions()->attach($version->id);

    $this->actingAs($owner)
        ->post(route('products.incidents.create-vulnerability', [$product, $incident]))
        ->assertRedirect();

    $incident->refresh();
    $vulnerability = ProductVulnerability::query()->findOrFail($incident->product_vulnerability_id);

    expect($vulnerability->title)->toBe('Auth bypass incident')
        ->and($vulnerability->discovery_source)->toBe(VulnerabilityDiscoverySource::IncidentInvestigation)
        ->and($vulnerability->business_severity)->toBe(VulnerabilityBusinessSeverity::Critical)
        ->and($vulnerability->status)->toBe(VulnerabilityStatus::Reported)
        ->and($vulnerability->owner_user_id)->toBe($owner->id)
        ->and($vulnerability->corrective_action)->toBe('Revoke sessions')
        ->and($vulnerability->affectedVersions()->pluck('product_versions.id')->all())->toContain($version->id);

    $this->actingAs($owner)
        ->get(route('products.incidents.edit', [$product, $incident]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->where('incident.linked_vulnerability.id', $vulnerability->id)
            ->where('incident.linked_vulnerability.title', 'Auth bypass incident'));
});

test('owner can link and unlink an existing vulnerability', function () {
    [$organization, $owner] = makeIncidentsOrgWithOwner();
    [$product] = makeProductWithVersionForIncidents($organization, $owner);

    $vulnerability = ProductVulnerability::query()->create([
        'product_id' => $product->id,
        'title' => 'Existing vuln',
        'discovery_source' => VulnerabilityDiscoverySource::InternalDiscovery,
        'status' => VulnerabilityStatus::Triage,
        'business_severity' => VulnerabilityBusinessSeverity::Medium,
        'exploitation_status' => VulnerabilityExploitationStatus::Unknown,
    ]);

    $incident = ProductIncident::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Linkable incident',
        'status' => IncidentStatus::Open,
        'severity' => IncidentSeverity::Medium,
    ]);

    $this->actingAs($owner)
        ->post(route('products.incidents.link-vulnerability', [$product, $incident]), [
            'product_vulnerability_id' => $vulnerability->id,
        ])
        ->assertRedirect(route('products.incidents.edit', [$product, $incident]));

    expect($incident->fresh()->product_vulnerability_id)->toBe($vulnerability->id);

    $this->actingAs($owner)
        ->delete(route('products.incidents.unlink-vulnerability', [$product, $incident]))
        ->assertRedirect(route('products.incidents.edit', [$product, $incident]));

    expect($incident->fresh()->product_vulnerability_id)->toBeNull();
});

test('read-only user cannot create vulnerability from incident', function () {
    [$organization, $owner] = makeIncidentsOrgWithOwner();
    [$product] = makeProductWithVersionForIncidents($organization, $owner);
    $viewer = makeIncidentsOrgReadOnly($organization);

    $incident = ProductIncident::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Viewer create block',
        'status' => IncidentStatus::Open,
        'severity' => IncidentSeverity::Low,
    ]);

    $this->actingAs($viewer)
        ->post(route('products.incidents.create-vulnerability', [$product, $incident]))
        ->assertForbidden();

    expect($incident->fresh()->product_vulnerability_id)->toBeNull()
        ->and(ProductVulnerability::query()->where('product_id', $product->id)->count())->toBe(0);
});

test('owner can close incident with closed_at closed_by and optional approval task', function () {
    [$organization, $owner] = makeIncidentsOrgWithOwner();
    [$product] = makeProductWithVersionForIncidents($organization, $owner);

    $incident = ProductIncident::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Ready to close',
        'status' => IncidentStatus::Contained,
        'severity' => IncidentSeverity::High,
        'awareness_at' => now()->subHour(),
        'root_cause' => 'Misconfigured auth rate limits',
        'corrective_measures' => 'Tightened WAF rules and rotated keys',
        'owner_user_id' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->post(route('products.incidents.close', [$product, $incident]), [
            'create_approval_task' => true,
        ])
        ->assertRedirect(route('products.incidents.edit', [$product, $incident]));

    $fresh = $incident->fresh();

    expect($fresh->status)->toBe(IncidentStatus::Closed)
        ->and($fresh->closed_at)->not->toBeNull()
        ->and($fresh->closed_by)->toBe($owner->id);

    $task = Task::query()
        ->where('product_id', $product->id)
        ->where('subject_type', ProductIncident::class)
        ->where('subject_id', $incident->id)
        ->first();

    expect($task)->not->toBeNull()
        ->and($task->status)->toBe(TaskStatus::Open)
        ->and($task->assignee_user_id)->toBe($owner->id);

    expect(AuditLog::query()->where('event_type', AuditEventType::IncidentClosed->value)->exists())->toBeTrue();
});

test('close requires awareness timestamp and rejects already closed incidents', function () {
    [$organization, $owner] = makeIncidentsOrgWithOwner();
    [$product] = makeProductWithVersionForIncidents($organization, $owner);

    $withoutAwareness = ProductIncident::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Missing awareness',
        'status' => IncidentStatus::Open,
        'severity' => IncidentSeverity::Low,
        'root_cause' => 'Unknown',
        'corrective_measures' => 'Pending',
    ]);

    $this->actingAs($owner)
        ->post(route('products.incidents.close', [$product, $withoutAwareness]))
        ->assertSessionHasErrors('awareness_at');

    $withoutRootCause = ProductIncident::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Missing root cause',
        'status' => IncidentStatus::Open,
        'severity' => IncidentSeverity::Low,
        'awareness_at' => now(),
        'corrective_measures' => 'Pending',
    ]);

    $this->actingAs($owner)
        ->post(route('products.incidents.close', [$product, $withoutRootCause]))
        ->assertSessionHasErrors('root_cause');

    $withoutCorrective = ProductIncident::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Missing corrective measures',
        'status' => IncidentStatus::Open,
        'severity' => IncidentSeverity::Low,
        'awareness_at' => now(),
        'root_cause' => 'Known cause',
    ]);

    $this->actingAs($owner)
        ->post(route('products.incidents.close', [$product, $withoutCorrective]))
        ->assertSessionHasErrors('corrective_measures');

    $closed = ProductIncident::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Already closed',
        'status' => IncidentStatus::Closed,
        'severity' => IncidentSeverity::Low,
        'awareness_at' => now()->subDay(),
        'root_cause' => 'Resolved',
        'corrective_measures' => 'Patched',
        'closed_at' => now()->subHour(),
        'closed_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->post(route('products.incidents.close', [$product, $closed]))
        ->assertSessionHasErrors('status');
});

test('owner can update root cause and corrective measures on edit', function () {
    [$organization, $owner] = makeIncidentsOrgWithOwner();
    [$product, $version] = makeProductWithVersionForIncidents($organization, $owner);

    $incident = ProductIncident::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Investigation fields',
        'status' => IncidentStatus::Investigating,
        'severity' => IncidentSeverity::Medium,
        'awareness_at' => now()->subHours(2),
    ]);

    $this->actingAs($owner)
        ->put(route('products.incidents.update', [$product, $incident]), [
            'title' => 'Investigation fields',
            'status' => IncidentStatus::Investigating->value,
            'severity' => IncidentSeverity::Medium->value,
            'awareness_at' => now()->subHours(2)->format('Y-m-d\TH:i'),
            'root_cause' => 'Exposed admin endpoint',
            'corrective_measures' => 'Disabled endpoint and rotated credentials',
            'lessons_learned' => 'Add authz regression tests',
            'version_ids' => [$version->id],
            'customer_ids' => [],
            'deployment_ids' => [],
        ])
        ->assertRedirect();

    $fresh = $incident->fresh();

    expect($fresh->root_cause)->toBe('Exposed admin endpoint')
        ->and($fresh->corrective_measures)->toBe('Disabled endpoint and rotated credentials')
        ->and($fresh->lessons_learned)->toBe('Add authz regression tests');
});

test('updating status to closed stamps closed_at and closed_by', function () {
    [$organization, $owner] = makeIncidentsOrgWithOwner();
    [$product, $version] = makeProductWithVersionForIncidents($organization, $owner);

    $incident = ProductIncident::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Status close stamp',
        'status' => IncidentStatus::Investigating,
        'severity' => IncidentSeverity::Medium,
        'awareness_at' => now()->subHours(3),
    ]);

    $this->actingAs($owner)
        ->put(route('products.incidents.update', [$product, $incident]), [
            'title' => 'Status close stamp',
            'status' => IncidentStatus::Closed->value,
            'severity' => IncidentSeverity::Medium->value,
            'awareness_at' => now()->subHours(3)->format('Y-m-d\TH:i'),
            'version_ids' => [$version->id],
            'customer_ids' => [],
            'deployment_ids' => [],
        ])
        ->assertRedirect();

    $fresh = $incident->fresh();

    expect($fresh->status)->toBe(IncidentStatus::Closed)
        ->and($fresh->closed_at)->not->toBeNull()
        ->and($fresh->closed_by)->toBe($owner->id);
});

test('read-only user cannot close incident', function () {
    [$organization, $owner] = makeIncidentsOrgWithOwner();
    [$product] = makeProductWithVersionForIncidents($organization, $owner);
    $viewer = makeIncidentsOrgReadOnly($organization);

    $incident = ProductIncident::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Viewer close block',
        'status' => IncidentStatus::Open,
        'severity' => IncidentSeverity::Low,
        'awareness_at' => now(),
        'root_cause' => 'n/a',
        'corrective_measures' => 'n/a',
    ]);

    $this->actingAs($viewer)
        ->post(route('products.incidents.close', [$product, $incident]))
        ->assertForbidden();

    expect($incident->fresh()->status)->toBe(IncidentStatus::Open)
        ->and($incident->fresh()->closed_at)->toBeNull();
});
