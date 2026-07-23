<?php

use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\SdlRunStatus;
use App\Enums\SdlStage;
use App\Enums\UserSecurityInstructionStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Role;
use App\Models\SdlRun;
use App\Models\User;
use App\Models\UserSecurityInstruction;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     organization: Organization,
 *     owner: User,
 *     product: Product,
 *     run: SdlRun,
 *     published: UserSecurityInstruction,
 *     draft: UserSecurityInstruction
 * }
 */
function makeSdlUsiLinkFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'SDL USI Link Org',
        'slug' => 'sdl-usi-link-org-' . uniqid(),
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
        'name' => 'SDL USI Link Product',
        'slug' => 'sdl-usi-link-product-' . uniqid(),
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

    $published = UserSecurityInstruction::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Published USI',
        'status' => UserSecurityInstructionStatus::Published,
        'version_label' => '1.0',
        'locale' => 'en',
        'published_at' => now(),
        'published_by' => $owner->id,
    ]);

    $draft = UserSecurityInstruction::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Draft USI',
        'status' => UserSecurityInstructionStatus::Draft,
        'version_label' => '0.1',
        'locale' => 'en',
    ]);

    $run = SdlRun::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Docs link run',
        'status' => SdlRunStatus::InProgress,
        'current_stage' => SdlStage::Publication,
        'owner_user_id' => $owner->id,
    ]);
    $run->ensureStageEntries();

    return compact('organization', 'owner', 'product', 'run', 'published', 'draft');
}

test('sdl edit exposes published USI options only', function () {
    [
        'owner' => $owner,
        'product' => $product,
        'run' => $run,
        'published' => $published,
    ] = makeSdlUsiLinkFixture();

    $this->actingAs($owner)
        ->get(route('products.sdl.edit', [$product, $run]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/sdl/Edit')
            ->has('published_usi', 1)
            ->where('published_usi.0.id', $published->id)
            ->where('run.user_security_instruction_id', null)
            ->where('run.tech_doc_delta_reviewed', false));
});

test('owner can link published USI and mark tech-doc delta reviewed', function () {
    [
        'owner' => $owner,
        'product' => $product,
        'run' => $run,
        'published' => $published,
    ] = makeSdlUsiLinkFixture();

    $this->actingAs($owner)
        ->put(route('products.sdl.documentation.update', [$product, $run]), [
            'user_security_instruction_id' => $published->id,
            'tech_doc_delta_reviewed' => true,
        ])
        ->assertRedirect(route('products.sdl.edit', [$product, $run]));

    $run->refresh();

    expect($run->user_security_instruction_id)->toBe($published->id)
        ->and($run->tech_doc_delta_reviewed)->toBeTrue();

    $this->actingAs($owner)
        ->get(route('products.sdl.edit', [$product, $run]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->where('run.user_security_instruction_id', $published->id)
            ->where('run.linked_usi.title', 'Published USI')
            ->where('run.tech_doc_delta_reviewed', true));
});

test('draft USI cannot be linked to SDL run', function () {
    [
        'owner' => $owner,
        'product' => $product,
        'run' => $run,
        'draft' => $draft,
    ] = makeSdlUsiLinkFixture();

    $this->actingAs($owner)
        ->from(route('products.sdl.edit', [$product, $run]))
        ->put(route('products.sdl.documentation.update', [$product, $run]), [
            'user_security_instruction_id' => $draft->id,
            'tech_doc_delta_reviewed' => false,
        ])
        ->assertRedirect(route('products.sdl.edit', [$product, $run]))
        ->assertSessionHasErrors('user_security_instruction_id');

    expect($run->fresh()->user_security_instruction_id)->toBeNull();
});

test('documentation links remain editable after approval', function () {
    [
        'owner' => $owner,
        'product' => $product,
        'run' => $run,
        'published' => $published,
    ] = makeSdlUsiLinkFixture();

    $run->update([
        'status' => SdlRunStatus::Approved,
        'approved_at' => now(),
        'approved_by' => $owner->id,
        'current_stage' => SdlStage::Monitoring,
    ]);

    $this->actingAs($owner)
        ->put(route('products.sdl.documentation.update', [$product, $run]), [
            'user_security_instruction_id' => $published->id,
            'tech_doc_delta_reviewed' => true,
        ])
        ->assertRedirect(route('products.sdl.edit', [$product, $run]));

    expect($run->fresh()->user_security_instruction_id)->toBe($published->id)
        ->and($run->fresh()->tech_doc_delta_reviewed)->toBeTrue();
});

test('SDL export includes linked USI and tech-doc flag', function () {
    [
        'owner' => $owner,
        'product' => $product,
        'run' => $run,
        'published' => $published,
    ] = makeSdlUsiLinkFixture();

    $run->update([
        'user_security_instruction_id' => $published->id,
        'tech_doc_delta_reviewed' => true,
    ]);

    $markdown = $this->actingAs($owner)
        ->get(route('products.sdl.export', [
            'product' => $product,
            'sdlRun' => $run,
            'format' => 'markdown',
        ]))
        ->assertOk()
        ->getContent();

    expect($markdown)->toContain('Published USI')
        ->and($markdown)->toContain('1.0')
        ->and($markdown)->toContain('Yes');
});
