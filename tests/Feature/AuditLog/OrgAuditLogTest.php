<?php

use App\Enums\AuditEventSource;
use App\Enums\AuditEventType;
use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\TaskApprovalStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{0: Organization, 1: User}
 */
function makeAuditOrgWithOwner(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Audit Trail Org',
        'slug' => 'audit-trail-org',
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

function makeAuditOrgReadOnly(Organization $organization): User
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

function makeProductForAudit(Organization $organization, User $owner): Product
{
    return Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Audit Product',
        'slug' => 'audit-product',
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
}

test('organization owner can view org audit logs index', function () {
    [$organization, $owner] = makeAuditOrgWithOwner();

    $this->actingAs($owner)
        ->get(route('audit-logs.index'))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('audit-logs/Index')
            ->where('organization.id', $organization->id));
});

test('read-only user without audit.view cannot access org audit logs', function () {
    [$organization] = makeAuditOrgWithOwner();
    $viewer = makeAuditOrgReadOnly($organization);

    $this->actingAs($viewer)
        ->get(route('audit-logs.index'))
        ->assertForbidden();

    $this->actingAs($viewer)
        ->getJson(route('internal.audit-logs.index'))
        ->assertForbidden();
});

test('organization owner api only returns logs for their organization', function () {
    [$organization, $owner] = makeAuditOrgWithOwner();

    $otherOrg = Organization::query()->create([
        'name' => 'Other Audit Org',
        'slug' => 'other-audit-org',
        'is_active' => true,
    ]);

    AuditLog::query()->create([
        'occurred_at' => now(),
        'event_type' => AuditEventType::ProductCreated,
        'event_source' => AuditEventSource::Workspace,
        'is_success' => true,
        'organization_id' => $organization->id,
        'user_email' => $owner->email,
        'user_name' => $owner->name,
        'description' => json_encode([['field' => 'name', 'value' => 'Mine']], JSON_UNESCAPED_UNICODE),
    ]);

    AuditLog::query()->create([
        'occurred_at' => now(),
        'event_type' => AuditEventType::ProductCreated,
        'event_source' => AuditEventSource::Workspace,
        'is_success' => true,
        'organization_id' => $otherOrg->id,
        'user_email' => 'other@example.com',
        'user_name' => 'Other',
        'description' => json_encode([['field' => 'name', 'value' => 'Theirs']], JSON_UNESCAPED_UNICODE),
    ]);

    AuditLog::query()->create([
        'occurred_at' => now(),
        'event_type' => AuditEventType::LoginSuccess,
        'event_source' => AuditEventSource::Workspace,
        'is_success' => true,
        'organization_id' => null,
        'user_email' => $owner->email,
        'user_name' => $owner->name,
        'description' => json_encode([['field' => 'email', 'value' => $owner->email]], JSON_UNESCAPED_UNICODE),
    ]);

    $this->actingAs($owner)
        ->getJson(route('internal.audit-logs.index', [
            'page' => 1,
            'per_page' => 10,
            'sort_by' => 'occurred_at',
            'sort_desc' => '1',
        ]))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.organization_id', $organization->id)
        ->assertJsonPath('data.0.event_type', 'product_created');
});

test('creating a product writes an audit log with organization scope', function () {
    [$organization, $owner] = makeAuditOrgWithOwner();

    $this->actingAs($owner)
        ->post(route('products.store'), [
            'name' => 'Logged Product',
            'slug' => 'logged-product',
            'product_type' => ProductType::Software->value,
            'licensing_model' => LicensingModel::Paid->value,
            'has_remote_data_processing' => true,
            'has_network_connectivity' => true,
            'scope_status' => ScopeStatus::LikelyInScope->value,
            'classification_status' => ClassificationStatus::General->value,
            'skip_scope_wizard' => true,
            'skip_classification_wizard' => true,
        ])
        ->assertRedirect();

    $product = Product::query()->where('slug', 'logged-product')->firstOrFail();

    $log = AuditLog::query()
        ->where('event_type', AuditEventType::ProductCreated)
        ->where('organization_id', $organization->id)
        ->where('product_id', $product->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($owner->id);
});

test('creating and approving a task writes scoped audit logs', function () {
    [$organization, $owner] = makeAuditOrgWithOwner();
    $product = makeProductForAudit($organization, $owner);

    $this->actingAs($owner)
        ->post(route('products.tasks.store', $product), [
            'title' => 'Audit task',
            'status' => TaskStatus::Open->value,
            'priority' => TaskPriority::Medium->value,
            'approval_status' => TaskApprovalStatus::NotRequired->value,
        ])
        ->assertRedirect();

    $task = Task::query()->where('title', 'Audit task')->firstOrFail();

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::TaskCreated)
        ->where('organization_id', $organization->id)
        ->where('product_id', $product->id)
        ->exists())->toBeTrue();

    $this->actingAs($owner)
        ->post(route('products.tasks.submit-approval', [$product, $task]))
        ->assertRedirect();

    $this->actingAs($owner)
        ->post(route('products.tasks.approve', [$product, $task]), [
            'approval_comment' => 'OK',
        ])
        ->assertRedirect();

    $approveLog = AuditLog::query()
        ->where('event_type', AuditEventType::TaskApproved)
        ->where('organization_id', $organization->id)
        ->where('product_id', $product->id)
        ->first();

    expect($approveLog)->not->toBeNull();
});

test('platform admin still accesses admin audit logs', function () {
    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
        'is_platform_admin' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.audit-logs.index'))
        ->assertOk()
        ->assertInertia(fn($page) => $page->component('admin/audit-logs/Index'));
});

test('organization owner still cannot access platform admin audit routes', function () {
    [$organization, $owner] = makeAuditOrgWithOwner();

    expect($organization)->toBeInstanceOf(Organization::class)
        ->and($owner)->toBeInstanceOf(User::class);

    $this->actingAs($owner)
        ->get(route('admin.audit-logs.index'))
        ->assertForbidden();

    $this->actingAs($owner)
        ->getJson(route('admin.internal.audit-logs.index'))
        ->assertForbidden();
});
