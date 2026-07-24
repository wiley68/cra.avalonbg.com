<?php

use App\Enums\ClassificationStatus;
use App\Enums\EvidenceConfidentiality;
use App\Enums\EvidenceFreshnessStatus;
use App\Enums\EvidenceType;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Models\Evidence;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\EvidenceService;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schedule;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, otherOrg: Organization, product: Product, owner: User}
 */
function makeEvidenceFreshnessCommandFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Evidence Freshness Org',
        'slug' => 'evidence-freshness-org-' . uniqid(),
        'is_active' => true,
    ]);

    $otherOrg = Organization::query()->create([
        'name' => 'Other Evidence Org',
        'slug' => 'other-evidence-org-' . uniqid(),
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

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Freshness Product',
        'slug' => 'freshness-product-' . uniqid(),
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    $otherProduct = Product::query()->create([
        'organization_id' => $otherOrg->id,
        'name' => 'Other Org Product',
        'slug' => 'other-org-product-' . uniqid(),
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    return compact('organization', 'otherOrg', 'product', 'owner') + ['otherProduct' => $otherProduct];
}

test('evidence refresh freshness command marks expired and review due rows', function () {
    [
        'organization' => $organization,
        'product' => $product,
        'owner' => $owner,
    ] = makeEvidenceFreshnessCommandFixture();

    $expired = Evidence::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'type' => EvidenceType::Document,
        'title' => 'Expired still marked current',
        'confidentiality' => EvidenceConfidentiality::Internal,
        'freshness_status' => EvidenceFreshnessStatus::Current,
        'valid_until' => Carbon::parse('2020-01-01'),
        'uploaded_by' => $owner->id,
    ]);

    $reviewDue = Evidence::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'type' => EvidenceType::TestReport,
        'title' => 'Review due still marked current',
        'confidentiality' => EvidenceConfidentiality::Internal,
        'freshness_status' => EvidenceFreshnessStatus::Current,
        'review_due_at' => Carbon::yesterday(),
        'uploaded_by' => $owner->id,
    ]);

    $stillCurrent = Evidence::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'type' => EvidenceType::Policy,
        'title' => 'Still current',
        'confidentiality' => EvidenceConfidentiality::Internal,
        'freshness_status' => EvidenceFreshnessStatus::Current,
        'valid_until' => Carbon::tomorrow(),
        'uploaded_by' => $owner->id,
    ]);

    $superseded = Evidence::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'type' => EvidenceType::Document,
        'title' => 'Manual superseded override',
        'confidentiality' => EvidenceConfidentiality::Internal,
        'freshness_status' => EvidenceFreshnessStatus::Superseded,
        'valid_until' => Carbon::parse('2019-01-01'),
        'uploaded_by' => $owner->id,
    ]);

    $this->artisan('evidence:refresh-freshness')
        ->assertSuccessful()
        ->expectsOutputToContain('Scanned 4 evidence row(s); 2 freshness status(es) updated.');

    expect($expired->fresh()->freshness_status)->toBe(EvidenceFreshnessStatus::Expired)
        ->and($reviewDue->fresh()->freshness_status)->toBe(EvidenceFreshnessStatus::ReviewDue)
        ->and($stillCurrent->fresh()->freshness_status)->toBe(EvidenceFreshnessStatus::Current)
        ->and($superseded->fresh()->freshness_status)->toBe(EvidenceFreshnessStatus::Superseded);
});

test('evidence refresh freshness dry-run does not write', function () {
    [
        'organization' => $organization,
        'product' => $product,
        'owner' => $owner,
    ] = makeEvidenceFreshnessCommandFixture();

    $expired = Evidence::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'type' => EvidenceType::Document,
        'title' => 'Would expire',
        'confidentiality' => EvidenceConfidentiality::Internal,
        'freshness_status' => EvidenceFreshnessStatus::Current,
        'valid_until' => Carbon::parse('2021-06-01'),
        'uploaded_by' => $owner->id,
    ]);

    $this->artisan('evidence:refresh-freshness', ['--dry-run' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('[dry-run] Scanned 1 evidence row(s); 1 freshness status(es) would update.');

    expect($expired->fresh()->freshness_status)->toBe(EvidenceFreshnessStatus::Current);
});

test('evidence refresh freshness can scope to organization', function () {
    [
        'organization' => $organization,
        'otherOrg' => $otherOrg,
        'product' => $product,
        'otherProduct' => $otherProduct,
        'owner' => $owner,
    ] = makeEvidenceFreshnessCommandFixture();

    $inScope = Evidence::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'type' => EvidenceType::Document,
        'title' => 'In org',
        'confidentiality' => EvidenceConfidentiality::Internal,
        'freshness_status' => EvidenceFreshnessStatus::Current,
        'valid_until' => Carbon::parse('2020-01-01'),
        'uploaded_by' => $owner->id,
    ]);

    $outOfScope = Evidence::query()->create([
        'organization_id' => $otherOrg->id,
        'product_id' => $otherProduct->id,
        'type' => EvidenceType::Document,
        'title' => 'Other org',
        'confidentiality' => EvidenceConfidentiality::Internal,
        'freshness_status' => EvidenceFreshnessStatus::Current,
        'valid_until' => Carbon::parse('2020-01-01'),
        'uploaded_by' => $owner->id,
    ]);

    $this->artisan('evidence:refresh-freshness', [
        '--organization' => $organization->id,
    ])->assertSuccessful();

    expect($inScope->fresh()->freshness_status)->toBe(EvidenceFreshnessStatus::Expired)
        ->and($outOfScope->fresh()->freshness_status)->toBe(EvidenceFreshnessStatus::Current);
});

test('evidence refresh freshness service respects invalid override', function () {
    [
        'organization' => $organization,
        'product' => $product,
        'owner' => $owner,
    ] = makeEvidenceFreshnessCommandFixture();

    $invalid = Evidence::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'type' => EvidenceType::Document,
        'title' => 'Invalid override',
        'confidentiality' => EvidenceConfidentiality::Internal,
        'freshness_status' => EvidenceFreshnessStatus::Invalid,
        'valid_until' => Carbon::parse('2018-01-01'),
        'uploaded_by' => $owner->id,
    ]);

    $result = app(EvidenceService::class)->refreshDerivedFreshnessStatuses();

    expect($result['updated'])->toBe(0)
        ->and($invalid->fresh()->freshness_status)->toBe(EvidenceFreshnessStatus::Invalid);
});

test('evidence refresh freshness is registered on the daily schedule', function () {
    $events = Schedule::events();

    $match = collect($events)->first(
        fn($event) => str_contains($event->command ?? '', 'evidence:refresh-freshness'),
    );

    expect($match)->not->toBeNull()
        ->and($match->expression)->toBe('0 0 * * *');
});
