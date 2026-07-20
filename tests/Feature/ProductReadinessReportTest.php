<?php

use App\Enums\AuditEventType;
use App\Enums\ClassificationStatus;
use App\Enums\EvidenceConfidentiality;
use App\Enums\EvidenceFreshnessStatus;
use App\Enums\EvidenceType;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\VcsAuthType;
use App\Enums\VcsConnectionStatus;
use App\Enums\VcsProvider;
use App\Models\AuditLog;
use App\Models\Evidence;
use App\Models\Organization;
use App\Models\OrganizationVcsConnection;
use App\Models\Product;
use App\Models\ProductRepository;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/**
 * @return array{0: Organization, 1: User}
 */
function makeReadinessOrgWithOwner(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Readiness Org',
        'slug' => 'readiness-org',
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

function makeReadinessOrgReadOnly(Organization $organization): User
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

function makeProductForReadiness(
    Organization $organization,
    User $owner,
    array $overrides = [],
): Product {
    return Product::query()->create(array_merge([
        'organization_id' => $organization->id,
        'name' => 'Readiness Product',
        'slug' => 'readiness-product',
        'manufacturer' => 'Acme Soft',
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
    ], $overrides));
}

test('owner can view readiness report with expected sections', function () {
    [$organization, $owner] = makeReadinessOrgWithOwner();
    $product = makeProductForReadiness($organization, $owner);

    $this->actingAs($owner)
        ->get(route('products.readiness.show', $product))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/readiness/Show')
            ->has('report.sections', 17)
            ->where('report.product.id', $product->id));

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::ReadinessReportViewed)
        ->where('organization_id', $organization->id)
        ->where('product_id', $product->id)
        ->exists())->toBeTrue();
});

test('read-only user with products.view can open readiness report', function () {
    [$organization, $owner] = makeReadinessOrgWithOwner();
    $product = makeProductForReadiness($organization, $owner);
    $viewer = makeReadinessOrgReadOnly($organization);

    $this->actingAs($viewer)
        ->get(route('products.readiness.show', $product))
        ->assertOk();
});

test('product from another organization returns 404', function () {
    [$organization, $owner] = makeReadinessOrgWithOwner();

    $otherOrg = Organization::query()->create([
        'name' => 'Other Readiness Org',
        'slug' => 'other-readiness-org',
        'is_active' => true,
    ]);

    $foreign = makeProductForReadiness($otherOrg, $owner, [
        'slug' => 'foreign-product',
        'name' => 'Foreign Product',
    ]);

    $this->actingAs($owner)
        ->get(route('products.readiness.show', $foreign))
        ->assertNotFound();
});

test('unclassified product marks classification as fail with gap', function () {
    [$organization, $owner] = makeReadinessOrgWithOwner();
    $product = makeProductForReadiness($organization, $owner, [
        'classification_status' => ClassificationStatus::Unclassified,
    ]);

    $this->actingAs($owner)
        ->get(route('products.readiness.show', $product))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->where('report.sections.1.key', 'classification')
            ->where('report.sections.1.status', 'fail')
            ->has('report.gaps')
            ->where('report.gaps.0.section', 'classification'));
});

test('missing repository link adds readiness warn gap', function () {
    [$organization, $owner] = makeReadinessOrgWithOwner();
    $product = makeProductForReadiness($organization, $owner);

    $this->actingAs($owner)
        ->get(route('products.readiness.show', $product))
        ->assertOk()
        ->assertInertia(function ($page) {
            $props = $page->toArray()['props'];
            $sections = collect($props['report']['sections']);
            $gaps = collect($props['report']['gaps']);
            $repository = $sections->firstWhere('key', 'repository');

            expect($repository['status'])->toBe('warn')
                ->and($repository['summary'])->toBe('not_linked')
                ->and($gaps->contains(
                    fn($gap) => $gap['message_key'] === 'products.readiness.gaps.no_repository_linked'
                    && $gap['link'] === 'edit',
                ))->toBeTrue();
        });
});

test('failing repository ci marks readiness fail gap', function () {
    [$organization, $owner] = makeReadinessOrgWithOwner();
    $product = makeProductForReadiness($organization, $owner);

    $connection = OrganizationVcsConnection::query()->create([
        'organization_id' => $organization->id,
        'provider' => VcsProvider::Github,
        'auth_type' => VcsAuthType::Pat,
        'token' => 'ghp_readiness',
        'label' => 'GitHub',
        'status' => VcsConnectionStatus::Active,
        'last_verified_at' => now(),
    ]);

    ProductRepository::query()->create([
        'product_id' => $product->id,
        'connection_id' => $connection->id,
        'external_id' => '1',
        'full_name' => 'acme/widget',
        'remote_url' => 'https://github.com/acme/widget',
        'default_branch' => 'main',
        'last_synced_at' => now(),
        'last_sync_summary' => [
            'ci' => [
                'status' => 'completed',
                'conclusion' => 'failure',
            ],
        ],
    ]);

    $this->actingAs($owner)
        ->get(route('products.readiness.show', $product))
        ->assertOk()
        ->assertInertia(function ($page) {
            $props = $page->toArray()['props'];
            $sections = collect($props['report']['sections']);
            $gaps = collect($props['report']['gaps']);
            $repository = $sections->firstWhere('key', 'repository');

            expect($repository['status'])->toBe('fail')
                ->and($repository['summary'])->toBe('ci_failing')
                ->and($gaps->contains(
                    fn($gap) => $gap['message_key'] === 'products.readiness.gaps.ci_failing',
                ))->toBeTrue();
        });
});

test('expired evidence marks evidence section as fail', function () {
    [$organization, $owner] = makeReadinessOrgWithOwner();
    $product = makeProductForReadiness($organization, $owner);

    Evidence::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'type' => EvidenceType::Document,
        'title' => 'Expired evidence',
        'confidentiality' => EvidenceConfidentiality::Internal,
        'freshness_status' => EvidenceFreshnessStatus::Current,
        'valid_until' => Carbon::parse('2020-01-01'),
        'uploaded_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->get(route('products.readiness.show', $product))
        ->assertOk()
        ->assertInertia(function ($page) {
            $sections = collect($page->toArray()['props']['report']['sections']);
            $evidence = $sections->firstWhere('key', 'evidence');

            expect($evidence['status'])->toBe('fail');
        });
});

test('export route streams pdf and writes audit log', function () {
    [$organization, $owner] = makeReadinessOrgWithOwner();
    $product = makeProductForReadiness($organization, $owner);

    $response = $this->actingAs($owner)
        ->get(route('products.readiness.export', $product))
        ->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/pdf');
    expect($response->headers->get('content-disposition'))->toContain('inline');
    expect($response->headers->get('content-disposition'))->toContain('.pdf');
    expect($response->getContent())->toStartWith('%PDF');

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::ReadinessReportExported)
        ->where('organization_id', $organization->id)
        ->where('product_id', $product->id)
        ->exists())->toBeTrue();
});
