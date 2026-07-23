<?php

use App\Enums\AuditEventType;
use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\TaskStatus;
use App\Enums\UserSecurityInstructionSectionKey;
use App\Enums\UserSecurityInstructionStatus;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Models\UserSecurityInstruction;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User, reviewer: User, product: Product}
 */
function makeUsiReviewTaskFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'USI Review Task Org',
        'slug' => 'usi-review-task-org-' . uniqid(),
        'is_active' => true,
        'locale' => 'en',
    ]);

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $reviewer = User::factory()->create([
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
    $organization->users()->attach($reviewer->id, [
        'role_id' => $ownerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'USI Review Task Product',
        'slug' => 'usi-review-task-product-' . uniqid(),
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    return compact('organization', 'owner', 'reviewer', 'product');
}

test('submit for review creates an open product task assigned to the actor by default', function () {
    ['owner' => $owner, 'product' => $product] = makeUsiReviewTaskFixture();

    $this->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'title' => 'Review me',
            'version_label' => '1.0',
            'locale' => 'en',
        ])
        ->assertRedirect();

    $instruction = UserSecurityInstruction::query()
        ->where('product_id', $product->id)
        ->firstOrFail();

    $this->actingAs($owner)
        ->post(route('products.security-instructions.submit-review', [$product, $instruction]))
        ->assertRedirect(route('products.security-instructions.edit', [$product, $instruction]));

    expect($instruction->fresh()->status)->toBe(UserSecurityInstructionStatus::UnderReview);

    $task = Task::query()
        ->where('product_id', $product->id)
        ->where('subject_type', UserSecurityInstruction::class)
        ->where('subject_id', $instruction->id)
        ->first();

    expect($task)->not->toBeNull()
        ->and($task->status)->toBe(TaskStatus::Open)
        ->and($task->assignee_user_id)->toBe($owner->id)
        ->and($task->title)->toContain('Review me');

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::UserSecurityInstructionSubmitted->value)
        ->where('product_id', $product->id)
        ->exists())->toBeTrue();

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::TaskCreated->value)
        ->where('product_id', $product->id)
        ->exists())->toBeTrue();

    $this->actingAs($owner)
        ->get(route('products.security-instructions.edit', [$product, $instruction]))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('products/user-security-instructions/Edit')
            ->where('reviewTask.id', $task->id)
            ->where('reviewTask.product_id', $product->id)
            ->has('memberOptions'));
});

test('submit for review can assign a different org member', function () {
    ['owner' => $owner, 'reviewer' => $reviewer, 'product' => $product] = makeUsiReviewTaskFixture();

    $this->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'title' => 'Assign reviewer',
            'version_label' => '1.0',
            'locale' => 'en',
        ]);

    $instruction = UserSecurityInstruction::query()
        ->where('title', 'Assign reviewer')
        ->firstOrFail();

    $this->actingAs($owner)
        ->post(route('products.security-instructions.submit-review', [$product, $instruction]), [
            'assignee_user_id' => $reviewer->id,
        ])
        ->assertRedirect();

    $task = Task::query()
        ->where('subject_type', UserSecurityInstruction::class)
        ->where('subject_id', $instruction->id)
        ->firstOrFail();

    expect($task->assignee_user_id)->toBe($reviewer->id);
});

test('publish completes open review tasks for the instruction', function () {
    ['owner' => $owner, 'product' => $product] = makeUsiReviewTaskFixture();

    $this->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'title' => 'Publish closes task',
            'version_label' => '1.0',
            'locale' => 'en',
        ]);

    $instruction = UserSecurityInstruction::query()
        ->where('title', 'Publish closes task')
        ->firstOrFail();

    $this->actingAs($owner)
        ->post(route('products.security-instructions.submit-review', [$product, $instruction]))
        ->assertRedirect();

    $task = Task::query()
        ->where('subject_type', UserSecurityInstruction::class)
        ->where('subject_id', $instruction->id)
        ->firstOrFail();

    expect($task->status)->toBe(TaskStatus::Open);

    $instruction->sections()->update(['is_applicable' => false, 'body' => '']);
    $instruction->sections()
        ->where('section_key', UserSecurityInstructionSectionKey::SecureInstallation->value)
        ->update([
            'is_applicable' => true,
            'body' => 'Install securely.',
        ]);

    $this->actingAs($owner)
        ->post(route('products.security-instructions.publish', [$product, $instruction]))
        ->assertRedirect();

    expect($instruction->fresh()->status)->toBe(UserSecurityInstructionStatus::Published)
        ->and($task->fresh()->status)->toBe(TaskStatus::Completed);
});

test('submit for review rejects assignee outside the organization', function () {
    ['owner' => $owner, 'product' => $product] = makeUsiReviewTaskFixture();

    $outsider = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $this->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'title' => 'Bad assignee',
            'version_label' => '1.0',
            'locale' => 'en',
        ]);

    $instruction = UserSecurityInstruction::query()
        ->where('title', 'Bad assignee')
        ->firstOrFail();

    $this->actingAs($owner)
        ->from(route('products.security-instructions.edit', [$product, $instruction]))
        ->post(route('products.security-instructions.submit-review', [$product, $instruction]), [
            'assignee_user_id' => $outsider->id,
        ])
        ->assertRedirect(route('products.security-instructions.edit', [$product, $instruction]))
        ->assertSessionHasErrors('assignee_user_id');

    expect($instruction->fresh()->status)->toBe(UserSecurityInstructionStatus::Draft)
        ->and(Task::query()->where('subject_id', $instruction->id)->exists())->toBeFalse();
});
