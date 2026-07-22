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

test('owner can create instructions from EN template with prefilled sections', function () {
    ['owner' => $owner, 'product' => $product] = makeUsiOrgWithOwner();

    $this->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'use_template' => true,
            'locale' => 'en',
            'title' => '',
            'version_label' => '',
        ])
        ->assertRedirect();

    $instruction = UserSecurityInstruction::query()
        ->where('product_id', $product->id)
        ->firstOrFail()
        ->load('sections');

    expect($instruction->title)->toBe('User security instructions')
        ->and($instruction->version_label)->toBe('1.0')
        ->and($instruction->locale)->toBe('en')
        ->and($instruction->sections)->toHaveCount(14);

    $install = $instruction->sections
        ->firstWhere('section_key', UserSecurityInstructionSectionKey::SecureInstallation);

    expect($install?->body)->toContain('Secure installation')
        ->and($install?->body)->not->toBe('');
});

test('owner can create instructions from BG template', function () {
    ['owner' => $owner, 'product' => $product] = makeUsiOrgWithOwner();

    $this->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'use_template' => true,
            'locale' => 'bg',
        ])
        ->assertRedirect();

    $instruction = UserSecurityInstruction::query()
        ->where('product_id', $product->id)
        ->firstOrFail()
        ->load('sections');

    expect($instruction->title)->toBe('Инструкции за сигурност на потребителя');

    $logging = $instruction->sections
        ->firstWhere('section_key', UserSecurityInstructionSectionKey::Logging);

    expect($logging?->body)->toContain('Журналиране');
});

test('template endpoint returns sections for locale', function () {
    ['owner' => $owner, 'product' => $product] = makeUsiOrgWithOwner();

    $this->actingAs($owner)
        ->getJson(route('products.security-instructions.template', $product) . '?locale=en')
        ->assertOk()
        ->assertJsonPath('title', 'User security instructions')
        ->assertJsonCount(14, 'sections');
});

test('create without template still requires title', function () {
    ['owner' => $owner, 'product' => $product] = makeUsiOrgWithOwner();

    $this->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'use_template' => false,
            'locale' => 'en',
            'title' => '',
            'version_label' => '',
        ])
        ->assertSessionHasErrors(['title', 'version_label']);
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

test('owner can submit draft for review', function () {
    ['owner' => $owner, 'product' => $product] = makeUsiOrgWithOwner();

    $this->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'title' => 'Review me',
            'version_label' => '1.0',
            'locale' => 'en',
        ]);

    $instruction = UserSecurityInstruction::query()
        ->where('product_id', $product->id)
        ->firstOrFail();

    $this->actingAs($owner)
        ->post(route('products.security-instructions.submit-review', [$product, $instruction]))
        ->assertRedirect(route('products.security-instructions.edit', [$product, $instruction]));

    expect($instruction->fresh()->status)->toBe(UserSecurityInstructionStatus::UnderReview);

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::UserSecurityInstructionSubmitted)
        ->where('product_id', $product->id)
        ->exists())->toBeTrue();
});

test('publish requires applicable section bodies', function () {
    ['owner' => $owner, 'product' => $product] = makeUsiOrgWithOwner();

    $this->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'title' => 'Incomplete',
            'version_label' => '1.0',
            'locale' => 'en',
        ]);

    $instruction = UserSecurityInstruction::query()
        ->where('product_id', $product->id)
        ->firstOrFail();

    $this->actingAs($owner)
        ->post(route('products.security-instructions.publish', [$product, $instruction]))
        ->assertSessionHasErrors('sections');

    expect($instruction->fresh()->status)->toBe(UserSecurityInstructionStatus::Draft);
});

test('owner can publish then retire and previous published is retired on republish', function () {
    ['owner' => $owner, 'product' => $product] = makeUsiOrgWithOwner();

    $this->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'title' => 'First published',
            'version_label' => '1.0',
            'locale' => 'en',
        ]);

    $first = UserSecurityInstruction::query()
        ->where('product_id', $product->id)
        ->firstOrFail()
        ->load('sections');

    $first->sections()->update([
        'is_applicable' => false,
        'body' => '',
    ]);
    $first->sections()
        ->where('section_key', UserSecurityInstructionSectionKey::SecureInstallation->value)
        ->update([
            'is_applicable' => true,
            'body' => 'Install securely.',
        ]);

    $this->actingAs($owner)
        ->post(route('products.security-instructions.publish', [$product, $first]))
        ->assertRedirect(route('products.security-instructions.edit', [$product, $first]));

    $first->refresh();
    expect($first->status)->toBe(UserSecurityInstructionStatus::Published)
        ->and($first->published_at)->not->toBeNull()
        ->and($first->published_by)->toBe($owner->id)
        ->and($first->isEditable())->toBeFalse();

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::UserSecurityInstructionPublished)
        ->where('product_id', $product->id)
        ->exists())->toBeTrue();

    $this->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'title' => 'Second published',
            'version_label' => '2.0',
            'locale' => 'en',
        ]);

    $second = UserSecurityInstruction::query()
        ->where('product_id', $product->id)
        ->where('title', 'Second published')
        ->firstOrFail();

    $second->sections()->update(['is_applicable' => false, 'body' => '']);
    $second->sections()
        ->where('section_key', UserSecurityInstructionSectionKey::Logging->value)
        ->update(['is_applicable' => true, 'body' => 'Log everything.']);

    $this->actingAs($owner)
        ->post(route('products.security-instructions.publish', [$product, $second]))
        ->assertRedirect();

    expect($first->fresh()->status)->toBe(UserSecurityInstructionStatus::Retired)
        ->and($second->fresh()->status)->toBe(UserSecurityInstructionStatus::Published);

    $this->actingAs($owner)
        ->post(route('products.security-instructions.retire', [$product, $second]))
        ->assertRedirect(route('products.security-instructions.edit', [$product, $second]));

    expect($second->fresh()->status)->toBe(UserSecurityInstructionStatus::Retired);

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::UserSecurityInstructionRetired)
        ->where('product_id', $product->id)
        ->exists())->toBeTrue();
});

test('viewer cannot publish security instructions', function () {
    ['organization' => $organization, 'owner' => $owner, 'product' => $product] = makeUsiOrgWithOwner();
    $viewer = makeUsiOrgViewer($organization);

    $this->actingAs($owner)
        ->post(route('products.security-instructions.store', $product), [
            'title' => 'Locked publish',
            'version_label' => '1.0',
            'locale' => 'en',
        ]);

    $instruction = UserSecurityInstruction::query()
        ->where('product_id', $product->id)
        ->firstOrFail();

    $instruction->sections()->update(['is_applicable' => false]);

    $this->actingAs($viewer)
        ->post(route('products.security-instructions.publish', [$product, $instruction]))
        ->assertForbidden();

    expect($instruction->fresh()->status)->toBe(UserSecurityInstructionStatus::Draft);
});
