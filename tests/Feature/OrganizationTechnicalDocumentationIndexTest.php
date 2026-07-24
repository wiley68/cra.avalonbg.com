<?php

use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\TechnicalDocumentationStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Role;
use App\Models\TechnicalDocumentationPackage;
use App\Models\User;
use App\Services\TechnicalDocumentationService;
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
 *     packageA: TechnicalDocumentationPackage,
 *     packageB: TechnicalDocumentationPackage
 * }
 */
function makeOrgTechDocIndexFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Org Tech Doc Index Org',
        'slug' => 'org-tech-doc-index-' . uniqid(),
        'is_active' => true,
        'locale' => 'en',
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

    $packages = app(TechnicalDocumentationService::class);

    $packageA = $packages->create($productA, [
        'title' => 'Alpha technical documentation',
        'version_label' => '1.0',
        'locale' => 'en',
        'notes' => null,
        'inherit_from_previous' => false,
        'product_version_id' => null,
        'user_security_instruction_id' => null,
        'sdl_run_id' => null,
    ], $owner);

    $packageB = $packages->create($productB, [
        'title' => 'Beta technical documentation',
        'version_label' => '1.0',
        'locale' => 'en',
        'notes' => null,
        'inherit_from_previous' => false,
        'product_version_id' => null,
        'user_security_instruction_id' => null,
        'sdl_run_id' => null,
    ], $owner);

    return compact(
        'organization',
        'owner',
        'viewer',
        'outsider',
        'productA',
        'productB',
        'packageA',
        'packageB',
    );
}

test('owner can open org-level technical documentation index', function () {
    ['owner' => $owner] = makeOrgTechDocIndexFixture();

    $this->actingAs($owner)
        ->get(route('technical-documentation.index'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('technical-documentation/Index')
            ->where('canManage', true));
});

test('org-level technical documentation API returns packages across products', function () {
    [
        'owner' => $owner,
        'productA' => $productA,
        'productB' => $productB,
        'packageA' => $packageA,
        'packageB' => $packageB,
    ] = makeOrgTechDocIndexFixture();

    $response = $this->actingAs($owner)
        ->getJson(route('internal.technical-documentation.index', [
            'per_page' => 50,
            'sort_by' => 'title',
            'sort_desc' => '0',
        ]))
        ->assertOk()
        ->assertJsonPath('total', 2);

    $ids = collect($response->json('data'))->pluck('id')->all();
    $productIds = collect($response->json('data'))->pluck('product_id')->all();

    expect($ids)->toContain($packageA->id, $packageB->id)
        ->and($productIds)->toContain($productA->id, $productB->id);

    $alpha = collect($response->json('data'))->firstWhere('id', $packageA->id);
    expect($alpha['product_name'])->toBe('Alpha Gateway')
        ->and($alpha['status'])->toBe(TechnicalDocumentationStatus::Draft->value);
});

test('org-level technical documentation API search matches product name', function () {
    ['owner' => $owner, 'packageB' => $packageB] = makeOrgTechDocIndexFixture();

    $this->actingAs($owner)
        ->getJson(route('internal.technical-documentation.index', [
            'search' => 'Beta Console',
        ]))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.id', $packageB->id);
});

test('viewer can list org-level technical documentation but cannot manage', function () {
    ['viewer' => $viewer] = makeOrgTechDocIndexFixture();

    $this->actingAs($viewer)
        ->get(route('technical-documentation.index'))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('technical-documentation/Index')
            ->where('canManage', false));

    $this->actingAs($viewer)
        ->getJson(route('internal.technical-documentation.index'))
        ->assertOk()
        ->assertJsonPath('total', 2);
});

test('outsider without membership cannot open org-level technical documentation index', function () {
    ['outsider' => $outsider] = makeOrgTechDocIndexFixture();

    $this->actingAs($outsider)
        ->get(route('technical-documentation.index'))
        ->assertForbidden();
});
