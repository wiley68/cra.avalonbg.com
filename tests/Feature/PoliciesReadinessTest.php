<?php

use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\PolicyStatus;
use App\Enums\PolicyType;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Models\OrgPolicy;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User, product: Product}
 */
function makePoliciesReadinessFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Policies Readiness Org',
        'slug' => 'policies-readiness-org',
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
        'name' => 'Policies Readiness Product',
        'slug' => 'policies-readiness-product',
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
    ]);

    return [
        'organization' => $organization,
        'owner' => $owner,
        'product' => $product,
    ];
}

function seedApprovedPoliciesForAllTypes(Organization $organization, User $approver): void
{
    foreach (PolicyType::cases() as $type) {
        OrgPolicy::query()->create([
            'organization_id' => $organization->id,
            'policy_type' => $type,
            'title' => $type->value . ' policy',
            'status' => PolicyStatus::Approved,
            'version_label' => '1.0',
            'body' => 'Approved body for ' . $type->value,
            'approved_at' => now(),
            'approved_by' => $approver->id,
        ]);
    }
}

test('missing approved policies produce policies_missing readiness gap', function () {
    $fixture = makePoliciesReadinessFixture();

    $this->actingAs($fixture['owner'])
        ->get(route('products.readiness.show', $fixture['product']))
        ->assertOk()
        ->assertInertia(function ($page) {
            $props = $page->toArray()['props'];
            $sections = collect($props['report']['sections']);
            $gaps = collect($props['report']['gaps']);
            $policies = $sections->firstWhere('key', 'policies');

            expect($policies['status'])->toBe('fail')
                ->and($policies['summary'])->toBe('missing')
                ->and($policies['metrics']['missing_types'])->toBe(count(PolicyType::cases()))
                ->and($gaps->contains(
                    fn($gap) => $gap['message_key'] === 'products.readiness.gaps.policies_missing'
                    && $gap['section'] === 'policies'
                    && $gap['status'] === 'fail'
                    && $gap['link'] === 'policies',
                ))->toBeTrue();
        });
});

test('all approved types with under_review policy produce policies_review_due gap', function () {
    $fixture = makePoliciesReadinessFixture();
    seedApprovedPoliciesForAllTypes($fixture['organization'], $fixture['owner']);

    OrgPolicy::query()->create([
        'organization_id' => $fixture['organization']->id,
        'policy_type' => PolicyType::Support,
        'title' => 'Support draft under review',
        'status' => PolicyStatus::UnderReview,
        'version_label' => '1.1',
        'body' => 'Revision awaiting approval',
    ]);

    $this->actingAs($fixture['owner'])
        ->get(route('products.readiness.show', $fixture['product']))
        ->assertOk()
        ->assertInertia(function ($page) {
            $props = $page->toArray()['props'];
            $sections = collect($props['report']['sections']);
            $gaps = collect($props['report']['gaps']);
            $policies = $sections->firstWhere('key', 'policies');

            expect($policies['status'])->toBe('warn')
                ->and($policies['summary'])->toBe('review_due')
                ->and($policies['metrics']['missing_types'])->toBe(0)
                ->and($policies['metrics']['under_review'])->toBe(1)
                ->and($gaps->contains(
                    fn($gap) => $gap['message_key'] === 'products.readiness.gaps.policies_review_due'
                    && $gap['section'] === 'policies'
                    && $gap['status'] === 'warn'
                    && $gap['link'] === 'policies',
                ))->toBeTrue()
                ->and($gaps->contains(
                    fn($gap) => $gap['message_key'] === 'products.readiness.gaps.policies_missing',
                ))->toBeFalse();
        });
});

test('all required policy types approved clears policies readiness gaps', function () {
    $fixture = makePoliciesReadinessFixture();
    seedApprovedPoliciesForAllTypes($fixture['organization'], $fixture['owner']);

    $this->actingAs($fixture['owner'])
        ->get(route('products.readiness.show', $fixture['product']))
        ->assertOk()
        ->assertInertia(function ($page) {
            $props = $page->toArray()['props'];
            $sections = collect($props['report']['sections']);
            $gaps = collect($props['report']['gaps']);
            $policies = $sections->firstWhere('key', 'policies');

            expect($policies['status'])->toBe('pass')
                ->and($policies['summary'])->toBe('complete')
                ->and($policies['metrics']['missing_types'])->toBe(0)
                ->and($policies['metrics']['approved_types'])->toBe(count(PolicyType::cases()))
                ->and($gaps->contains(
                    fn($gap) => str_starts_with($gap['message_key'], 'products.readiness.gaps.policies_'),
                ))->toBeFalse();
        });
});
