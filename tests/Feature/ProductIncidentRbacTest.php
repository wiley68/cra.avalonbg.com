<?php

use App\Enums\ClassificationStatus;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ProductVersionState;
use App\Enums\ScopeStatus;
use App\Enums\SupportStatus;
use App\Enums\VulnerabilityBusinessSeverity;
use App\Enums\VulnerabilityDiscoverySource;
use App\Enums\VulnerabilityExploitationStatus;
use App\Enums\VulnerabilityStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductIncident;
use App\Models\ProductVersion;
use App\Models\ProductVulnerability;
use App\Models\Role;
use App\Models\User;
use App\Support\Translations;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     organization: Organization,
 *     owner: User,
 *     viewer: User,
 *     product: Product,
 *     incident: ProductIncident,
 *     vulnerability: ProductVulnerability
 * }
 */
function makeIncidentRbacFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Incident RBAC Org',
        'slug' => 'incident-rbac-org-' . uniqid(),
        'is_active' => true,
    ]);

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $viewer = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $ownerRole = Role::query()->where('slug', 'organization_owner')->firstOrFail();
    $viewerRole = Role::query()->where('slug', 'read_only')->firstOrFail();

    $organization->users()->attach($owner->id, [
        'role_id' => $ownerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $organization->users()->attach($viewer->id, [
        'role_id' => $viewerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Incident RBAC Product',
        'slug' => 'incident-rbac-product-' . uniqid(),
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

    ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '1.0.0',
        'state' => ProductVersionState::Draft,
        'support_status' => SupportStatus::Supported,
    ]);

    $incident = ProductIncident::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'RBAC incident',
        'status' => IncidentStatus::Open,
        'severity' => IncidentSeverity::Medium,
        'summary' => 'Baseline incident for viewer checks.',
    ]);

    $vulnerability = ProductVulnerability::query()->create([
        'product_id' => $product->id,
        'title' => 'RBAC vulnerability',
        'discovery_source' => VulnerabilityDiscoverySource::InternalDiscovery,
        'status' => VulnerabilityStatus::Reported,
        'business_severity' => VulnerabilityBusinessSeverity::Low,
        'exploitation_status' => VulnerabilityExploitationStatus::Unknown,
    ]);

    return compact('organization', 'owner', 'viewer', 'product', 'incident', 'vulnerability');
}

test('incident toast and label translations exist in en and bg', function () {
    $keys = [
        'products.incidents.index_title',
        'products.incidents.create_title',
        'products.incidents.edit_title',
        'products.incidents.created',
        'products.incidents.updated',
        'products.incidents.deleted',
        'products.incidents.timeline_added',
        'products.incidents.vulnerability_linked',
        'products.incidents.vulnerability_unlinked',
        'products.incidents.vulnerability_created',
        'products.incidents.statuses.open',
        'products.incidents.severities.critical',
        'products.incidents_link',
        'products.modules.incidents.description',
        'products.tasks.subject_types.incident',
        'audit_logs.event_types.incident_created',
        'audit_logs.event_types.incident_timeline_event_added',
    ];

    foreach ($keys as $key) {
        $en = Translations::get($key, locale: 'en');
        $bg = Translations::get($key, locale: 'bg');

        expect($en)->not->toBe($key)
            ->and($en)->not->toBe('')
            ->and($bg)->not->toBe($key)
            ->and($bg)->not->toBe('')
            ->and($bg)->not->toBe($en);
    }
});

test('owner can open index and create pages with manage access', function () {
    ['owner' => $owner, 'product' => $product] = makeIncidentRbacFixture();

    $this->actingAs($owner)
        ->get(route('products.incidents.index', $product))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/incidents/Index')
            ->where('canManage', true));

    $this->actingAs($owner)
        ->get(route('products.incidents.create', $product))
        ->assertOk()
        ->assertInertia(fn($page) => $page->component('products/incidents/Create'));
});

test('viewer can view index and edit but cannot manage incidents', function () {
    [
        'viewer' => $viewer,
        'product' => $product,
        'incident' => $incident,
        'vulnerability' => $vulnerability,
    ] = makeIncidentRbacFixture();

    $this->actingAs($viewer)
        ->get(route('products.incidents.index', $product))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/incidents/Index')
            ->where('canManage', false));

    $this->actingAs($viewer)
        ->get(route('products.incidents.edit', [$product, $incident]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/incidents/Edit')
            ->where('canManage', false)
            ->where('incident.id', $incident->id));

    $this->actingAs($viewer)
        ->getJson(route('internal.products.incidents.index', $product))
        ->assertOk()
        ->assertJsonPath('total', 1);

    $this->actingAs($viewer)
        ->get(route('products.incidents.create', $product))
        ->assertForbidden();

    $this->actingAs($viewer)
        ->post(route('products.incidents.store', $product), [
            'title' => 'Forbidden create',
            'status' => IncidentStatus::Open->value,
            'severity' => IncidentSeverity::Low->value,
        ])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->put(route('products.incidents.update', [$product, $incident]), [
            'title' => 'Hacked title',
            'status' => IncidentStatus::Contained->value,
            'severity' => IncidentSeverity::Critical->value,
        ])
        ->assertForbidden();

    expect($incident->fresh()->title)->toBe('RBAC incident')
        ->and($incident->fresh()->status)->toBe(IncidentStatus::Open);

    $this->actingAs($viewer)
        ->delete(route('products.incidents.destroy', [$product, $incident]))
        ->assertForbidden();

    expect(ProductIncident::query()->whereKey($incident->id)->exists())->toBeTrue();

    $this->actingAs($viewer)
        ->post(route('products.incidents.timeline.store', [$product, $incident]), [
            'occurred_at' => '2026-07-22T10:00',
            'label' => 'Forbidden timeline',
        ])
        ->assertForbidden();

    expect($incident->timelineEvents()->count())->toBe(0);

    $this->actingAs($viewer)
        ->post(route('products.incidents.link-vulnerability', [$product, $incident]), [
            'product_vulnerability_id' => $vulnerability->id,
        ])
        ->assertForbidden();

    expect($incident->fresh()->product_vulnerability_id)->toBeNull();

    $incident->update(['product_vulnerability_id' => $vulnerability->id]);

    $this->actingAs($viewer)
        ->delete(route('products.incidents.unlink-vulnerability', [$product, $incident]))
        ->assertForbidden();

    expect($incident->fresh()->product_vulnerability_id)->toBe($vulnerability->id);

    $this->actingAs($viewer)
        ->post(route('products.incidents.create-vulnerability', [$product, $incident]))
        ->assertForbidden();
});
