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
use App\Models\AuditLog;
use App\Models\AuditorFinding;
use App\Models\AuditorReviewPackage;
use App\Models\Evidence;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User, auditor: User, package: AuditorReviewPackage}
 */
function makeExportPackageOrg(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Export Org',
        'slug' => 'export-org',
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
        'name' => 'Export Product',
        'slug' => 'export-product',
        'manufacturer' => 'Acme',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    $package = AuditorReviewPackage::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Export review',
        'status' => AuditorReviewPackageStatus::Shared,
        'shared_at' => now(),
        'created_by' => $owner->id,
        'notes' => 'Export scope notes',
    ]);

    return compact('organization', 'owner', 'auditor', 'package');
}

test('package export downloads zip with pdf and evidence file and writes audit log', function () {
    ['organization' => $organization, 'owner' => $owner, 'auditor' => $auditor, 'package' => $package] = makeExportPackageOrg();

    Storage::fake('local');
    $path = 'evidence/export-demo.txt';
    Storage::disk('local')->put($path, 'evidence-bytes');

    $evidence = Evidence::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $package->product_id,
        'type' => EvidenceType::Document,
        'title' => 'Exportable evidence',
        'confidentiality' => EvidenceConfidentiality::Internal,
        'freshness_status' => EvidenceFreshnessStatus::Current,
        'storage_path' => $path,
        'source_filename' => 'export-demo.txt',
        'uploaded_by' => $owner->id,
    ]);
    $package->evidence()->sync([$evidence->id]);

    AuditorFinding::query()->create([
        'package_id' => $package->id,
        'title' => 'Export finding',
        'body' => 'Included in PDF',
        'severity' => AuditorFindingSeverity::Minor,
        'status' => AuditorFindingStatus::Open,
        'created_by' => $auditor->id,
    ]);

    $response = $this->actingAs($auditor)
        ->get(route('auditor.packages.export', $package))
        ->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/zip');
    expect($response->headers->get('content-disposition'))->toContain('.zip');

    $zipPath = $response->baseResponse->getFile()->getPathname();
    $zip = new \ZipArchive;
    expect($zip->open($zipPath))->toBeTrue();
    expect($zip->locateName('review-package.pdf'))->not->toBeFalse();
    expect($zip->locateName('evidence/README.txt'))->not->toBeFalse();
    expect($zip->locateName('evidence/' . $evidence->id . '-export-demo.txt'))->not->toBeFalse();

    $pdf = $zip->getFromName('review-package.pdf');
    expect($pdf)->toStartWith('%PDF');
    $zip->close();

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::AuditorPackageExported->value)
        ->where('organization_id', $organization->id)
        ->exists())->toBeTrue();
});

test('viewer can export package when they can view it', function () {
    ['organization' => $organization, 'owner' => $owner, 'package' => $package] = makeExportPackageOrg();

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

    $this->actingAs($viewer)
        ->get(route('auditor.packages.export', $package))
        ->assertOk();

    $this->actingAs($owner)
        ->get(route('auditor.packages.export', $package))
        ->assertOk();
});
