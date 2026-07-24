<?php

use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\TechnicalDocumentationSectionKey;
use App\Enums\TechnicalDocumentationSectionSource;
use App\Enums\TechnicalDocumentationStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Role;
use App\Models\TechnicalDocumentationPackage;
use App\Models\User;
use App\Services\TechnicalDocumentationService;
use App\Support\TechnicalDocumentationConformityPack;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User, product: Product}
 */
function makeTechDocConformityPackFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Tech Doc Conformity Org',
        'slug' => 'tech-doc-conformity-org-' . uniqid(),
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
        'name' => 'Tech Doc Conformity Product',
        'slug' => 'tech-doc-conformity-product-' . uniqid(),
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    return compact('organization', 'owner', 'product');
}

function createTechDocConformityDraft(
    User $owner,
    Product $product,
    string $title = 'Conformity draft',
): TechnicalDocumentationPackage {
    return app(TechnicalDocumentationService::class)->create($product, [
        'title' => $title,
        'version_label' => '1.0',
        'locale' => 'en',
        'notes' => null,
        'inherit_from_previous' => false,
        'product_version_id' => null,
        'user_security_instruction_id' => null,
        'sdl_run_id' => null,
    ], $owner)->load('sections');
}

/**
 * @return array{items: list<array{key: string, done: bool, notes: string}>, path_summary: string, kind: string}
 */
function completeConformityChecklistPack(): array
{
    $pack = TechnicalDocumentationConformityPack::defaultChecklistPayload();
    $pack['path_summary'] = 'Module A internal production control with self-assessment.';
    $pack['items'] = array_map(
        fn(array $item): array => [
            'key' => $item['key'],
            'done' => true,
            'notes' => 'Done',
        ],
        $pack['items'],
    );

    return $pack;
}

/**
 * @return array{fields: array<string, string>, reviewed: bool, kind: string}
 */
function completeDeclarationFieldsPack(): array
{
    $pack = TechnicalDocumentationConformityPack::defaultDeclarationPayload();
    $pack['reviewed'] = true;
    $pack['fields']['manufacturer_name'] = 'Avalon Labs EOOD';
    $pack['fields']['product_name'] = 'Secure Gateway';
    $pack['fields']['signatory_name_role'] = 'Jane Doe, CEO';
    $pack['fields']['place_date'] = 'Sofia, 2026-07-24';

    return $pack;
}

test('new package seeds empty conformity checklist and DoC field packs', function () {
    ['owner' => $owner, 'product' => $product] = makeTechDocConformityPackFixture();
    $package = createTechDocConformityDraft($owner, $product);

    $checklist = $package->sections
        ->firstWhere('section_key', TechnicalDocumentationSectionKey::ConformityAssessmentPath);
    $declaration = $package->sections
        ->firstWhere('section_key', TechnicalDocumentationSectionKey::DeclarationInformation);

    expect($checklist?->source)->toBe(TechnicalDocumentationSectionSource::Authored)
        ->and($checklist?->generated_payload['kind'] ?? null)
        ->toBe(TechnicalDocumentationConformityPack::CHECKLIST_KIND)
        ->and($checklist?->body_markdown)->toBeNull()
        ->and($declaration?->generated_payload['kind'] ?? null)
        ->toBe(TechnicalDocumentationConformityPack::DECLARATION_KIND)
        ->and($declaration?->body_markdown)->toBeNull();

    $incomplete = app(TechnicalDocumentationService::class)->incompleteSectionKeys($package);

    expect($incomplete)->toContain(TechnicalDocumentationSectionKey::ConformityAssessmentPath->value)
        ->and($incomplete)->toContain(TechnicalDocumentationSectionKey::DeclarationInformation->value);
});

test('owner can save conformity checklist and DoC packs which sync markdown', function () {
    ['owner' => $owner, 'product' => $product] = makeTechDocConformityPackFixture();
    $package = createTechDocConformityDraft($owner, $product);

    $checklistPack = completeConformityChecklistPack();
    $declarationPack = completeDeclarationFieldsPack();

    $sections = $package->sections->map(function ($section) use ($checklistPack, $declarationPack) {
        $payload = [
            'section_key' => $section->section_key->value,
            'body_markdown' => $section->body_markdown,
            'is_applicable' => true,
            'override_reason' => null,
            'sort_order' => $section->sort_order,
        ];

        if ($section->section_key === TechnicalDocumentationSectionKey::ConformityAssessmentPath) {
            $payload['manual_pack'] = $checklistPack;
            $payload['body_markdown'] = null;
        }

        if ($section->section_key === TechnicalDocumentationSectionKey::DeclarationInformation) {
            $payload['manual_pack'] = $declarationPack;
            $payload['body_markdown'] = null;
        }

        return $payload;
    })->all();

    $this->actingAs($owner)
        ->put(route('products.technical-documentation.update', [$product, $package]), [
            'title' => $package->title,
            'version_label' => $package->version_label,
            'locale' => 'en',
            'sections' => $sections,
        ])
        ->assertRedirect();

    $package->refresh()->load('sections');

    $checklist = $package->sections
        ->firstWhere('section_key', TechnicalDocumentationSectionKey::ConformityAssessmentPath);
    $declaration = $package->sections
        ->firstWhere('section_key', TechnicalDocumentationSectionKey::DeclarationInformation);

    expect($checklist?->generated_payload['path_summary'] ?? null)
        ->toContain('Module A')
        ->and($checklist?->body_markdown)->toContain('Module A')
        ->and($checklist?->body_markdown)->toContain('[x]')
        ->and($declaration?->generated_payload['fields']['manufacturer_name'] ?? null)
        ->toBe('Avalon Labs EOOD')
        ->and($declaration?->body_markdown)->toContain('Avalon Labs EOOD')
        ->and($declaration?->generated_payload['reviewed'] ?? null)->toBeTrue();

    $incomplete = app(TechnicalDocumentationService::class)->incompleteSectionKeys($package);

    expect($incomplete)->not->toContain(TechnicalDocumentationSectionKey::ConformityAssessmentPath->value)
        ->and($incomplete)->not->toContain(TechnicalDocumentationSectionKey::DeclarationInformation->value);
});

test('publish rejects incomplete conformity packs even with empty markdown sync avoided', function () {
    ['owner' => $owner, 'product' => $product] = makeTechDocConformityPackFixture();
    $package = createTechDocConformityDraft($owner, $product);

    $package->sections()
        ->whereIn('source', [
            TechnicalDocumentationSectionSource::Authored->value,
            TechnicalDocumentationSectionSource::Linked->value,
        ])
        ->whereNotIn('section_key', [
            TechnicalDocumentationSectionKey::ConformityAssessmentPath->value,
            TechnicalDocumentationSectionKey::DeclarationInformation->value,
        ])
        ->update([
            'is_applicable' => false,
            'override_reason' => 'N/A for pack incomplete test.',
            'body_markdown' => null,
        ]);

    $package->sections()
        ->where('source', TechnicalDocumentationSectionSource::Generated->value)
        ->update([
            'is_applicable' => false,
            'override_reason' => 'N/A for pack incomplete test.',
        ]);

    $this->actingAs($owner)
        ->post(route('products.technical-documentation.publish', [$product, $package]))
        ->assertSessionHasErrors('sections');

    expect($package->fresh()->status)->toBe(TechnicalDocumentationStatus::Draft);
});

test('edit page exposes manual_pack for conformity and declaration sections', function () {
    ['owner' => $owner, 'product' => $product] = makeTechDocConformityPackFixture();
    $package = createTechDocConformityDraft($owner, $product);

    $checklistKind = TechnicalDocumentationConformityPack::CHECKLIST_KIND;
    $declarationKind = TechnicalDocumentationConformityPack::DECLARATION_KIND;

    $this->actingAs($owner)
        ->get(route('products.technical-documentation.edit', [$product, $package]))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('products/technical-documentation/Edit')
            ->has('package.sections')
            ->where(
                'package.sections',
                fn($sections) => collect($sections)->contains(
                    fn($section) => $section['section_key'] === 'conformity_assessment_path'
                    && ($section['manual_pack']['kind'] ?? null) === $checklistKind,
                ),
            )
            ->where(
                'package.sections',
                fn($sections) => collect($sections)->contains(
                    fn($section) => $section['section_key'] === 'declaration_information'
                    && ($section['manual_pack']['kind'] ?? null) === $declarationKind,
                ),
            ));
});

test('legacy authored markdown without pack kind still counts as complete', function () {
    ['owner' => $owner, 'product' => $product] = makeTechDocConformityPackFixture();
    $package = createTechDocConformityDraft($owner, $product);

    $package->sections()
        ->where('section_key', TechnicalDocumentationSectionKey::ConformityAssessmentPath->value)
        ->update([
            'generated_payload' => null,
            'body_markdown' => '## Legacy free-form conformity notes',
        ]);

    $package->sections()
        ->where('section_key', TechnicalDocumentationSectionKey::DeclarationInformation->value)
        ->update([
            'generated_payload' => null,
            'body_markdown' => '## Legacy DoC notes',
        ]);

    $incomplete = app(TechnicalDocumentationService::class)
        ->incompleteSectionKeys($package->fresh(['sections']));

    expect($incomplete)->not->toContain(TechnicalDocumentationSectionKey::ConformityAssessmentPath->value)
        ->and($incomplete)->not->toContain(TechnicalDocumentationSectionKey::DeclarationInformation->value);
});

test('checklist and declaration completeness helpers require required fields', function () {
    $emptyChecklist = TechnicalDocumentationConformityPack::defaultChecklistPayload();
    expect(TechnicalDocumentationConformityPack::isComplete(
        TechnicalDocumentationSectionKey::ConformityAssessmentPath,
        $emptyChecklist,
    ))->toBeFalse();

    expect(TechnicalDocumentationConformityPack::isComplete(
        TechnicalDocumentationSectionKey::ConformityAssessmentPath,
        completeConformityChecklistPack(),
    ))->toBeTrue();

    $emptyDeclaration = TechnicalDocumentationConformityPack::defaultDeclarationPayload();
    expect(TechnicalDocumentationConformityPack::isComplete(
        TechnicalDocumentationSectionKey::DeclarationInformation,
        $emptyDeclaration,
    ))->toBeFalse();

    expect(TechnicalDocumentationConformityPack::isComplete(
        TechnicalDocumentationSectionKey::DeclarationInformation,
        completeDeclarationFieldsPack(),
    ))->toBeTrue();
});
