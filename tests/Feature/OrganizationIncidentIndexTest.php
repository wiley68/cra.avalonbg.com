<?php

use App\Enums\ClassificationStatus;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductIncident;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     organization: Organization,
 *     owner: User,
 *     viewer: User,
 *     outsider: User,
 *     productA: Product,
 *     productB: Product,
 *     incidentA: ProductIncident,
 *     incidentB: ProductIncident
 * }
 */
function makeOrgIncidentsIndexFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Org Incidents Index Org',
        'slug' => 'org-incidents-index-' . uniqid(),
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

    $outsider = User::factory()->create([
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

    $productA = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Alpha Gateway',
        'slug' => 'alpha-gateway-' . uniqid(),
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

    $productB = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Beta Console',
        'slug' => 'beta-console-' . uniqid(),
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
        'scope_reviewed_at' => now(),
        'scope_reviewed_by' => $owner->id,
        'classification_reviewed_at' => now(),
        'classification_reviewed_by' => $owner->id,
    ]);

    $incidentA = ProductIncident::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $productA->id,
        'title' => 'Alpha auth spike',
        'status' => IncidentStatus::Open,
        'severity' => IncidentSeverity::High,
    ]);

    $incidentB = ProductIncident::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $productB->id,
        'title' => 'Beta data exfil attempt',
        'status' => IncidentStatus::Investigating,
        'severity' => IncidentSeverity::Critical,
    ]);

    return compact(
        'organization',
        'owner',
        'viewer',
        'outsider',
        'productA',
        'productB',
        'incidentA',
        'incidentB',
    );
}

test('owner can open org-level incidents index', function () {
    ['owner' => $owner] = makeOrgIncidentsIndexFixture();

    $this->actingAs($owner)
        ->get(route('incidents.index'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('incidents/Index')
            ->where('canManage', true));
});

test('org-level incidents API returns incidents across products', function () {
    [
        'owner' => $owner,
        'productA' => $productA,
        'productB' => $productB,
        'incidentA' => $incidentA,
        'incidentB' => $incidentB,
    ] = makeOrgIncidentsIndexFixture();

    $response = $this->actingAs($owner)
        ->getJson(route('internal.incidents.index', [
            'per_page' => 50,
            'sort_by' => 'title',
            'sort_desc' => '0',
        ]))
        ->assertOk()
        ->assertJsonPath('total', 2);

    $ids = collect($response->json('data'))->pluck('id')->all();
    $productIds = collect($response->json('data'))->pluck('product_id')->all();

    expect($ids)->toContain($incidentA->id, $incidentB->id)
        ->and($productIds)->toContain($productA->id, $productB->id);

    $alpha = collect($response->json('data'))->firstWhere('id', $incidentA->id);
    expect($alpha['product_name'])->toBe('Alpha Gateway');
});

test('org-level incidents API search matches product name', function () {
    ['owner' => $owner, 'incidentB' => $incidentB] = makeOrgIncidentsIndexFixture();

    $this->actingAs($owner)
        ->getJson(route('internal.incidents.index', [
            'search' => 'Beta Console',
        ]))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.id', $incidentB->id);
});

test('viewer can list org-level incidents but cannot manage', function () {
    ['viewer' => $viewer] = makeOrgIncidentsIndexFixture();

    $this->actingAs($viewer)
        ->get(route('incidents.index'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('incidents/Index')
            ->where('canManage', false));

    $this->actingAs($viewer)
        ->getJson(route('internal.incidents.index'))
        ->assertOk()
        ->assertJsonPath('total', 2);
});

test('outsider without membership cannot open org-level incidents index', function () {
    ['outsider' => $outsider] = makeOrgIncidentsIndexFixture();

    $this->actingAs($outsider)
        ->get(route('incidents.index'))
        ->assertForbidden();
});
