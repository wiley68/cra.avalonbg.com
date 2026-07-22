<?php

use App\Enums\AuditEventType;
use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\UserSecurityInstructionSectionKey;
use App\Enums\UserSecurityInstructionStatus;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\UserSecurityInstruction;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User, product: Product}
 */
function makeUsiOrgWithOwner(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'USI CRUD Org',
        'slug' => 'usi-crud-org-' . uniqid(),
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
        'name' => 'USI CRUD Product',
        'slug' => 'usi-crud-product-' . uniqid(),
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    return compact('organization', 'owner', 'product');
}

function makeUsiOrgViewer(Organization $organization): User
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

test('owner can view security instructions index', function () {
    ['owner' => $owner, 'product' => $product] = makeUsiOrgWithOwner();

    $this->actingAs($owner)
        ->get(route('products.security-instructions.index', $product))
        ->assertOk();
});

test('owner can create instructions with empty sections and audit is recorded', function () {
    ['organization' => $organization, 'owner' => $owner, 'product' => $product] = makeUsiOrgWithOwner();

    $response = $this->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'title' => 'Installer security guide',
            'version_label' => '1.0',
            'locale' => 'en',
            'notes' => 'Draft notes',
        ]);

    $instruction = UserSecurityInstruction::query()
        ->where('product_id', $product->id)
        ->firstOrFail();

    $response->assertRedirect(route('products.security-instructions.edit', [$product, $instruction]));

    expect($instruction->status)->toBe(UserSecurityInstructionStatus::Draft)
        ->and($instruction->organization_id)->toBe($organization->id)
        ->and($instruction->sections()->count())->toBe(count(UserSecurityInstructionSectionKey::cases()));

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::UserSecurityInstructionCreated)
        ->where('product_id', $product->id)
        ->exists())->toBeTrue();
});

test('owner can update section bodies', function () {
    ['owner' => $owner, 'product' => $product] = makeUsiOrgWithOwner();

    $this->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'title' => 'Guide',
            'version_label' => '1.0',
            'locale' => 'bg',
        ]);

    $instruction = UserSecurityInstruction::query()
        ->where('product_id', $product->id)
        ->firstOrFail()
        ->load('sections');

    $sections = $instruction->sections->map(fn($section) => [
        'section_key' => $section->section_key->value,
        'title_override' => $section->section_key === UserSecurityInstructionSectionKey::Logging
            ? 'Custom logging'
            : null,
        'body' => $section->section_key === UserSecurityInstructionSectionKey::Logging
            ? 'Enable audit logs.'
            : $section->body,
        'sort_order' => $section->sort_order,
        'is_applicable' => $section->section_key !== UserSecurityInstructionSectionKey::Backup,
    ])->all();

    $this->actingAs($owner)
        ->put(route('products.security-instructions.update', [$product, $instruction]), [
            'title' => 'Guide v2',
            'version_label' => '1.1',
            'locale' => 'bg',
            'notes' => null,
            'sections' => $sections,
        ])
        ->assertRedirect(route('products.security-instructions.edit', [$product, $instruction]));

    $instruction->refresh()->load('sections');

    expect($instruction->title)->toBe('Guide v2')
        ->and($instruction->version_label)->toBe('1.1');

    $logging = $instruction->sections
        ->firstWhere('section_key', UserSecurityInstructionSectionKey::Logging);
    $backup = $instruction->sections
        ->firstWhere('section_key', UserSecurityInstructionSectionKey::Backup);

    expect($logging?->body)->toBe('Enable audit logs.')
        ->and($logging?->title_override)->toBe('Custom logging')
        ->and($backup?->is_applicable)->toBeFalse();
});

test('viewer cannot create security instructions', function () {
    ['organization' => $organization, 'owner' => $owner, 'product' => $product] = makeUsiOrgWithOwner();
    $viewer = makeUsiOrgViewer($organization);

    $this->actingAs($viewer)
        ->post(route('products.security-instructions.store', $product), [
            'title' => 'Forbidden',
            'version_label' => '1.0',
            'locale' => 'en',
        ])
        ->assertForbidden();

    expect(UserSecurityInstruction::query()->where('product_id', $product->id)->count())->toBe(0);
});

test('viewer can view index but internal api is allowed for view', function () {
    ['organization' => $organization, 'owner' => $owner, 'product' => $product] = makeUsiOrgWithOwner();
    $viewer = makeUsiOrgViewer($organization);

    $this->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'title' => 'Visible guide',
            'version_label' => '1.0',
            'locale' => 'en',
        ]);

    $this->actingAs($viewer)
        ->get(route('products.security-instructions.index', $product))
        ->assertOk();

    $this->actingAs($viewer)
        ->getJson(route('internal.products.security-instructions.index', $product))
        ->assertOk()
        ->assertJsonPath('total', 1);
});

test('owner can delete draft instructions', function () {
    ['owner' => $owner, 'product' => $product] = makeUsiOrgWithOwner();

    $this->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'title' => 'To delete',
            'version_label' => '1.0',
            'locale' => 'en',
        ]);

    $instruction = UserSecurityInstruction::query()
        ->where('product_id', $product->id)
        ->firstOrFail();

    $this->actingAs($owner)
        ->delete(route('products.security-instructions.destroy', [$product, $instruction]))
        ->assertRedirect(route('products.security-instructions.index', $product));

    expect(UserSecurityInstruction::query()->whereKey($instruction->id)->exists())->toBeFalse();
});
