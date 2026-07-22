<?php

use App\Enums\AuditEventType;
use App\Enums\ClassificationStatus;
use App\Enums\EvidenceType;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\UserSecurityInstructionSectionKey;
use App\Enums\UserSecurityInstructionStatus;
use App\Models\AuditLog;
use App\Models\Evidence;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\UserSecurityInstruction;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User, product: Product}
 */
function makeUsiEvidenceOrgWithOwner(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'USI Evidence Org',
        'slug' => 'usi-evidence-org-' . uniqid(),
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
        'name' => 'USI Evidence Product',
        'slug' => 'usi-evidence-product-' . uniqid(),
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    return compact('organization', 'owner', 'product');
}

function makeUsiEvidenceOrgViewer(Organization $organization): User
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

function publishableUsiInstruction(Product $product, User $owner): UserSecurityInstruction
{
    test()->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'title' => 'Security guide',
            'version_label' => '1.0',
            'locale' => 'en',
            'use_template' => true,
        ]);

    $instruction = UserSecurityInstruction::query()
        ->where('product_id', $product->id)
        ->firstOrFail()
        ->load('sections');

    $instruction->sections()->update([
        'is_applicable' => false,
        'body' => '',
    ]);
    $instruction->sections()
        ->where('section_key', UserSecurityInstructionSectionKey::SecureInstallation->value)
        ->update([
            'is_applicable' => true,
            'body' => 'Install from signed packages only.',
        ]);

    test()->actingAs($owner)
        ->post(route('products.security-instructions.publish', [$product, $instruction]))
        ->assertRedirect();

    return $instruction->fresh(['sections']);
}

test('published instructions can be published as product evidence', function () {
    Storage::fake('local');

    ['owner' => $owner, 'product' => $product] = makeUsiEvidenceOrgWithOwner();
    $instruction = publishableUsiInstruction($product, $owner);

    expect($instruction->status)->toBe(UserSecurityInstructionStatus::Published)
        ->and($instruction->evidence_id)->toBeNull();

    $this->actingAs($owner)
        ->post(route('products.security-instructions.publish-evidence', [$product, $instruction]))
        ->assertRedirect(route('products.security-instructions.edit', [$product, $instruction]));

    $instruction->refresh();

    expect($instruction->evidence_id)->not->toBeNull();

    $evidence = Evidence::query()->findOrFail($instruction->evidence_id);

    expect($evidence->product_id)->toBe($product->id)
        ->and($evidence->type)->toBe(EvidenceType::Document)
        ->and($evidence->source)->toBe('user_security_instruction:' . $instruction->id)
        ->and($evidence->source_filename)->toContain('user-security-instructions-v1.0-en')
        ->and($evidence->title)->toBe('Security guide (1.0)')
        ->and(Storage::disk('local')->get($evidence->storage_path))->toContain('Install from signed packages only.');

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::UserSecurityInstructionPublishedEvidence->value)
        ->where('product_id', $product->id)
        ->exists())->toBeTrue();

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::EvidenceCreated->value)
        ->where('product_id', $product->id)
        ->exists())->toBeTrue();

    $this->actingAs($owner)
        ->get(route('products.security-instructions.edit', [$product, $instruction]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/user-security-instructions/Edit')
            ->where('instruction.evidence_id', $evidence->id)
            ->where('instruction.evidence_title', $evidence->title));

    $this->actingAs($owner)
        ->post(route('products.security-instructions.publish-evidence', [$product, $instruction]))
        ->assertSessionHasErrors('evidence_id');
});

test('draft instructions cannot be published as evidence', function () {
    ['owner' => $owner, 'product' => $product] = makeUsiEvidenceOrgWithOwner();

    $this->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'title' => 'Draft only',
            'version_label' => '0.1',
            'locale' => 'en',
        ]);

    $instruction = UserSecurityInstruction::query()
        ->where('product_id', $product->id)
        ->firstOrFail();

    $this->actingAs($owner)
        ->post(route('products.security-instructions.publish-evidence', [$product, $instruction]))
        ->assertSessionHasErrors('status');

    expect($instruction->fresh()->evidence_id)->toBeNull();
});

test('viewer cannot publish instructions as evidence', function () {
    ['organization' => $organization, 'owner' => $owner, 'product' => $product] = makeUsiEvidenceOrgWithOwner();
    $viewer = makeUsiEvidenceOrgViewer($organization);
    $instruction = publishableUsiInstruction($product, $owner);

    $this->actingAs($viewer)
        ->post(route('products.security-instructions.publish-evidence', [$product, $instruction]))
        ->assertForbidden();

    expect($instruction->fresh()->evidence_id)->toBeNull();
});
