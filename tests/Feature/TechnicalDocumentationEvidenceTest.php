<?php

use App\Enums\AuditEventType;
use App\Enums\ClassificationStatus;
use App\Enums\EvidenceType;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\TechnicalDocumentationSectionKey;
use App\Enums\TechnicalDocumentationSectionSource;
use App\Enums\TechnicalDocumentationStatus;
use App\Models\AuditLog;
use App\Models\Evidence;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Role;
use App\Models\TechnicalDocumentationPackage;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User, product: Product}
 */
function makeTechDocEvidenceOrgWithOwner(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Tech Doc Evidence Org',
        'slug' => 'tech-doc-evidence-org-' . uniqid(),
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
        'name' => 'Tech Doc Evidence Product',
        'slug' => 'tech-doc-evidence-product-' . uniqid(),
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    return compact('organization', 'owner', 'product');
}

function makeTechDocEvidenceOrgViewer(Organization $organization): User
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

function publishableTechDocPackage(Product $product, User $owner): TechnicalDocumentationPackage
{
    test()->actingAs($owner)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => 'Architecture pack',
            'version_label' => '1.0',
            'locale' => 'en',
            'inherit_from_previous' => false,
        ]);

    $package = TechnicalDocumentationPackage::query()
        ->where('product_id', $product->id)
        ->firstOrFail()
        ->load('sections');

    $package->sections()
        ->whereIn('source', [
            TechnicalDocumentationSectionSource::Authored->value,
            TechnicalDocumentationSectionSource::Linked->value,
        ])
        ->update([
            'is_applicable' => false,
            'override_reason' => 'Not required for evidence smoke test.',
            'body_markdown' => null,
        ]);

    $package->sections()
        ->where('section_key', TechnicalDocumentationSectionKey::Architecture->value)
        ->update([
            'is_applicable' => true,
            'override_reason' => null,
            'body_markdown' => "## Architecture\n\nTrust boundaries documented.",
        ]);

    test()->actingAs($owner)
        ->post(route('products.technical-documentation.publish', [$product, $package]))
        ->assertRedirect();

    return $package->fresh(['sections']);
}

test('published technical documentation can be published as product evidence', function () {
    Storage::fake('local');

    ['owner' => $owner, 'product' => $product] = makeTechDocEvidenceOrgWithOwner();
    $package = publishableTechDocPackage($product, $owner);

    expect($package->status)->toBe(TechnicalDocumentationStatus::Published)
        ->and($package->evidence_id)->toBeNull();

    $this->actingAs($owner)
        ->post(route('products.technical-documentation.publish-evidence', [$product, $package]))
        ->assertRedirect(route('products.technical-documentation.edit', [$product, $package]));

    $package->refresh();

    expect($package->evidence_id)->not->toBeNull();

    $evidence = Evidence::query()->findOrFail($package->evidence_id);

    expect($evidence->product_id)->toBe($product->id)
        ->and($evidence->type)->toBe(EvidenceType::Document)
        ->and($evidence->source)->toBe('technical_documentation:' . $package->id)
        ->and($evidence->source_filename)->toContain('technical-documentation-v1.0-en')
        ->and($evidence->title)->toBe('Architecture pack (1.0)')
        ->and(Storage::disk('local')->get($evidence->storage_path))->toContain('Trust boundaries documented.');

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::TechnicalDocumentationPublishedEvidence->value)
        ->where('product_id', $product->id)
        ->exists())->toBeTrue();

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::EvidenceCreated->value)
        ->where('product_id', $product->id)
        ->exists())->toBeTrue();

    $this->actingAs($owner)
        ->get(route('products.technical-documentation.edit', [$product, $package]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/technical-documentation/Edit')
            ->where('package.evidence_id', $evidence->id)
            ->where('package.evidence_title', $evidence->title));

    $this->actingAs($owner)
        ->post(route('products.technical-documentation.publish-evidence', [$product, $package]))
        ->assertSessionHasErrors('evidence_id');
});

test('draft technical documentation cannot be published as evidence', function () {
    ['owner' => $owner, 'product' => $product] = makeTechDocEvidenceOrgWithOwner();

    $this->actingAs($owner)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => 'Draft only',
            'version_label' => '0.1',
            'locale' => 'en',
            'inherit_from_previous' => false,
        ]);

    $package = TechnicalDocumentationPackage::query()
        ->where('product_id', $product->id)
        ->firstOrFail();

    $this->actingAs($owner)
        ->post(route('products.technical-documentation.publish-evidence', [$product, $package]))
        ->assertSessionHasErrors('status');

    expect($package->fresh()->evidence_id)->toBeNull();
});

test('viewer cannot publish technical documentation as evidence', function () {
    ['organization' => $organization, 'owner' => $owner, 'product' => $product] = makeTechDocEvidenceOrgWithOwner();
    $viewer = makeTechDocEvidenceOrgViewer($organization);
    $package = publishableTechDocPackage($product, $owner);

    $this->actingAs($viewer)
        ->post(route('products.technical-documentation.publish-evidence', [$product, $package]))
        ->assertForbidden();

    expect($package->fresh()->evidence_id)->toBeNull();
});
