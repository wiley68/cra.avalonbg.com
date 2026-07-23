<?php

use App\Enums\AuditEventType;
use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\TaskStatus;
use App\Enums\TechnicalDocumentationSectionKey;
use App\Enums\TechnicalDocumentationSectionSource;
use App\Enums\TechnicalDocumentationStatus;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Role;
use App\Models\Task;
use App\Models\TechnicalDocumentationPackage;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User, product: Product}
 */
function makeTechDocLifecycleOrg(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Tech Doc Lifecycle Org',
        'slug' => 'tech-doc-lifecycle-org-' . uniqid(),
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

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Tech Doc Lifecycle Product',
        'slug' => 'tech-doc-lifecycle-product-' . uniqid(),
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    return compact('organization', 'owner', 'product');
}

function makeTechDocLifecycleViewer(Organization $organization): User
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

function createTechDocDraftPackage(User $owner, Product $product, string $title = 'Lifecycle package'): TechnicalDocumentationPackage
{
    test()->actingAs($owner)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => $title,
            'version_label' => '1.0',
            'locale' => 'en',
        ])
        ->assertRedirect();

    return TechnicalDocumentationPackage::query()
        ->where('product_id', $product->id)
        ->where('title', $title)
        ->firstOrFail()
        ->load('sections');
}

function prepareTechDocPackageForPublish(TechnicalDocumentationPackage $package): void
{
    $package->sections()
        ->whereIn('source', [
            TechnicalDocumentationSectionSource::Authored->value,
            TechnicalDocumentationSectionSource::Linked->value,
        ])
        ->update([
            'is_applicable' => false,
            'override_reason' => 'Not required for lifecycle smoke test.',
            'body_markdown' => null,
        ]);

    $package->sections()
        ->where('section_key', TechnicalDocumentationSectionKey::Architecture->value)
        ->update([
            'is_applicable' => true,
            'override_reason' => null,
            'body_markdown' => '## Architecture\n\nTrust boundaries documented.',
        ]);
}

test('owner can submit technical documentation for review and create a task', function () {
    ['owner' => $owner, 'product' => $product] = makeTechDocLifecycleOrg();
    $package = createTechDocDraftPackage($owner, $product, 'Review me');

    $this->actingAs($owner)
        ->post(route('products.technical-documentation.submit-review', [$product, $package]))
        ->assertRedirect(route('products.technical-documentation.edit', [$product, $package]));

    expect($package->fresh()->status)->toBe(TechnicalDocumentationStatus::UnderReview);

    $task = Task::query()
        ->where('product_id', $product->id)
        ->where('subject_type', TechnicalDocumentationPackage::class)
        ->where('subject_id', $package->id)
        ->first();

    expect($task)->not->toBeNull()
        ->and($task->status)->toBe(TaskStatus::Open)
        ->and($task->assignee_user_id)->toBe($owner->id)
        ->and($task->title)->toContain('Review me');

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::TechnicalDocumentationSubmitted)
        ->where('product_id', $product->id)
        ->exists())->toBeTrue();
});

test('owner can publish then retire and previous published is retired on republish', function () {
    ['owner' => $owner, 'product' => $product] = makeTechDocLifecycleOrg();

    $first = createTechDocDraftPackage($owner, $product, 'First published');
    prepareTechDocPackageForPublish($first);

    $this->actingAs($owner)
        ->post(route('products.technical-documentation.publish', [$product, $first]))
        ->assertRedirect(route('products.technical-documentation.edit', [$product, $first]));

    $first->refresh();
    expect($first->status)->toBe(TechnicalDocumentationStatus::Published)
        ->and($first->published_at)->not->toBeNull()
        ->and($first->published_by)->toBe($owner->id)
        ->and($first->isEditable())->toBeFalse();

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::TechnicalDocumentationPublished)
        ->where('product_id', $product->id)
        ->exists())->toBeTrue();

    $second = createTechDocDraftPackage($owner, $product, 'Second published');
    $second->update(['version_label' => '2.0']);
    prepareTechDocPackageForPublish($second);

    $this->actingAs($owner)
        ->post(route('products.technical-documentation.publish', [$product, $second]))
        ->assertRedirect();

    expect($first->fresh()->status)->toBe(TechnicalDocumentationStatus::Retired)
        ->and($second->fresh()->status)->toBe(TechnicalDocumentationStatus::Published);

    $this->actingAs($owner)
        ->post(route('products.technical-documentation.retire', [$product, $second]))
        ->assertRedirect(route('products.technical-documentation.edit', [$product, $second]));

    expect($second->fresh()->status)->toBe(TechnicalDocumentationStatus::Retired);

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::TechnicalDocumentationRetired)
        ->where('product_id', $product->id)
        ->exists())->toBeTrue();
});

test('publish completes open review task', function () {
    ['owner' => $owner, 'product' => $product] = makeTechDocLifecycleOrg();
    $package = createTechDocDraftPackage($owner, $product, 'Publish after review');

    $this->actingAs($owner)
        ->post(route('products.technical-documentation.submit-review', [$product, $package]))
        ->assertRedirect();

    prepareTechDocPackageForPublish($package->fresh());

    $this->actingAs($owner)
        ->post(route('products.technical-documentation.publish', [$product, $package]))
        ->assertRedirect();

    $task = Task::query()
        ->where('subject_type', TechnicalDocumentationPackage::class)
        ->where('subject_id', $package->id)
        ->firstOrFail();

    expect($task->status)->toBe(TaskStatus::Completed)
        ->and($package->fresh()->status)->toBe(TechnicalDocumentationStatus::Published);
});

test('publish rejects incomplete authored sections', function () {
    ['owner' => $owner, 'product' => $product] = makeTechDocLifecycleOrg();
    $package = createTechDocDraftPackage($owner, $product, 'Incomplete publish');

    $this->actingAs($owner)
        ->post(route('products.technical-documentation.publish', [$product, $package]))
        ->assertSessionHasErrors('sections');

    expect($package->fresh()->status)->toBe(TechnicalDocumentationStatus::Draft);
});

test('viewer cannot submit publish or retire technical documentation', function () {
    ['organization' => $organization, 'owner' => $owner, 'product' => $product] = makeTechDocLifecycleOrg();
    $viewer = makeTechDocLifecycleViewer($organization);
    $package = createTechDocDraftPackage($owner, $product, 'Forbidden lifecycle');
    prepareTechDocPackageForPublish($package);

    $this->actingAs($viewer)
        ->post(route('products.technical-documentation.submit-review', [$product, $package]))
        ->assertForbidden();

    $this->actingAs($viewer)
        ->post(route('products.technical-documentation.publish', [$product, $package]))
        ->assertForbidden();

    $this->actingAs($owner)
        ->post(route('products.technical-documentation.publish', [$product, $package]))
        ->assertRedirect();

    $this->actingAs($viewer)
        ->post(route('products.technical-documentation.retire', [$product, $package]))
        ->assertForbidden();
});

test('edit page exposes lifecycle props', function () {
    ['owner' => $owner, 'product' => $product] = makeTechDocLifecycleOrg();
    $package = createTechDocDraftPackage($owner, $product, 'Lifecycle props');

    $this->actingAs($owner)
        ->get(route('products.technical-documentation.edit', [$product, $package]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/technical-documentation/Edit')
            ->has('memberOptions')
            ->where('reviewTask', null)
            ->where('package.status', 'draft'));
});

test('published package cannot be updated deleted or refreshed', function () {
    ['owner' => $owner, 'product' => $product] = makeTechDocLifecycleOrg();
    $package = createTechDocDraftPackage($owner, $product, 'Locked package');
    prepareTechDocPackageForPublish($package);

    $this->actingAs($owner)
        ->post(route('products.technical-documentation.publish', [$product, $package]))
        ->assertRedirect();

    $package->refresh()->load('sections');

    $this->actingAs($owner)
        ->put(route('products.technical-documentation.update', [$product, $package]), [
            'title' => 'Should stay locked',
            'version_label' => '1.0',
            'locale' => 'en',
            'sections' => $package->sections->map(fn($section) => [
                'section_key' => $section->section_key->value,
                'body_markdown' => $section->body_markdown,
                'is_applicable' => $section->is_applicable,
                'override_reason' => $section->override_reason,
                'sort_order' => $section->sort_order,
            ])->all(),
        ])
        ->assertSessionHasErrors('status');

    expect($package->fresh()->title)->toBe('Locked package');

    $this->actingAs($owner)
        ->post(route('products.technical-documentation.refresh-generated', [$product, $package]))
        ->assertSessionHasErrors('status');

    $this->actingAs($owner)
        ->delete(route('products.technical-documentation.destroy', [$product, $package]))
        ->assertForbidden();

    expect(TechnicalDocumentationPackage::query()->whereKey($package->id)->exists())->toBeTrue();
});

test('generated markdown uses package locale labels', function () {
    ['owner' => $owner, 'product' => $product] = makeTechDocLifecycleOrg();

    $product->update(['manufacturer' => 'Avalon Labs']);

    $this->actingAs($owner)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => 'BG locale package',
            'version_label' => '1.0',
            'locale' => 'bg',
        ])
        ->assertRedirect();

    $package = TechnicalDocumentationPackage::query()
        ->where('product_id', $product->id)
        ->where('title', 'BG locale package')
        ->firstOrFail()
        ->load('sections');

    $identification = $package->sections
        ->firstWhere('section_key', TechnicalDocumentationSectionKey::ProductIdentification);

    expect($identification?->generated_payload['markdown'] ?? '')->toContain('Производител')
        ->and($identification?->generated_payload['markdown'] ?? '')->toContain('Avalon Labs')
        ->and($identification?->generated_payload['markdown'] ?? '')->not->toContain('**Manufacturer:**');
});
