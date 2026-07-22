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
 * @return array{
 *     organization: Organization,
 *     owner: User,
 *     viewer: User,
 *     product: Product,
 *     draft: UserSecurityInstruction,
 *     published: UserSecurityInstruction
 * }
 */
function makeUsiRbacFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'USI RBAC Org',
        'slug' => 'usi-rbac-org-' . uniqid(),
        'is_active' => true,
        'locale' => 'en',
    ]);

    $owner = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $viewer = User::factory()->create([
        'email_verified_at' => now(),
        'is_platform_admin' => false,
        'must_change_password' => false,
        'two_factor_confirmed_at' => now(),
    ]);

    $ownerRole = Role::query()->where('slug', 'organization_owner')->firstOrFail();
    $viewerRole = Role::query()->where('slug', 'read_only')->firstOrFail();

    $organization->users()->attach($owner->id, [
        'role_id' => $ownerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $organization->users()->attach($viewer->id, [
        'role_id' => $viewerRole->id,
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'USI RBAC Product',
        'slug' => 'usi-rbac-product-' . uniqid(),
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    test()->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'title' => 'Draft guide',
            'version_label' => '0.1',
            'locale' => 'en',
        ])
        ->assertRedirect();

    $draft = UserSecurityInstruction::query()
        ->where('product_id', $product->id)
        ->where('title', 'Draft guide')
        ->firstOrFail()
        ->load('sections');

    test()->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'use_template' => true,
            'locale' => 'en',
        ])
        ->assertRedirect();

    $published = UserSecurityInstruction::query()
        ->where('product_id', $product->id)
        ->where('title', 'User security instructions')
        ->firstOrFail()
        ->load('sections');

    $published->sections()->update(['is_applicable' => false, 'body' => '']);
    $published->sections()
        ->where('section_key', UserSecurityInstructionSectionKey::SecureInstallation->value)
        ->update([
            'is_applicable' => true,
            'body' => 'Install securely.',
        ]);

    test()->actingAs($owner)
        ->post(route('products.security-instructions.publish', [$product, $published]))
        ->assertRedirect();

    $published->refresh();

    return compact('organization', 'owner', 'viewer', 'product', 'draft', 'published');
}

test('owner can open create and edit pages with expected inertia props', function () {
    ['owner' => $owner, 'product' => $product, 'draft' => $draft] = makeUsiRbacFixture();

    $this->actingAs($owner)
        ->get(route('products.security-instructions.create', $product))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/user-security-instructions/Create')
            ->has('options.section_keys')
            ->has('options.locales'));

    $this->actingAs($owner)
        ->get(route('products.security-instructions.edit', [$product, $draft]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/user-security-instructions/Edit')
            ->where('canManage', true)
            ->where('instruction.title', 'Draft guide')
            ->has('instruction.sections'));
});

test('owner create update delete write audit events', function () {
    ['owner' => $owner, 'product' => $product] = makeUsiRbacFixture();

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::UserSecurityInstructionCreated->value)
        ->where('product_id', $product->id)
        ->exists())->toBeTrue();

    $this->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'title' => 'Audit update target',
            'version_label' => '1.0',
            'locale' => 'en',
        ]);

    $instruction = UserSecurityInstruction::query()
        ->where('product_id', $product->id)
        ->where('title', 'Audit update target')
        ->firstOrFail()
        ->load('sections');

    $sections = $instruction->sections->map(fn($section) => [
        'section_key' => $section->section_key->value,
        'title_override' => null,
        'body' => $section->body,
        'sort_order' => $section->sort_order,
        'is_applicable' => $section->is_applicable,
    ])->all();

    $this->actingAs($owner)
        ->put(route('products.security-instructions.update', [$product, $instruction]), [
            'title' => 'Audit update target v2',
            'version_label' => '1.1',
            'locale' => 'en',
            'notes' => 'note',
            'sections' => $sections,
        ])
        ->assertRedirect();

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::UserSecurityInstructionUpdated->value)
        ->where('product_id', $product->id)
        ->exists())->toBeTrue();

    $this->actingAs($owner)
        ->delete(route('products.security-instructions.destroy', [$product, $instruction]))
        ->assertRedirect();

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::UserSecurityInstructionDeleted->value)
        ->where('product_id', $product->id)
        ->exists())->toBeTrue();
});

test('viewer can view index and edit pages but cannot manage', function () {
    [
        'viewer' => $viewer,
        'product' => $product,
        'draft' => $draft,
        'published' => $published,
    ] = makeUsiRbacFixture();

    $this->actingAs($viewer)
        ->get(route('products.security-instructions.index', $product))
        ->assertOk()
        ->assertInertia(fn($page) => $page->where('canManage', false));

    $this->actingAs($viewer)
        ->get(route('products.security-instructions.edit', [$product, $draft]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->where('canManage', false)
            ->where('instruction.is_editable', true));

    $this->actingAs($viewer)
        ->get(route('products.security-instructions.edit', [$product, $published]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->where('canManage', false)
            ->where('instruction.is_editable', false));

    $this->actingAs($viewer)
        ->get(route('products.security-instructions.create', $product))
        ->assertForbidden();

    $this->actingAs($viewer)
        ->getJson(route('products.security-instructions.template', $product) . '?locale=en')
        ->assertForbidden();

    $sections = $draft->sections->map(fn($section) => [
        'section_key' => $section->section_key->value,
        'title_override' => null,
        'body' => 'Hacked',
        'sort_order' => $section->sort_order,
        'is_applicable' => true,
    ])->all();

    $this->actingAs($viewer)
        ->put(route('products.security-instructions.update', [$product, $draft]), [
            'title' => 'Hacked title',
            'version_label' => '9.9',
            'locale' => 'en',
            'notes' => null,
            'sections' => $sections,
        ])
        ->assertForbidden();

    expect($draft->fresh()->title)->toBe('Draft guide');

    $this->actingAs($viewer)
        ->delete(route('products.security-instructions.destroy', [$product, $draft]))
        ->assertForbidden();

    expect(UserSecurityInstruction::query()->whereKey($draft->id)->exists())->toBeTrue();

    $this->actingAs($viewer)
        ->post(route('products.security-instructions.submit-review', [$product, $draft]))
        ->assertForbidden();

    expect($draft->fresh()->status)->toBe(UserSecurityInstructionStatus::Draft);

    $this->actingAs($viewer)
        ->post(route('products.security-instructions.retire', [$product, $published]))
        ->assertForbidden();

    expect($published->fresh()->status)->toBe(UserSecurityInstructionStatus::Published);

    $this->actingAs($viewer)
        ->get(route('products.security-instructions.export', [
            'product' => $product,
            'instruction' => $draft,
            'format' => 'html',
        ]))
        ->assertForbidden();

    $this->actingAs($viewer)
        ->get(route('products.security-instructions.export', [
            'product' => $product,
            'instruction' => $published,
            'format' => 'html',
        ]))
        ->assertOk();
});
