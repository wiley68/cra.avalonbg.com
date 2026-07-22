<?php

use App\Enums\AuditEventType;
use App\Enums\AuditorFindingSeverity;
use App\Enums\AuditorFindingStatus;
use App\Enums\AuditorReviewPackageStatus;
use App\Enums\ClassificationStatus;
use App\Enums\EvidenceConfidentiality;
use App\Enums\EvidenceFreshnessStatus;
use App\Enums\EvidenceType;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\AuditLog;
use App\Models\AuditorFinding;
use App\Models\AuditorReviewPackage;
use App\Models\Evidence;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User, auditor: User, product: Product, package: AuditorReviewPackage}
 */
function makeSharedPackageWithAuditor(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Findings Org',
        'slug' => 'findings-org',
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
        'name' => 'Findings Product',
        'slug' => 'findings-product',
        'manufacturer' => 'Acme',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    Evidence::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'type' => EvidenceType::Document,
        'title' => 'Policy PDF',
        'confidentiality' => EvidenceConfidentiality::Internal,
        'freshness_status' => EvidenceFreshnessStatus::Current,
        'uploaded_by' => $owner->id,
    ]);

    $package = AuditorReviewPackage::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Shared review',
        'status' => AuditorReviewPackageStatus::Shared,
        'shared_at' => now(),
        'created_by' => $owner->id,
        'notes' => 'Check Annex I',
    ]);

    return compact('organization', 'owner', 'auditor', 'product', 'package');
}

function makeFindingsViewer(Organization $organization): User
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

test('auditor can create finding on shared package and audit is recorded', function () {
    ['owner' => $owner, 'auditor' => $auditor, 'package' => $package] = makeSharedPackageWithAuditor();

    $this->actingAs($auditor)
        ->post(route('auditor.packages.findings.store', $package), [
            'title' => 'Missing SBOM evidence',
            'body' => 'No SBOM attached for the reviewed version.',
            'severity' => AuditorFindingSeverity::Major->value,
        ])
        ->assertRedirect(route('auditor.packages.show', $package));

    $finding = AuditorFinding::query()->first();

    expect($finding)->not->toBeNull()
        ->and($finding->package_id)->toBe($package->id)
        ->and($finding->title)->toBe('Missing SBOM evidence')
        ->and($finding->severity)->toBe(AuditorFindingSeverity::Major)
        ->and($finding->status)->toBe(AuditorFindingStatus::Open)
        ->and($finding->created_by)->toBe($auditor->id);

    $task = Task::query()
        ->where('subject_type', AuditorFinding::class)
        ->where('subject_id', $finding->id)
        ->first();

    expect($task)->not->toBeNull()
        ->and($task->product_id)->toBe($package->product_id)
        ->and($task->status)->toBe(TaskStatus::Open)
        ->and($task->priority)->toBe(TaskPriority::High)
        ->and($task->created_by)->toBe($auditor->id);

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::AuditorFindingCreated->value)
        ->exists())->toBeTrue();

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::TaskCreated->value)
        ->exists())->toBeTrue();

    $this->actingAs($owner)
        ->post(route('auditor.packages.findings.store', $package), [
            'title' => 'Owner cannot create',
            'body' => 'Nope',
            'severity' => AuditorFindingSeverity::Info->value,
        ])
        ->assertForbidden();
});

test('auditor can update and delete open finding content', function () {
    ['auditor' => $auditor, 'package' => $package] = makeSharedPackageWithAuditor();

    $finding = AuditorFinding::query()->create([
        'package_id' => $package->id,
        'title' => 'Draft finding',
        'body' => 'Initial body',
        'severity' => AuditorFindingSeverity::Minor,
        'status' => AuditorFindingStatus::Open,
        'created_by' => $auditor->id,
    ]);

    $this->actingAs($auditor)
        ->put(route('auditor.packages.findings.update', [$package, $finding]), [
            'title' => 'Updated finding',
            'body' => 'Updated body',
            'severity' => AuditorFindingSeverity::Critical->value,
        ])
        ->assertRedirect(route('auditor.packages.show', $package));

    $finding->refresh();

    expect($finding->title)->toBe('Updated finding')
        ->and($finding->body)->toBe('Updated body')
        ->and($finding->severity)->toBe(AuditorFindingSeverity::Critical);

    $this->actingAs($auditor)
        ->delete(route('auditor.packages.findings.destroy', [$package, $finding]))
        ->assertRedirect(route('auditor.packages.show', $package));

    expect(AuditorFinding::query()->find($finding->id))->toBeNull();
});

test('owner can update remediation status but auditor cannot', function () {
    ['owner' => $owner, 'auditor' => $auditor, 'package' => $package] = makeSharedPackageWithAuditor();

    $finding = AuditorFinding::query()->create([
        'package_id' => $package->id,
        'title' => 'Gap',
        'body' => 'Needs fix',
        'severity' => AuditorFindingSeverity::Major,
        'status' => AuditorFindingStatus::Open,
        'created_by' => $auditor->id,
    ]);

    $this->actingAs($auditor)
        ->put(route('auditor.packages.findings.status', [$package, $finding]), [
            'status' => AuditorFindingStatus::Remediated->value,
        ])
        ->assertForbidden();

    $this->actingAs($owner)
        ->put(route('auditor.packages.findings.status', [$package, $finding]), [
            'status' => AuditorFindingStatus::Remediated->value,
        ])
        ->assertRedirect(route('auditor.packages.show', $package));

    $finding->refresh();

    expect($finding->status)->toBe(AuditorFindingStatus::Remediated)
        ->and($finding->remediated_at)->not->toBeNull();

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::AuditorFindingStatusUpdated->value)
        ->exists())->toBeTrue();

    $this->actingAs($owner)
        ->put(route('auditor.packages.findings.status', [$package, $finding]), [
            'status' => AuditorFindingStatus::Accepted->value,
        ])
        ->assertRedirect();

    expect($finding->fresh()->remediated_at)->toBeNull()
        ->and($finding->fresh()->status)->toBe(AuditorFindingStatus::Accepted);
});

test('remediating finding completes open remediation task and deleting cancels it', function () {
    ['owner' => $owner, 'auditor' => $auditor, 'package' => $package] = makeSharedPackageWithAuditor();

    $this->actingAs($auditor)
        ->post(route('auditor.packages.findings.store', $package), [
            'title' => 'Needs task lifecycle',
            'body' => 'Track me',
            'severity' => AuditorFindingSeverity::Minor->value,
        ])
        ->assertRedirect();

    $finding = AuditorFinding::query()->where('title', 'Needs task lifecycle')->firstOrFail();
    $task = Task::query()
        ->where('subject_type', AuditorFinding::class)
        ->where('subject_id', $finding->id)
        ->firstOrFail();

    expect($task->status)->toBe(TaskStatus::Open)
        ->and($task->priority)->toBe(TaskPriority::Medium);

    $this->actingAs($owner)
        ->put(route('auditor.packages.findings.status', [$package, $finding]), [
            'status' => AuditorFindingStatus::Remediated->value,
        ])
        ->assertRedirect();

    expect($task->fresh()->status)->toBe(TaskStatus::Completed);

    $this->actingAs($auditor)
        ->post(route('auditor.packages.findings.store', $package), [
            'title' => 'Will be deleted',
            'body' => 'Cancel my task',
            'severity' => AuditorFindingSeverity::Info->value,
        ])
        ->assertRedirect();

    $toDelete = AuditorFinding::query()->where('title', 'Will be deleted')->firstOrFail();
    $openTask = Task::query()
        ->where('subject_type', AuditorFinding::class)
        ->where('subject_id', $toDelete->id)
        ->firstOrFail();

    expect($openTask->status)->toBe(TaskStatus::Open)
        ->and($openTask->priority)->toBe(TaskPriority::Low);

    $this->actingAs($auditor)
        ->delete(route('auditor.packages.findings.destroy', [$package, $toDelete]))
        ->assertRedirect();

    expect(AuditorFinding::query()->find($toDelete->id))->toBeNull()
        ->and($openTask->fresh()->status)->toBe(TaskStatus::Cancelled);
});

test('viewer cannot create findings or change remediation', function () {
    ['organization' => $organization, 'auditor' => $auditor, 'package' => $package] = makeSharedPackageWithAuditor();
    $viewer = makeFindingsViewer($organization);

    $finding = AuditorFinding::query()->create([
        'package_id' => $package->id,
        'title' => 'Visible finding',
        'body' => 'Body',
        'severity' => AuditorFindingSeverity::Info,
        'status' => AuditorFindingStatus::Open,
        'created_by' => $auditor->id,
    ]);

    $this->actingAs($viewer)
        ->get(route('auditor.packages.show', $package))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->where('canCreateFindings', false)
            ->where('canManageRemediation', false)
            ->where('findings.0.title', 'Visible finding'));

    $this->actingAs($viewer)
        ->post(route('auditor.packages.findings.store', $package), [
            'title' => 'Forbidden',
            'body' => 'Nope',
            'severity' => AuditorFindingSeverity::Info->value,
        ])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->put(route('auditor.packages.findings.status', [$package, $finding]), [
            'status' => AuditorFindingStatus::Accepted->value,
        ])
        ->assertForbidden();
});

test('findings cannot be created on draft packages', function () {
    ['organization' => $organization, 'owner' => $owner, 'auditor' => $auditor, 'product' => $product] = makeSharedPackageWithAuditor();

    $draft = AuditorReviewPackage::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Draft package',
        'status' => AuditorReviewPackageStatus::Draft,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($auditor)
        ->post(route('auditor.packages.findings.store', $draft), [
            'title' => 'Too early',
            'body' => 'Not shared yet',
            'severity' => AuditorFindingSeverity::Minor->value,
        ])
        ->assertForbidden();
});
