<?php

use App\Enums\AuditEventType;
use App\Enums\ClassificationStatus;
use App\Enums\EvidenceType;
use App\Enums\LicensingModel;
use App\Enums\PolicyStatus;
use App\Enums\PolicyType;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Models\AuditLog;
use App\Models\Evidence;
use App\Models\Organization;
use App\Models\OrgPolicy;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User}
 */
function makePoliciesOrgWithOwner(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Policies Org',
        'slug' => 'policies-org',
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

    return compact('organization', 'owner');
}

function makePoliciesOrgViewer(Organization $organization): User
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

test('owner can view policies index', function () {
    ['owner' => $owner] = makePoliciesOrgWithOwner();

    $this->actingAs($owner)
        ->get(route('policies.index'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('policies/Index')
            ->where('canManage', true));
});

test('owner can create policy from template and audit is recorded', function () {
    ['organization' => $organization, 'owner' => $owner] = makePoliciesOrgWithOwner();

    $this->actingAs($owner)
        ->post(route('policies.store'), [
            'policy_type' => PolicyType::VulnerabilityDisclosure->value,
            'use_template' => true,
            'title' => '',
            'version_label' => '',
            'body' => '',
        ])
        ->assertRedirect();

    $policy = OrgPolicy::query()->first();

    expect($policy)->not->toBeNull()
        ->and($policy->organization_id)->toBe($organization->id)
        ->and($policy->status)->toBe(PolicyStatus::Draft)
        ->and($policy->title)->not->toBe('')
        ->and($policy->body)->toContain('Vulnerability disclosure');

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::OrgPolicyCreated->value)
        ->exists())->toBeTrue();
});

test('lifecycle submit approve retires previous approved of same type', function () {
    ['organization' => $organization, 'owner' => $owner] = makePoliciesOrgWithOwner();

    $previous = OrgPolicy::query()->create([
        'organization_id' => $organization->id,
        'policy_type' => PolicyType::Support,
        'title' => 'Support v1',
        'status' => PolicyStatus::Approved,
        'version_label' => '1.0',
        'body' => 'Old support policy',
        'approved_at' => now()->subMonth(),
        'approved_by' => $owner->id,
    ]);

    $draft = OrgPolicy::query()->create([
        'organization_id' => $organization->id,
        'policy_type' => PolicyType::Support,
        'title' => 'Support v2',
        'status' => PolicyStatus::Draft,
        'version_label' => '2.0',
        'body' => 'New support policy',
        'supersedes_id' => $previous->id,
    ]);

    $this->actingAs($owner)
        ->post(route('policies.submit-review', $draft))
        ->assertRedirect();

    expect($draft->fresh()->status)->toBe(PolicyStatus::UnderReview);

    $this->actingAs($owner)
        ->post(route('policies.approve', $draft))
        ->assertRedirect();

    expect($draft->fresh()->status)->toBe(PolicyStatus::Approved)
        ->and($draft->fresh()->approved_by)->toBe($owner->id)
        ->and($previous->fresh()->status)->toBe(PolicyStatus::Retired);
});

test('viewer can list policies but cannot create or change lifecycle', function () {
    ['organization' => $organization, 'owner' => $owner] = makePoliciesOrgWithOwner();
    $viewer = makePoliciesOrgViewer($organization);

    $policy = OrgPolicy::query()->create([
        'organization_id' => $organization->id,
        'policy_type' => PolicyType::Update,
        'title' => 'Update policy',
        'status' => PolicyStatus::Draft,
        'version_label' => '1.0',
        'body' => 'Body',
    ]);

    $this->actingAs($viewer)
        ->get(route('policies.index'))
        ->assertOk()
        ->assertInertia(fn($page) => $page->where('canManage', false));

    $this->actingAs($viewer)
        ->post(route('policies.store'), [
            'policy_type' => PolicyType::Update->value,
            'title' => 'Should fail',
            'version_label' => '1.0',
            'body' => 'Nope',
            'use_template' => false,
        ])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->post(route('policies.submit-review', $policy))
        ->assertForbidden();

    expect($policy->fresh()->status)->toBe(PolicyStatus::Draft);
});

test('cannot delete approved policy', function () {
    ['organization' => $organization, 'owner' => $owner] = makePoliciesOrgWithOwner();

    $policy = OrgPolicy::query()->create([
        'organization_id' => $organization->id,
        'policy_type' => PolicyType::IncidentResponse,
        'title' => 'IR',
        'status' => PolicyStatus::Approved,
        'version_label' => '1.0',
        'body' => 'Body',
        'approved_at' => now(),
        'approved_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->delete(route('policies.destroy', $policy))
        ->assertForbidden();

    expect(OrgPolicy::query()->whereKey($policy->id)->exists())->toBeTrue();
});

test('internal api lists org policies', function () {
    ['organization' => $organization, 'owner' => $owner] = makePoliciesOrgWithOwner();

    OrgPolicy::query()->create([
        'organization_id' => $organization->id,
        'policy_type' => PolicyType::SecureDevelopment,
        'title' => 'SDL',
        'status' => PolicyStatus::Draft,
        'version_label' => '1.0',
        'body' => 'Body',
    ]);

    $this->actingAs($owner)
        ->getJson(route('internal.policies.index', ['search' => 'SDL']))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.title', 'SDL');
});

test('template endpoint returns starter content', function () {
    ['owner' => $owner] = makePoliciesOrgWithOwner();

    $this->actingAs($owner)
        ->getJson(route('policies.template', [
            'policy_type' => PolicyType::ThirdPartyComponents->value,
        ]))
        ->assertOk()
        ->assertJsonPath('version_label', '1.0')
        ->assertJsonFragment(['title' => 'Third-party component policy']);
});

test('internal api can filter policies by policy_type', function () {
    ['organization' => $organization, 'owner' => $owner] = makePoliciesOrgWithOwner();

    OrgPolicy::query()->create([
        'organization_id' => $organization->id,
        'policy_type' => PolicyType::Support,
        'title' => 'Support policy',
        'status' => PolicyStatus::Draft,
        'version_label' => '1.0',
        'body' => 'Support body',
    ]);

    OrgPolicy::query()->create([
        'organization_id' => $organization->id,
        'policy_type' => PolicyType::Update,
        'title' => 'Update policy',
        'status' => PolicyStatus::Draft,
        'version_label' => '1.0',
        'body' => 'Update body',
    ]);

    $this->actingAs($owner)
        ->getJson(route('internal.policies.index', [
            'policy_type' => PolicyType::Support->value,
        ]))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.policy_type', PolicyType::Support->value)
        ->assertJsonPath('data.0.title', 'Support policy');
});

test('policies index accepts policy_type filter prop', function () {
    ['owner' => $owner] = makePoliciesOrgWithOwner();

    $this->actingAs($owner)
        ->get(route('policies.index', [
            'policy_type' => PolicyType::IncidentResponse->value,
        ]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('policies/Index')
            ->where('filters.policy_type', PolicyType::IncidentResponse->value));
});

test('approved policy can be published as product evidence', function () {
    Storage::fake('local');

    ['organization' => $organization, 'owner' => $owner] = makePoliciesOrgWithOwner();

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Policy Evidence Product',
        'slug' => 'policy-evidence-product',
        'manufacturer' => 'Acme',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    $policy = OrgPolicy::query()->create([
        'organization_id' => $organization->id,
        'policy_type' => PolicyType::VulnerabilityDisclosure,
        'title' => 'CVD Policy',
        'status' => PolicyStatus::Approved,
        'version_label' => '1.0',
        'body' => "# CVD\n\nDisclose responsibly.",
        'approved_at' => now(),
        'approved_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->post(route('policies.publish-evidence', $policy), [
            'product_id' => $product->id,
        ])
        ->assertRedirect(route('policies.edit', $policy));

    $policy->refresh();

    expect($policy->evidence_id)->not->toBeNull();

    $evidence = Evidence::query()->findOrFail($policy->evidence_id);

    expect($evidence->product_id)->toBe($product->id)
        ->and($evidence->type)->toBe(EvidenceType::Policy)
        ->and($evidence->source)->toBe('org_policy:' . $policy->id)
        ->and($evidence->source_filename)->toContain('policy-vulnerability_disclosure')
        ->and(Storage::disk('local')->get($evidence->storage_path))->toBe($policy->body);

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::OrgPolicyPublishedEvidence->value)
        ->exists())->toBeTrue();

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::EvidenceCreated->value)
        ->exists())->toBeTrue();

    $this->actingAs($owner)
        ->post(route('policies.publish-evidence', $policy), [
            'product_id' => $product->id,
        ])
        ->assertSessionHasErrors('evidence_id');
});

test('draft policy cannot be published as evidence', function () {
    ['organization' => $organization, 'owner' => $owner] = makePoliciesOrgWithOwner();

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Draft Publish Product',
        'slug' => 'draft-publish-product',
        'manufacturer' => 'Acme',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    $policy = OrgPolicy::query()->create([
        'organization_id' => $organization->id,
        'policy_type' => PolicyType::Update,
        'title' => 'Update draft',
        'status' => PolicyStatus::Draft,
        'version_label' => '0.1',
        'body' => 'Draft body',
    ]);

    $this->actingAs($owner)
        ->post(route('policies.publish-evidence', $policy), [
            'product_id' => $product->id,
        ])
        ->assertSessionHasErrors('status');
});

test('viewer cannot publish policy as evidence', function () {
    ['organization' => $organization, 'owner' => $owner] = makePoliciesOrgWithOwner();
    $viewer = makePoliciesOrgViewer($organization);

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Viewer Publish Product',
        'slug' => 'viewer-publish-product',
        'manufacturer' => 'Acme',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    $policy = OrgPolicy::query()->create([
        'organization_id' => $organization->id,
        'policy_type' => PolicyType::IncidentResponse,
        'title' => 'IR approved',
        'status' => PolicyStatus::Approved,
        'version_label' => '1.0',
        'body' => 'IR body',
        'approved_at' => now(),
        'approved_by' => $owner->id,
    ]);

    $this->actingAs($viewer)
        ->post(route('policies.publish-evidence', $policy), [
            'product_id' => $product->id,
        ])
        ->assertForbidden();
});
