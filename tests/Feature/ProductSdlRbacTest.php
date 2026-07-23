<?php

use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\PermissionSlug;
use App\Enums\ProductType;
use App\Enums\ProductVersionState;
use App\Enums\RoleScope;
use App\Enums\ScopeStatus;
use App\Enums\SdlRunStatus;
use App\Enums\SdlStage;
use App\Enums\SdlStageStatus;
use App\Enums\SupportStatus;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVersion;
use App\Models\Role;
use App\Models\SdlRun;
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
 *     run: SdlRun
 * }
 */
function makeSdlRbacFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'SDL RBAC Org',
        'slug' => 'sdl-rbac-org-' . uniqid(),
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
        'name' => 'SDL RBAC Product',
        'slug' => 'sdl-rbac-product-' . uniqid(),
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

    $run = SdlRun::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'RBAC SDL run',
        'status' => SdlRunStatus::Draft,
        'current_stage' => SdlStage::Requirement,
    ]);
    $run->ensureStageEntries();

    return compact('organization', 'owner', 'viewer', 'product', 'run');
}

test('sdl toast and label translations exist in en and bg', function () {
    $keys = [
        'products.sdl.index_title',
        'products.sdl.create_title',
        'products.sdl.edit_title',
        'products.sdl.created',
        'products.sdl.updated',
        'products.sdl.deleted',
        'products.sdl.stage_updated',
        'products.sdl.approve',
        'products.sdl.approved',
        'products.sdl.approval_revoked',
        'products.sdl.statuses.draft',
        'products.sdl.stages.release_approval',
        'products.sdl.stage_statuses.done',
        'products.modules.sdl.description',
        'audit_logs.event_types.sdl_run_created',
        'audit_logs.event_types.sdl_run_updated',
        'audit_logs.event_types.sdl_stage_updated',
        'audit_logs.event_types.sdl_run_approved',
        'audit_logs.event_types.sdl_run_approval_revoked',
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

test('owner can open sdl index and create pages with manage access', function () {
    ['owner' => $owner, 'product' => $product] = makeSdlRbacFixture();

    $this->actingAs($owner)
        ->get(route('products.sdl.index', $product))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/sdl/Index')
            ->where('canManage', true));

    $this->actingAs($owner)
        ->get(route('products.sdl.create', $product))
        ->assertOk()
        ->assertInertia(fn($page) => $page->component('products/sdl/Create'));
});

test('viewer can view sdl index and edit but cannot manage', function () {
    [
        'viewer' => $viewer,
        'product' => $product,
        'run' => $run,
    ] = makeSdlRbacFixture();

    $this->actingAs($viewer)
        ->get(route('products.sdl.index', $product))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/sdl/Index')
            ->where('canManage', false));

    $this->actingAs($viewer)
        ->get(route('products.sdl.edit', [$product, $run]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/sdl/Edit')
            ->where('canManage', false)
            ->where('run.id', $run->id));

    $this->actingAs($viewer)
        ->getJson(route('internal.products.sdl.index', $product))
        ->assertOk()
        ->assertJsonPath('total', 1);

    $this->actingAs($viewer)
        ->get(route('products.sdl.create', $product))
        ->assertForbidden();

    $this->actingAs($viewer)
        ->post(route('products.sdl.store', $product), [
            'title' => 'Forbidden create',
            'status' => SdlRunStatus::Draft->value,
            'current_stage' => SdlStage::Requirement->value,
        ])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->put(route('products.sdl.update', [$product, $run]), [
            'title' => 'Hacked title',
            'status' => SdlRunStatus::InProgress->value,
            'current_stage' => SdlStage::Design->value,
        ])
        ->assertForbidden();

    expect($run->fresh()->title)->toBe('RBAC SDL run')
        ->and($run->fresh()->status)->toBe(SdlRunStatus::Draft);

    $this->actingAs($viewer)
        ->put(route('products.sdl.stages.update', [
            'product' => $product,
            'sdlRun' => $run,
            'stage' => SdlStage::Requirement->value,
        ]), [
            'status' => SdlStageStatus::Done->value,
            'notes' => 'Forbidden',
        ])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->post(route('products.sdl.approve', [$product, $run]))
        ->assertForbidden();

    $this->actingAs($viewer)
        ->post(route('products.sdl.revoke-approval', [$product, $run]))
        ->assertForbidden();

    $this->actingAs($viewer)
        ->delete(route('products.sdl.destroy', [$product, $run]))
        ->assertForbidden();

    expect(SdlRun::query()->whereKey($run->id)->exists())->toBeTrue();
});

test('must v1 sdl access reuses products view and manage permissions', function () {
    test()->seed([RolePermissionSeeder::class]);

    $owner = Role::query()->where('slug', 'organization_owner')->with('permissions')->firstOrFail();
    $viewer = Role::query()->where('slug', 'read_only')->with('permissions')->firstOrFail();

    expect($owner->permissions->pluck('slug'))
        ->toContain(
            PermissionSlug::ProductsView->value,
            PermissionSlug::ProductsManage->value,
        )
        ->and($viewer->permissions->pluck('slug'))
        ->toContain(PermissionSlug::ProductsView->value)
        ->not->toContain(PermissionSlug::ProductsManage->value);
});

test('products view alone does not grant sdl manage', function () {
    [
        'organization' => $organization,
        'product' => $product,
        'run' => $run,
    ] = makeSdlRbacFixture();

    $user = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $role = Role::query()->create([
        'slug' => 'products-view-only-' . uniqid(),
        'name' => 'Products view only',
        'description' => 'View products without manage',
        'scope' => RoleScope::Organization,
        'is_default' => false,
    ]);

    $role->permissions()->sync(
        Permission::query()
            ->where('slug', PermissionSlug::ProductsView->value)
            ->pluck('id'),
    );

    $organization->users()->attach($user->id, [
        'role_id' => $role->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('products.sdl.index', $product))
        ->assertOk()
        ->assertInertia(fn($page) => $page->where('canManage', false));

    $this->actingAs($user)
        ->get(route('products.sdl.edit', [$product, $run]))
        ->assertOk()
        ->assertInertia(fn($page) => $page->where('canManage', false));

    $this->actingAs($user)
        ->post(route('products.sdl.store', $product), [
            'title' => 'Should fail',
            'status' => SdlRunStatus::Draft->value,
            'current_stage' => SdlStage::Requirement->value,
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->put(route('products.sdl.update', [$product, $run]), [
            'title' => 'Should fail',
            'status' => SdlRunStatus::InProgress->value,
            'current_stage' => SdlStage::Requirement->value,
        ])
        ->assertForbidden();

    expect($run->fresh()->title)->toBe('RBAC SDL run');
});
