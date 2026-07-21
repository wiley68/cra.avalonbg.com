<?php

use App\Enums\AuditEventType;
use App\Enums\AuditorReviewPackageStatus;
use App\Enums\ClassificationStatus;
use App\Enums\EvidenceConfidentiality;
use App\Enums\EvidenceFreshnessStatus;
use App\Enums\EvidenceType;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Models\AuditLog;
use App\Models\AuditorReviewPackage;
use App\Models\Evidence;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User, product: Product, evidence: Evidence}
 */
function makeAuditorOrgWithOwner(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Auditor Org',
        'slug' => 'auditor-org',
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

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Auditor Product',
        'slug' => 'auditor-product',
        'manufacturer' => 'Acme',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    $evidence = Evidence::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'type' => EvidenceType::Document,
        'title' => 'SBOM snapshot',
        'confidentiality' => EvidenceConfidentiality::Internal,
        'freshness_status' => EvidenceFreshnessStatus::Current,
        'uploaded_by' => $owner->id,
    ]);

    return compact('organization', 'owner', 'product', 'evidence');
}

function makeAuditorOrgViewer(Organization $organization): User
{
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

    return $viewer;
}

test('owner can view auditor packages index', function () {
    ['owner' => $owner] = makeAuditorOrgWithOwner();

    $this->actingAs($owner)
        ->get(route('auditor.index'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('auditor/Index')
            ->where('canManage', true));
});

test('owner can create package with evidence and audit is recorded', function () {
    ['organization' => $organization, 'owner' => $owner, 'product' => $product, 'evidence' => $evidence] = makeAuditorOrgWithOwner();

    $this->actingAs($owner)
        ->post(route('auditor.packages.store'), [
            'product_id' => $product->id,
            'title' => 'CRA readiness Q3',
            'notes' => 'Focus on Annex I',
            'evidence_ids' => [$evidence->id],
        ])
        ->assertRedirect();

    $package = AuditorReviewPackage::query()->first();

    expect($package)->not->toBeNull()
        ->and($package->organization_id)->toBe($organization->id)
        ->and($package->product_id)->toBe($product->id)
        ->and($package->status)->toBe(AuditorReviewPackageStatus::Draft)
        ->and($package->title)->toBe('CRA readiness Q3')
        ->and($package->created_by)->toBe($owner->id)
        ->and($package->evidence()->pluck('evidence.id')->all())->toBe([$evidence->id]);

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::AuditorPackageCreated->value)
        ->exists())->toBeTrue();
});

test('owner can update draft package and sync evidence', function () {
    ['owner' => $owner, 'product' => $product, 'evidence' => $evidence] = makeAuditorOrgWithOwner();

    $secondEvidence = Evidence::query()->create([
        'organization_id' => $product->organization_id,
        'product_id' => $product->id,
        'type' => EvidenceType::Document,
        'title' => 'Risk assessment',
        'confidentiality' => EvidenceConfidentiality::Internal,
        'freshness_status' => EvidenceFreshnessStatus::Current,
        'uploaded_by' => $owner->id,
    ]);

    $package = AuditorReviewPackage::query()->create([
        'organization_id' => $product->organization_id,
        'product_id' => $product->id,
        'title' => 'Draft package',
        'status' => AuditorReviewPackageStatus::Draft,
        'created_by' => $owner->id,
        'notes' => 'Old notes',
    ]);
    $package->evidence()->sync([$evidence->id]);

    $this->actingAs($owner)
        ->put(route('auditor.packages.update', $package), [
            'title' => 'Updated package',
            'notes' => 'New scope',
            'evidence_ids' => [$secondEvidence->id],
        ])
        ->assertRedirect(route('auditor.packages.edit', $package));

    $package->refresh();

    expect($package->title)->toBe('Updated package')
        ->and($package->notes)->toBe('New scope')
        ->and($package->evidence()->pluck('evidence.id')->all())->toBe([$secondEvidence->id]);

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::AuditorPackageUpdated->value)
        ->exists())->toBeTrue();
});

test('owner can share then close package', function () {
    ['owner' => $owner, 'product' => $product] = makeAuditorOrgWithOwner();

    $package = AuditorReviewPackage::query()->create([
        'organization_id' => $product->organization_id,
        'product_id' => $product->id,
        'title' => 'Shareable package',
        'status' => AuditorReviewPackageStatus::Draft,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->post(route('auditor.packages.share', $package))
        ->assertRedirect(route('auditor.packages.edit', $package));

    $package->refresh();

    expect($package->status)->toBe(AuditorReviewPackageStatus::Shared)
        ->and($package->shared_at)->not->toBeNull();

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::AuditorPackageShared->value)
        ->exists())->toBeTrue();

    $this->actingAs($owner)
        ->post(route('auditor.packages.close', $package))
        ->assertRedirect(route('auditor.packages.edit', $package));

    $package->refresh();

    expect($package->status)->toBe(AuditorReviewPackageStatus::Closed)
        ->and($package->closed_at)->not->toBeNull();

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::AuditorPackageClosed->value)
        ->exists())->toBeTrue();
});

test('owner can delete draft package but not shared', function () {
    ['owner' => $owner, 'product' => $product] = makeAuditorOrgWithOwner();

    $draft = AuditorReviewPackage::query()->create([
        'organization_id' => $product->organization_id,
        'product_id' => $product->id,
        'title' => 'Delete me',
        'status' => AuditorReviewPackageStatus::Draft,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->delete(route('auditor.packages.destroy', $draft))
        ->assertRedirect(route('auditor.index'));

    expect(AuditorReviewPackage::query()->find($draft->id))->toBeNull();

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::AuditorPackageDeleted->value)
        ->exists())->toBeTrue();

    $shared = AuditorReviewPackage::query()->create([
        'organization_id' => $product->organization_id,
        'product_id' => $product->id,
        'title' => 'Shared package',
        'status' => AuditorReviewPackageStatus::Shared,
        'shared_at' => now(),
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->delete(route('auditor.packages.destroy', $shared))
        ->assertForbidden();

    expect(AuditorReviewPackage::query()->find($shared->id))->not->toBeNull();
});

test('shared package cannot be updated', function () {
    ['owner' => $owner, 'product' => $product] = makeAuditorOrgWithOwner();

    $package = AuditorReviewPackage::query()->create([
        'organization_id' => $product->organization_id,
        'product_id' => $product->id,
        'title' => 'Locked package',
        'status' => AuditorReviewPackageStatus::Shared,
        'shared_at' => now(),
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->from(route('auditor.packages.edit', $package))
        ->put(route('auditor.packages.update', $package), [
            'title' => 'Should fail',
            'notes' => null,
            'evidence_ids' => [],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('status');

    expect($package->fresh()->title)->toBe('Locked package');
});

test('viewer can list packages but cannot manage', function () {
    ['organization' => $organization, 'owner' => $owner, 'product' => $product] = makeAuditorOrgWithOwner();
    $viewer = makeAuditorOrgViewer($organization);

    $package = AuditorReviewPackage::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Visible package',
        'status' => AuditorReviewPackageStatus::Draft,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($viewer)
        ->get(route('auditor.index'))
        ->assertOk()
        ->assertInertia(fn($page) => $page->where('canManage', false));

    $this->actingAs($viewer)
        ->get(route('auditor.packages.edit', $package))
        ->assertOk();

    $this->actingAs($viewer)
        ->post(route('auditor.packages.store'), [
            'product_id' => $product->id,
            'title' => 'Forbidden',
            'notes' => null,
            'evidence_ids' => [],
        ])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->put(route('auditor.packages.update', $package), [
            'title' => 'Forbidden update',
            'notes' => null,
            'evidence_ids' => [],
        ])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->post(route('auditor.packages.share', $package))
        ->assertForbidden();

    $this->actingAs($viewer)
        ->post(route('auditor.packages.close', $package))
        ->assertForbidden();

    $this->actingAs($viewer)
        ->delete(route('auditor.packages.destroy', $package))
        ->assertForbidden();
});

test('owner and viewer can open read-only review with passport readiness and evidence', function () {
    ['organization' => $organization, 'owner' => $owner, 'product' => $product, 'evidence' => $evidence] = makeAuditorOrgWithOwner();
    $viewer = makeAuditorOrgViewer($organization);

    $package = AuditorReviewPackage::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Review package',
        'status' => AuditorReviewPackageStatus::Shared,
        'shared_at' => now(),
        'created_by' => $owner->id,
        'notes' => 'Focus on Annex I',
    ]);
    $package->evidence()->sync([$evidence->id]);

    $this->actingAs($owner)
        ->get(route('auditor.packages.show', $package))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('auditor/Show')
            ->where('package.title', 'Review package')
            ->where('package.notes', 'Focus on Annex I')
            ->where('package.evidence.0.id', $evidence->id)
            ->where('product.id', $product->id)
            ->where('product.name', $product->name)
            ->has('report.sections')
            ->has('report.gaps')
            ->where('canManage', true));

    $this->actingAs($viewer)
        ->get(route('auditor.packages.show', $package))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('auditor/Show')
            ->where('canManage', false)
            ->where('package.evidence.0.title', 'SBOM snapshot'));
});

test('internal api lists packages for owner', function () {
    ['owner' => $owner, 'product' => $product] = makeAuditorOrgWithOwner();

    AuditorReviewPackage::query()->create([
        'organization_id' => $product->organization_id,
        'product_id' => $product->id,
        'title' => 'API package',
        'status' => AuditorReviewPackageStatus::Draft,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->getJson(route('internal.auditor.packages.index'))
        ->assertOk()
        ->assertJsonPath('data.0.title', 'API package')
        ->assertJsonPath('data.0.status', 'draft');
});
