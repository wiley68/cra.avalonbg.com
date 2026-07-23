<?php

use App\Enums\AuditEventType;
use App\Enums\ClassificationStatus;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ProductVersionState;
use App\Enums\ScopeStatus;
use App\Enums\SupportStatus;
use App\Enums\TaskApprovalStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductIncident;
use App\Models\ProductVersion;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{0: Organization, 1: User, 2: Product, 3: ProductIncident}
 */
function makeIncidentTaskAuditFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Incident Task Org',
        'slug' => 'incident-task-org-' . uniqid(),
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
        'name' => 'Incident Task Product',
        'slug' => 'incident-task-product-' . uniqid(),
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

    ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '1.0.0',
        'state' => ProductVersionState::Draft,
        'support_status' => SupportStatus::Supported,
    ]);

    $incident = ProductIncident::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Task-linked incident',
        'status' => IncidentStatus::Open,
        'severity' => IncidentSeverity::High,
    ]);

    return [$organization, $owner, $product, $incident];
}

test('owner can create task with incident subject', function () {
    [, $owner, $product, $incident] = makeIncidentTaskAuditFixture();

    $this->actingAs($owner)
        ->post(route('products.tasks.store', $product), [
            'title' => 'Investigate incident',
            'description' => 'Follow up on containment.',
            'status' => TaskStatus::Open->value,
            'priority' => TaskPriority::High->value,
            'approval_status' => TaskApprovalStatus::NotRequired->value,
            'subject_type' => 'incident',
            'subject_id' => $incident->id,
        ])
        ->assertRedirect();

    $task = Task::query()
        ->where('product_id', $product->id)
        ->where('title', 'Investigate incident')
        ->firstOrFail();

    expect($task->subject_type)->toBe(ProductIncident::class)
        ->and($task->subject_id)->toBe($incident->id);
});

test('incident create update status and timeline write audit events', function () {
    [, $owner, $product] = makeIncidentTaskAuditFixture();

    $this->actingAs($owner)
        ->post(route('products.incidents.store', $product), [
            'title' => 'Audited incident',
            'status' => IncidentStatus::Open->value,
            'severity' => IncidentSeverity::Medium->value,
            'summary' => 'Initial report',
        ])
        ->assertRedirect();

    $incident = ProductIncident::query()
        ->where('product_id', $product->id)
        ->where('title', 'Audited incident')
        ->firstOrFail();

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::IncidentCreated->value)
        ->where('product_id', $product->id)
        ->exists())->toBeTrue();

    $this->actingAs($owner)
        ->put(route('products.incidents.update', [$product, $incident]), [
            'title' => 'Audited incident',
            'status' => IncidentStatus::Open->value,
            'severity' => IncidentSeverity::High->value,
            'summary' => 'Updated summary',
        ])
        ->assertRedirect();

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::IncidentUpdated->value)
        ->where('product_id', $product->id)
        ->exists())->toBeTrue();

    $this->actingAs($owner)
        ->put(route('products.incidents.update', [$product, $incident]), [
            'title' => 'Audited incident',
            'status' => IncidentStatus::Investigating->value,
            'severity' => IncidentSeverity::High->value,
            'summary' => 'Updated summary',
        ])
        ->assertRedirect();

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::IncidentStatusUpdated->value)
        ->where('product_id', $product->id)
        ->exists())->toBeTrue();

    $this->actingAs($owner)
        ->post(route('products.incidents.timeline.store', [$product, $incident]), [
            'occurred_at' => '2026-07-22T12:00',
            'label' => 'Containment started',
            'notes' => 'Blocked suspicious IPs',
        ])
        ->assertRedirect();

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::IncidentTimelineEventAdded->value)
        ->where('product_id', $product->id)
        ->exists())->toBeTrue();

    $this->actingAs($owner)
        ->delete(route('products.incidents.destroy', [$product, $incident]))
        ->assertRedirect(route('products.incidents.index', $product));

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::IncidentDeleted->value)
        ->where('product_id', $product->id)
        ->exists())->toBeTrue();
});
