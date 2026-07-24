<?php

use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\SdlRunStatus;
use App\Enums\SdlStage;
use App\Enums\TechnicalDocumentationSectionKey;
use App\Enums\TechnicalDocumentationStatus;
use App\Enums\UserSecurityInstructionStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Role;
use App\Models\SdlRun;
use App\Models\TechnicalDocumentationPackage;
use App\Models\TechnicalDocumentationSection;
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
 *     package: TechnicalDocumentationPackage,
 *     published: UserSecurityInstruction,
 *     draft: UserSecurityInstruction,
 *     sdlRun: SdlRun
 * }
 */
function makeTechDocUsiSdlLinkFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Tech Doc Link Org',
        'slug' => 'tech-doc-link-org-' . uniqid(),
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
        'name' => 'Tech Doc Link Product',
        'slug' => 'tech-doc-link-product-' . uniqid(),
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
        'title' => 'Published USI for tech doc',
        'status' => UserSecurityInstructionStatus::Published,
        'version_label' => '2.0',
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

    $sdlRun = SdlRun::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Approved SDL reference',
        'status' => SdlRunStatus::Approved,
        'current_stage' => SdlStage::Monitoring,
        'owner_user_id' => $owner->id,
        'approved_at' => now(),
        'approved_by' => $owner->id,
    ]);
    $sdlRun->ensureStageEntries();

    $package = TechnicalDocumentationPackage::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Package with links',
        'status' => TechnicalDocumentationStatus::Draft,
        'version_label' => '1.0',
        'locale' => 'en',
    ]);

    foreach (TechnicalDocumentationSectionKey::ordered() as $key) {
        TechnicalDocumentationSection::query()->create([
            'package_id' => $package->id,
            'section_key' => $key,
            'source' => $key->defaultSource(),
            'body_markdown' => null,
            'generated_payload' => null,
            'sort_order' => $key->defaultSortOrder(),
            'is_applicable' => true,
            'override_reason' => null,
            'changed_since_parent' => false,
        ]);
    }

    return compact(
        'organization',
        'owner',
        'product',
        'package',
        'published',
        'draft',
        'sdlRun',
    );
}

/**
 * @param  TechnicalDocumentationPackage  $package
 * @return list<array<string, mixed>>
 */
function techDocSectionsPayload(TechnicalDocumentationPackage $package): array
{
    return $package->sections()
        ->orderBy('sort_order')
        ->get()
        ->map(fn(TechnicalDocumentationSection $section) => [
            'section_key' => $section->section_key->value,
            'body_markdown' => $section->body_markdown,
            'is_applicable' => $section->is_applicable,
            'override_reason' => $section->override_reason,
            'sort_order' => $section->sort_order,
        ])
        ->all();
}

test('tech-doc edit exposes published USI and SDL run options', function () {
    [
        'owner' => $owner,
        'product' => $product,
        'package' => $package,
        'published' => $published,
        'sdlRun' => $sdlRun,
    ] = makeTechDocUsiSdlLinkFixture();

    $this->actingAs($owner)
        ->get(route('products.technical-documentation.edit', [$product, $package]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/technical-documentation/Edit')
            ->has('published_usi', 1)
            ->where('published_usi.0.id', $published->id)
            ->has('sdl_runs', 1)
            ->where('sdl_runs.0.id', $sdlRun->id)
            ->where('package.user_security_instruction_id', null)
            ->where('package.sdl_run_id', null));
});

test('owner can link published USI and SDL run and refresh linked summary', function () {
    [
        'owner' => $owner,
        'product' => $product,
        'package' => $package,
        'published' => $published,
        'sdlRun' => $sdlRun,
    ] = makeTechDocUsiSdlLinkFixture();

    $this->actingAs($owner)
        ->put(route('products.technical-documentation.update', [$product, $package]), [
            'title' => $package->title,
            'version_label' => $package->version_label,
            'locale' => $package->locale,
            'notes' => null,
            'product_version_id' => null,
            'user_security_instruction_id' => $published->id,
            'sdl_run_id' => $sdlRun->id,
            'sections' => techDocSectionsPayload($package),
        ])
        ->assertRedirect(route('products.technical-documentation.edit', [$product, $package]));

    $package->refresh();

    expect($package->user_security_instruction_id)->toBe($published->id)
        ->and($package->sdl_run_id)->toBe($sdlRun->id);

    $linked = $package->sections()
        ->where('section_key', TechnicalDocumentationSectionKey::UserSecurityInstructions->value)
        ->firstOrFail();

    expect($linked->generated_payload)->toBeArray()
        ->and($linked->generated_payload['markdown'] ?? null)->toContain('Published USI for tech doc')
        ->and($linked->generated_payload['markdown'] ?? null)->toContain('Approved SDL reference')
        ->and($linked->generated_payload['facts']['usi_id'] ?? null)->toBe($published->id)
        ->and($linked->generated_payload['facts']['sdl_run_id'] ?? null)->toBe($sdlRun->id);

    $this->actingAs($owner)
        ->get(route('products.technical-documentation.edit', [$product, $package]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->where('package.user_security_instruction_id', $published->id)
            ->where('package.linked_usi.title', 'Published USI for tech doc')
            ->where('package.linked_sdl.title', 'Approved SDL reference'));
});

test('draft USI cannot be linked to technical documentation', function () {
    [
        'owner' => $owner,
        'product' => $product,
        'package' => $package,
        'draft' => $draft,
    ] = makeTechDocUsiSdlLinkFixture();

    $this->actingAs($owner)
        ->from(route('products.technical-documentation.edit', [$product, $package]))
        ->put(route('products.technical-documentation.update', [$product, $package]), [
            'title' => $package->title,
            'version_label' => $package->version_label,
            'locale' => $package->locale,
            'notes' => null,
            'product_version_id' => null,
            'user_security_instruction_id' => $draft->id,
            'sdl_run_id' => null,
            'sections' => techDocSectionsPayload($package),
        ])
        ->assertRedirect(route('products.technical-documentation.edit', [$product, $package]))
        ->assertSessionHasErrors('user_security_instruction_id');

    expect($package->fresh()->user_security_instruction_id)->toBeNull();
});

test('tech-doc export includes linked USI and SDL summary', function () {
    [
        'owner' => $owner,
        'product' => $product,
        'package' => $package,
        'published' => $published,
        'sdlRun' => $sdlRun,
    ] = makeTechDocUsiSdlLinkFixture();

    $this->actingAs($owner)
        ->put(route('products.technical-documentation.update', [$product, $package]), [
            'title' => $package->title,
            'version_label' => $package->version_label,
            'locale' => $package->locale,
            'notes' => null,
            'product_version_id' => null,
            'user_security_instruction_id' => $published->id,
            'sdl_run_id' => $sdlRun->id,
            'sections' => techDocSectionsPayload($package),
        ])
        ->assertRedirect();

    $markdown = $this->actingAs($owner)
        ->get(route('products.technical-documentation.export', [
            'product' => $product,
            'package' => $package,
            'format' => 'markdown',
        ]))
        ->assertOk()
        ->getContent();

    expect($markdown)->toContain('Published USI for tech doc')
        ->and($markdown)->toContain('2.0')
        ->and($markdown)->toContain('Approved SDL reference');
});
