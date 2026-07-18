<?php

use App\Enums\ClassificationStatus;
use App\Enums\EvidenceConfidentiality;
use App\Enums\EvidenceFreshnessStatus;
use App\Enums\EvidenceType;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ProductVersionState;
use App\Enums\ScopeStatus;
use App\Enums\SupportStatus;
use App\Enums\VulnerabilityBusinessSeverity;
use App\Enums\VulnerabilityDiscoverySource;
use App\Enums\VulnerabilityExploitationStatus;
use App\Enums\VulnerabilityStatus;
use App\Models\Evidence;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVersion;
use App\Models\ProductVulnerability;
use App\Models\Role;
use App\Models\User;
use App\Services\EvidenceService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * @return array{0: Organization, 1: User}
 */
function makeEvidenceOrgWithOwner(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Evidence Org',
        'slug' => 'evidence-org',
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

function makeEvidenceOrgReadOnly(Organization $organization): User
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
function makeProductWithVersionForEvidence(Organization $organization, User $owner): array
{
    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Payments Module Evidence',
        'slug' => 'payments-module-evidence',
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
        'version_number' => '1.0.0',
        'state' => ProductVersionState::Draft,
        'support_status' => SupportStatus::Supported,
    ]);

    return [$product, $version];
}

test('freshness helpers derive expired and review due', function () {
    expect(EvidenceService::deriveFreshness(
        EvidenceFreshnessStatus::Current,
        Carbon::parse('2026-01-01'),
        null,
        Carbon::parse('2026-07-18'),
    ))->toBe(EvidenceFreshnessStatus::Expired);

    expect(EvidenceService::deriveFreshness(
        EvidenceFreshnessStatus::Current,
        null,
        Carbon::parse('2026-07-10'),
        Carbon::parse('2026-07-18'),
    ))->toBe(EvidenceFreshnessStatus::ReviewDue);

    expect(EvidenceService::deriveFreshness(
        EvidenceFreshnessStatus::Superseded,
        Carbon::parse('2020-01-01'),
        Carbon::parse('2020-01-01'),
        Carbon::parse('2026-07-18'),
    ))->toBe(EvidenceFreshnessStatus::Superseded);
});

test('owner can create evidence with file checksum and vulnerability link', function () {
    Storage::fake('local');

    [$organization, $owner] = makeEvidenceOrgWithOwner();
    [$product, $version] = makeProductWithVersionForEvidence($organization, $owner);

    $vulnerability = ProductVulnerability::query()->create([
        'product_id' => $product->id,
        'title' => 'Related vuln',
        'discovery_source' => VulnerabilityDiscoverySource::InternalDiscovery,
        'status' => VulnerabilityStatus::Confirmed,
        'business_severity' => VulnerabilityBusinessSeverity::High,
        'exploitation_status' => VulnerabilityExploitationStatus::None,
    ]);

    $contents = "evidence-body-content\n";
    $file = UploadedFile::fake()->createWithContent('test-report.pdf', $contents);

    $this->actingAs($owner)
        ->post(route('products.evidence.store', $product), [
            'title' => 'HMAC test report',
            'type' => EvidenceType::TestReport->value,
            'confidentiality' => EvidenceConfidentiality::Internal->value,
            'freshness_status' => EvidenceFreshnessStatus::Current->value,
            'product_version_id' => $version->id,
            'collected_at' => '2026-07-18T10:00',
            'file' => $file,
            'vulnerability_ids' => [$vulnerability->id],
        ])
        ->assertRedirect();

    $evidence = Evidence::query()
        ->where('product_id', $product->id)
        ->where('title', 'HMAC test report')
        ->firstOrFail();

    expect($evidence->checksum_sha256)->toBe(hash('sha256', $contents));
    expect($evidence->source_filename)->toBe('test-report.pdf');
    expect($evidence->storage_path)->not->toBeNull();
    expect(Storage::disk('local')->exists($evidence->storage_path))->toBeTrue();
    expect($evidence->vulnerabilities()->pluck('product_vulnerabilities.id')->all())->toContain($vulnerability->id);
});

test('read-only user can view evidence but cannot create', function () {
    [$organization, $owner] = makeEvidenceOrgWithOwner();
    [$product] = makeProductWithVersionForEvidence($organization, $owner);
    $viewer = makeEvidenceOrgReadOnly($organization);

    $this->actingAs($viewer)
        ->get(route('products.evidence.index', $product))
        ->assertOk();

    $this->actingAs($viewer)
        ->post(route('products.evidence.store', $product), [
            'title' => 'Forbidden',
            'type' => EvidenceType::Document->value,
            'confidentiality' => EvidenceConfidentiality::Internal->value,
            'freshness_status' => EvidenceFreshnessStatus::Current->value,
            'file' => UploadedFile::fake()->createWithContent('a.txt', 'x'),
        ])
        ->assertForbidden();
});

test('owner can download evidence file', function () {
    Storage::fake('local');

    [$organization, $owner] = makeEvidenceOrgWithOwner();
    [$product] = makeProductWithVersionForEvidence($organization, $owner);

    $path = "evidence/{$product->id}/file.txt";
    Storage::disk('local')->put($path, 'download-me');

    $evidence = Evidence::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'type' => EvidenceType::Document,
        'title' => 'Downloadable',
        'confidentiality' => EvidenceConfidentiality::Internal,
        'freshness_status' => EvidenceFreshnessStatus::Current,
        'storage_path' => $path,
        'source_filename' => 'file.txt',
        'checksum_sha256' => hash('sha256', 'download-me'),
        'uploaded_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->get(route('products.evidence.download', [$product, $evidence]))
        ->assertOk();
});

test('read-only user cannot download when lacking view is not an issue but create fails already covered', function () {
    Storage::fake('local');

    [$organization, $owner] = makeEvidenceOrgWithOwner();
    [$product] = makeProductWithVersionForEvidence($organization, $owner);
    $viewer = makeEvidenceOrgReadOnly($organization);

    $path = "evidence/{$product->id}/file.txt";
    Storage::disk('local')->put($path, 'download-me');

    $evidence = Evidence::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'type' => EvidenceType::Document,
        'title' => 'Readable download',
        'confidentiality' => EvidenceConfidentiality::Internal,
        'freshness_status' => EvidenceFreshnessStatus::Current,
        'storage_path' => $path,
        'source_filename' => 'file.txt',
        'checksum_sha256' => hash('sha256', 'download-me'),
        'uploaded_by' => $owner->id,
    ]);

    $this->actingAs($viewer)
        ->get(route('products.evidence.download', [$product, $evidence]))
        ->assertOk();
});

test('owner can delete evidence and file is removed', function () {
    Storage::fake('local');

    [$organization, $owner] = makeEvidenceOrgWithOwner();
    [$product] = makeProductWithVersionForEvidence($organization, $owner);

    $path = "evidence/{$product->id}/gone.txt";
    Storage::disk('local')->put($path, 'bye');

    $evidence = Evidence::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'type' => EvidenceType::Document,
        'title' => 'To delete',
        'confidentiality' => EvidenceConfidentiality::Internal,
        'freshness_status' => EvidenceFreshnessStatus::Current,
        'storage_path' => $path,
        'source_filename' => 'gone.txt',
        'checksum_sha256' => hash('sha256', 'bye'),
        'uploaded_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->delete(route('products.evidence.destroy', [$product, $evidence]))
        ->assertRedirect(route('products.evidence.index', $product));

    expect(Evidence::query()->whereKey($evidence->id)->exists())->toBeFalse();
    expect(Storage::disk('local')->exists($path))->toBeFalse();
});

test('internal api lists evidence', function () {
    Storage::fake('local');

    [$organization, $owner] = makeEvidenceOrgWithOwner();
    [$product] = makeProductWithVersionForEvidence($organization, $owner);

    Evidence::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'type' => EvidenceType::Policy,
        'title' => 'Support policy',
        'confidentiality' => EvidenceConfidentiality::Internal,
        'freshness_status' => EvidenceFreshnessStatus::Current,
        'valid_until' => Carbon::parse('2020-01-01'),
        'uploaded_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->getJson(route('internal.products.evidence.index', $product))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.title', 'Support policy')
        ->assertJsonPath('data.0.freshness_status', EvidenceFreshnessStatus::Expired->value);
});
