<?php

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
use App\Models\ProductRisk;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Enums\ProductRiskStatus;
use App\Enums\RiskCategory;
use App\Enums\RiskImpact;
use App\Enums\RiskLikelihood;
use App\Enums\RiskTreatment;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{0: Organization, 1: User}
 */
function makeTaskOrgWithOwner(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Tasks Org',
        'slug' => 'tasks-org',
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

function makeTaskOrgUser(Organization $organization, string $roleSlug): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $role = Role::query()->where('slug', $roleSlug)->firstOrFail();

    $organization->users()->attach($user->id, [
        'role_id' => $role->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $user;
}

function makeProductForTasks(Organization $organization, User $owner): Product
{
    return Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Tasks Product',
        'slug' => 'tasks-product',
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

test('owner can create update and delete a task linked to a risk', function () {
    [$organization, $owner] = makeTaskOrgWithOwner();
    $product = makeProductForTasks($organization, $owner);

    $risk = ProductRisk::query()->create([
        'product_id' => $product->id,
        'title' => 'Related risk',
        'category' => RiskCategory::UnauthorisedAccess,
        'likelihood' => RiskLikelihood::Medium,
        'impact' => RiskImpact::Medium,
        'treatment' => RiskTreatment::Mitigate,
        'status' => ProductRiskStatus::Open,
    ]);

    $this->actingAs($owner)
        ->post(route('products.tasks.store', $product), [
            'title' => 'Close residual risk',
            'description' => 'Finish treatment plan',
            'status' => TaskStatus::Open->value,
            'priority' => TaskPriority::High->value,
            'assignee_user_id' => $owner->id,
            'due_at' => '2026-08-01',
            'subject_type' => 'risk',
            'subject_id' => $risk->id,
            'approval_status' => TaskApprovalStatus::NotRequired->value,
        ])
        ->assertRedirect();

    $task = Task::query()
        ->where('product_id', $product->id)
        ->where('title', 'Close residual risk')
        ->firstOrFail();

    expect($task->organization_id)->toBe($organization->id);
    expect($task->subject_type)->toBe(ProductRisk::class);
    expect($task->subject_id)->toBe($risk->id);
    expect($task->assignee_user_id)->toBe($owner->id);
    expect($task->created_by)->toBe($owner->id);

    $this->actingAs($owner)
        ->put(route('products.tasks.update', [$product, $task]), [
            'title' => 'Close residual risk updated',
            'description' => 'Updated notes',
            'status' => TaskStatus::InProgress->value,
            'priority' => TaskPriority::Medium->value,
            'assignee_user_id' => $owner->id,
            'due_at' => '2026-08-15',
            'subject_type' => 'risk',
            'subject_id' => $risk->id,
            'approval_status' => TaskApprovalStatus::NotRequired->value,
        ])
        ->assertRedirect(route('products.tasks.edit', [$product, $task]));

    $task->refresh();
    expect($task->title)->toBe('Close residual risk updated');
    expect($task->status)->toBe(TaskStatus::InProgress);

    $this->actingAs($owner)
        ->delete(route('products.tasks.destroy', [$product, $task]))
        ->assertRedirect(route('products.tasks.index', $product));

    expect(Task::query()->whereKey($task->id)->exists())->toBeFalse();
});

test('read-only user can view tasks but cannot create', function () {
    [$organization, $owner] = makeTaskOrgWithOwner();
    $product = makeProductForTasks($organization, $owner);
    $viewer = makeTaskOrgUser($organization, 'read_only');

    $this->actingAs($viewer)
        ->get(route('products.tasks.index', $product))
        ->assertOk();

    $this->actingAs($viewer)
        ->post(route('products.tasks.store', $product), [
            'title' => 'Forbidden',
            'status' => TaskStatus::Open->value,
            'priority' => TaskPriority::Low->value,
        ])
        ->assertForbidden();
});

test('developer cannot approve tasks', function () {
    [$organization, $owner] = makeTaskOrgWithOwner();
    $product = makeProductForTasks($organization, $owner);
    $developer = makeTaskOrgUser($organization, 'developer');

    $task = Task::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Needs approval',
        'status' => TaskStatus::PendingApproval,
        'priority' => TaskPriority::Medium,
        'created_by' => $owner->id,
        'approval_status' => TaskApprovalStatus::Pending,
    ]);

    $this->actingAs($developer)
        ->post(route('products.tasks.approve', [$product, $task]), [
            'approval_comment' => 'Nope',
        ])
        ->assertForbidden();
});

test('owner can submit approve and reject with audit log', function () {
    [$organization, $owner] = makeTaskOrgWithOwner();
    $product = makeProductForTasks($organization, $owner);

    $task = Task::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Approval flow',
        'status' => TaskStatus::Open,
        'priority' => TaskPriority::High,
        'created_by' => $owner->id,
        'approval_status' => TaskApprovalStatus::NotRequired,
    ]);

    $this->actingAs($owner)
        ->post(route('products.tasks.submit-approval', [$product, $task]))
        ->assertRedirect(route('products.tasks.edit', [$product, $task]));

    $task->refresh();
    expect($task->approval_status)->toBe(TaskApprovalStatus::Pending);
    expect($task->status)->toBe(TaskStatus::PendingApproval);

    $this->actingAs($owner)
        ->post(route('products.tasks.reject', [$product, $task]), [
            'approval_comment' => 'Needs more evidence',
        ])
        ->assertRedirect(route('products.tasks.edit', [$product, $task]));

    $task->refresh();
    expect($task->approval_status)->toBe(TaskApprovalStatus::Rejected);
    expect($task->status)->toBe(TaskStatus::InProgress);
    expect($task->approval_comment)->toBe('Needs more evidence');
    expect(AuditLog::query()->where('event_type', 'task_rejected')->exists())->toBeTrue();

    $this->actingAs($owner)
        ->post(route('products.tasks.submit-approval', [$product, $task]))
        ->assertRedirect();

    $this->actingAs($owner)
        ->post(route('products.tasks.approve', [$product, $task]), [
            'approval_comment' => 'Looks good',
        ])
        ->assertRedirect();

    $task->refresh();
    expect($task->approval_status)->toBe(TaskApprovalStatus::Approved);
    expect($task->status)->toBe(TaskStatus::Completed);
    expect($task->approved_by)->toBe($owner->id);
    expect(AuditLog::query()->where('event_type', 'task_approved')->exists())->toBeTrue();
});

test('task from another product returns 404', function () {
    [$organization, $owner] = makeTaskOrgWithOwner();
    $product = makeProductForTasks($organization, $owner);

    $otherProduct = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Other Product',
        'slug' => 'other-product',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
        'scope_reviewed_at' => now(),
        'scope_reviewed_by' => $owner->id,
        'classification_reviewed_at' => now(),
        'classification_reviewed_by' => $owner->id,
    ]);

    $task = Task::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $otherProduct->id,
        'title' => 'Foreign task',
        'status' => TaskStatus::Open,
        'priority' => TaskPriority::Low,
        'created_by' => $owner->id,
        'approval_status' => TaskApprovalStatus::NotRequired,
    ]);

    $this->actingAs($owner)
        ->get(route('products.tasks.edit', [$product, $task]))
        ->assertNotFound();
});

test('internal api lists tasks with search and pagination', function () {
    [$organization, $owner] = makeTaskOrgWithOwner();
    $product = makeProductForTasks($organization, $owner);

    Task::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Alpha remediation',
        'status' => TaskStatus::Open,
        'priority' => TaskPriority::Low,
        'created_by' => $owner->id,
        'approval_status' => TaskApprovalStatus::NotRequired,
    ]);

    Task::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Beta review',
        'status' => TaskStatus::InProgress,
        'priority' => TaskPriority::High,
        'created_by' => $owner->id,
        'approval_status' => TaskApprovalStatus::Pending,
    ]);

    $this->actingAs($owner)
        ->getJson(route('internal.products.tasks.index', [
            'product' => $product,
            'search' => 'Beta',
            'sort_by' => 'title',
            'sort_desc' => '0',
            'per_page' => 10,
            'page' => 1,
        ]))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.title', 'Beta review')
        ->assertJsonPath('data.0.approval_status', 'pending');
});
