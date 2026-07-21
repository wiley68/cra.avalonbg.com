<?php

use App\Enums\AuditorFindingSeverity;
use App\Enums\AuditorFindingStatus;
use App\Enums\AuditorReviewPackageStatus;
use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Models\AuditorFinding;
use App\Models\AuditorReviewPackage;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     organization: Organization,
 *     owner: User,
 *     auditor: User,
 *     product: Product,
 *     draft: AuditorReviewPackage,
 *     shared: AuditorReviewPackage,
 *     closed: AuditorReviewPackage
 * }
 */
function makeAuditorRbacFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'RBAC Auditor Org',
        'slug' => 'rbac-auditor-org',
        'is_active' => true,
        'locale' => 'en',
    ]);

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $auditor = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $ownerRole = Role::query()->where('slug', 'organization_owner')->firstOrFail();
    $auditorRole = Role::query()->where('slug', 'auditor')->firstOrFail();

    $organization->users()->attach($owner->id, [
        'role_id' => $ownerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $organization->users()->attach($auditor->id, [
        'role_id' => $auditorRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'RBAC Product',
        'slug' => 'rbac-product',
        'manufacturer' => 'Acme',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    $draft = AuditorReviewPackage::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Draft package',
        'status' => AuditorReviewPackageStatus::Draft,
        'created_by' => $owner->id,
    ]);

    $shared = AuditorReviewPackage::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Shared package',
        'status' => AuditorReviewPackageStatus::Shared,
        'shared_at' => now(),
        'created_by' => $owner->id,
        'notes' => 'Shared with auditor',
    ]);

    $closed = AuditorReviewPackage::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Closed package',
        'status' => AuditorReviewPackageStatus::Closed,
        'shared_at' => now()->subDay(),
        'closed_at' => now(),
        'created_by' => $owner->id,
    ]);

    return compact('organization', 'owner', 'auditor', 'product', 'draft', 'shared', 'closed');
}

test('auditor cannot manage packages but can review shared ones', function () {
    ['owner' => $owner, 'auditor' => $auditor, 'product' => $product, 'draft' => $draft, 'shared' => $shared] = makeAuditorRbacFixture();

    $this->actingAs($auditor)
        ->get(route('auditor.index'))
        ->assertOk()
        ->assertInertia(fn($page) => $page->where('canManage', false));

    $this->actingAs($auditor)
        ->get(route('auditor.packages.create'))
        ->assertForbidden();

    $this->actingAs($auditor)
        ->post(route('auditor.packages.store'), [
            'product_id' => $product->id,
            'title' => 'Auditor created',
            'notes' => null,
            'evidence_ids' => [],
        ])
        ->assertForbidden();

    $this->actingAs($auditor)
        ->get(route('auditor.packages.show', $draft))
        ->assertForbidden();

    $this->actingAs($auditor)
        ->get(route('auditor.packages.edit', $draft))
        ->assertForbidden();

    $this->actingAs($auditor)
        ->put(route('auditor.packages.update', $draft), [
            'title' => 'Hacked',
            'notes' => null,
            'evidence_ids' => [],
        ])
        ->assertForbidden();

    $this->actingAs($auditor)
        ->post(route('auditor.packages.share', $draft))
        ->assertForbidden();

    $this->actingAs($auditor)
        ->post(route('auditor.packages.close', $shared))
        ->assertForbidden();

    $this->actingAs($auditor)
        ->delete(route('auditor.packages.destroy', $draft))
        ->assertForbidden();

    $this->actingAs($auditor)
        ->get(route('auditor.packages.show', $shared))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('auditor/Show')
            ->where('canManage', false)
            ->where('canCreateFindings', true)
            ->where('canManageFindingContent', true)
            ->where('canManageRemediation', false));

    $this->actingAs($owner)
        ->get(route('auditor.packages.show', $shared))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->where('canManage', true)
            ->where('canCreateFindings', false)
            ->where('canManageFindingContent', false)
            ->where('canManageRemediation', true));
});

test('auditor list api hides drafts while owner sees them', function () {
    ['owner' => $owner, 'auditor' => $auditor, 'draft' => $draft, 'shared' => $shared] = makeAuditorRbacFixture();

    $this->actingAs($auditor)
        ->getJson(route('internal.auditor.packages.index'))
        ->assertOk()
        ->assertJsonMissing(['title' => $draft->title])
        ->assertJsonFragment(['title' => $shared->title]);

    $this->actingAs($owner)
        ->getJson(route('internal.auditor.packages.index'))
        ->assertOk()
        ->assertJsonFragment(['title' => $draft->title])
        ->assertJsonFragment(['title' => $shared->title]);
});

test('owner cannot mutate finding content; auditor cannot remediate', function () {
    ['owner' => $owner, 'auditor' => $auditor, 'shared' => $shared] = makeAuditorRbacFixture();

    $finding = AuditorFinding::query()->create([
        'package_id' => $shared->id,
        'title' => 'Gap',
        'body' => 'Needs work',
        'severity' => AuditorFindingSeverity::Major,
        'status' => AuditorFindingStatus::Open,
        'created_by' => $auditor->id,
    ]);

    $this->actingAs($owner)
        ->put(route('auditor.packages.findings.update', [$shared, $finding]), [
            'title' => 'Owner rewrite',
            'body' => 'Nope',
            'severity' => AuditorFindingSeverity::Info->value,
        ])
        ->assertForbidden();

    $this->actingAs($owner)
        ->delete(route('auditor.packages.findings.destroy', [$shared, $finding]))
        ->assertForbidden();

    $this->actingAs($auditor)
        ->put(route('auditor.packages.findings.status', [$shared, $finding]), [
            'status' => AuditorFindingStatus::Remediated->value,
        ])
        ->assertForbidden();

    $this->actingAs($owner)
        ->put(route('auditor.packages.findings.status', [$shared, $finding]), [
            'status' => AuditorFindingStatus::Accepted->value,
        ])
        ->assertRedirect(route('auditor.packages.show', $shared));

    expect($finding->fresh()->status)->toBe(AuditorFindingStatus::Accepted);
});

test('closed package blocks finding content changes but allows owner remediation', function () {
    ['owner' => $owner, 'auditor' => $auditor, 'closed' => $closed] = makeAuditorRbacFixture();

    $finding = AuditorFinding::query()->create([
        'package_id' => $closed->id,
        'title' => 'Legacy finding',
        'body' => 'Still open when closed',
        'severity' => AuditorFindingSeverity::Minor,
        'status' => AuditorFindingStatus::Open,
        'created_by' => $auditor->id,
    ]);

    $this->actingAs($auditor)
        ->get(route('auditor.packages.show', $closed))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->where('canCreateFindings', false)
            ->where('canManageFindingContent', false)
            ->where('canManageRemediation', false));

    $this->actingAs($auditor)
        ->post(route('auditor.packages.findings.store', $closed), [
            'title' => 'Too late',
            'body' => 'Closed',
            'severity' => AuditorFindingSeverity::Info->value,
        ])
        ->assertForbidden();

    $this->actingAs($auditor)
        ->put(route('auditor.packages.findings.update', [$closed, $finding]), [
            'title' => 'Edit closed',
            'body' => 'Nope',
            'severity' => AuditorFindingSeverity::Critical->value,
        ])
        ->assertForbidden();

    $this->actingAs($auditor)
        ->delete(route('auditor.packages.findings.destroy', [$closed, $finding]))
        ->assertForbidden();

    $this->actingAs($owner)
        ->get(route('auditor.packages.show', $closed))
        ->assertOk()
        ->assertInertia(fn($page) => $page->where('canManageRemediation', true));

    $this->actingAs($owner)
        ->put(route('auditor.packages.findings.status', [$closed, $finding]), [
            'status' => AuditorFindingStatus::WontFix->value,
        ])
        ->assertRedirect(route('auditor.packages.show', $closed));

    expect($finding->fresh()->status)->toBe(AuditorFindingStatus::WontFix);
});

test('users from another organization cannot access packages', function () {
    ['shared' => $shared] = makeAuditorRbacFixture();

    test()->seed([RolePermissionSeeder::class]);

    $otherOrg = Organization::query()->create([
        'name' => 'Other Org',
        'slug' => 'other-org-rbac',
        'is_active' => true,
        'locale' => 'en',
    ]);

    $outsider = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $ownerRole = Role::query()->where('slug', 'organization_owner')->firstOrFail();
    $otherOrg->users()->attach($outsider->id, [
        'role_id' => $ownerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($outsider)
        ->get(route('auditor.packages.show', $shared))
        ->assertNotFound();

    $this->actingAs($outsider)
        ->get(route('auditor.packages.export', $shared))
        ->assertNotFound();

    $this->actingAs($outsider)
        ->post(route('auditor.packages.findings.store', $shared), [
            'title' => 'Cross org',
            'body' => 'Nope',
            'severity' => AuditorFindingSeverity::Info->value,
        ])
        ->assertForbidden();
});
