<?php

use App\Enums\AuditEventType;
use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductRiskStatus;
use App\Enums\ProductType;
use App\Enums\ProductVersionState;
use App\Enums\RiskCategory;
use App\Enums\RiskImpact;
use App\Enums\RiskLikelihood;
use App\Enums\RiskTreatment;
use App\Enums\SbomFormat;
use App\Enums\ScopeStatus;
use App\Enums\SupportStatus;
use App\Enums\TechnicalDocumentationSectionKey;
use App\Enums\TechnicalDocumentationSectionSource;
use App\Enums\TechnicalDocumentationStatus;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductRisk;
use App\Models\ProductVersion;
use App\Models\Role;
use App\Models\Sbom;
use App\Models\TechnicalDocumentationPackage;
use App\Models\TechnicalDocumentationSection;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User, product: Product}
 */
function makeTechDocOrgWithOwner(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Tech Doc CRUD Org',
        'slug' => 'tech-doc-crud-org-' . uniqid(),
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
        'name' => 'Tech Doc CRUD Product',
        'slug' => 'tech-doc-crud-product-' . uniqid(),
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    return compact('organization', 'owner', 'product');
}

function makeTechDocOrgViewer(Organization $organization): User
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

function seedTechDocModuleFacts(Product $product, User $owner): ProductVersion
{
    $product->update([
        'manufacturer' => 'Avalon Labs',
        'intended_purpose' => 'Secure industrial gateway',
    ]);

    $version = ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '2.1.0',
        'state' => ProductVersionState::Released,
        'support_status' => SupportStatus::Supported,
        'release_date' => now()->toDateString(),
    ]);

    ProductRisk::query()->create([
        'product_id' => $product->id,
        'product_version_id' => $version->id,
        'title' => 'Weak default credentials',
        'category' => RiskCategory::SecretsExposure,
        'likelihood' => RiskLikelihood::High,
        'impact' => RiskImpact::High,
        'treatment' => RiskTreatment::Mitigate,
        'status' => ProductRiskStatus::Open,
    ]);

    Sbom::query()->create([
        'product_id' => $product->id,
        'product_version_id' => $version->id,
        'format' => SbomFormat::CycloneDxJson,
        'source_filename' => 'sbom-2.1.0.json',
        'checksum_sha256' => str_repeat('a', 64),
        'component_count' => 42,
        'imported_by' => $owner->id,
        'imported_at' => now(),
    ]);

    return $version;
}

/**
 * @param  iterable<int, TechnicalDocumentationSection>  $sections
 * @param  array<string, array{body_markdown?: string|null, is_applicable?: bool, override_reason?: string|null}>  $overrides
 * @return list<array{
 *     section_key: string,
 *     body_markdown: string|null,
 *     is_applicable: bool,
 *     override_reason: string|null,
 *     sort_order: int
 * }>
 */
function techDocSectionUpdatePayload(iterable $sections, array $overrides = []): array
{
    return collect($sections)->map(function (TechnicalDocumentationSection $section) use ($overrides): array {
        $key = $section->section_key->value;
        $override = $overrides[$key] ?? [];

        return [
            'section_key' => $key,
            'body_markdown' => array_key_exists('body_markdown', $override)
                ? $override['body_markdown']
                : $section->body_markdown,
            'is_applicable' => $override['is_applicable'] ?? true,
            'override_reason' => $override['override_reason'] ?? null,
            'sort_order' => $section->sort_order,
        ];
    })->all();
}

function techDocSectionByKey(
    TechnicalDocumentationPackage $package,
    TechnicalDocumentationSectionKey $key,
): ?TechnicalDocumentationSection {
    return $package->sections->firstWhere('section_key', $key);
}

test('owner can view technical documentation index', function () {
    ['owner' => $owner, 'product' => $product] = makeTechDocOrgWithOwner();

    $this->actingAs($owner)
        ->get(route('products.technical-documentation.index', $product))
        ->assertOk();
});

test('owner can create technical documentation package with seeded sections', function () {
    ['owner' => $owner, 'product' => $product] = makeTechDocOrgWithOwner();

    $this->actingAs($owner)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => 'Technical documentation',
            'version_label' => '1.0',
            'locale' => 'en',
            'notes' => 'Initial draft',
        ])
        ->assertRedirect();

    $package = TechnicalDocumentationPackage::query()
        ->where('product_id', $product->id)
        ->firstOrFail()
        ->load('sections');

    expect($package->title)->toBe('Technical documentation')
        ->and($package->status)->toBe(TechnicalDocumentationStatus::Draft)
        ->and($package->sections)->toHaveCount(18);

    $sbom = $package->sections
        ->firstWhere('section_key', TechnicalDocumentationSectionKey::Sbom);

    expect($sbom?->source)->toBe(TechnicalDocumentationSectionSource::Generated)
        ->and($sbom?->generated_payload)->toBeArray()
        ->and($sbom?->generated_payload['source_module'] ?? null)->toBe('sbom')
        ->and($sbom?->generated_payload['markdown'] ?? null)->toBeString();

    $identification = $package->sections
        ->firstWhere('section_key', TechnicalDocumentationSectionKey::ProductIdentification);

    expect($identification?->generated_payload['facts']['name'] ?? null)
        ->toBe('Tech Doc CRUD Product');

    $architecture = $package->sections
        ->firstWhere('section_key', TechnicalDocumentationSectionKey::Architecture);

    expect($architecture?->source)->toBe(TechnicalDocumentationSectionSource::Authored)
        ->and($architecture?->generated_payload)->toBeNull();

    expect(AuditLog::query()
        ->where('event_type', AuditEventType::TechnicalDocumentationCreated)
        ->exists())->toBeTrue();
});

test('create validates required fields', function () {
    ['owner' => $owner, 'product' => $product] = makeTechDocOrgWithOwner();

    $this->actingAs($owner)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => '',
            'version_label' => '',
            'locale' => 'en',
        ])
        ->assertSessionHasErrors(['title', 'version_label']);
});

test('owner can update package metadata', function () {
    ['owner' => $owner, 'product' => $product] = makeTechDocOrgWithOwner();

    $this->actingAs($owner)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => 'Original',
            'version_label' => '1.0',
            'locale' => 'en',
        ])
        ->assertRedirect();

    $package = TechnicalDocumentationPackage::query()
        ->where('product_id', $product->id)
        ->firstOrFail()
        ->load('sections');

    $sections = $package->sections->map(fn($section) => [
        'section_key' => $section->section_key->value,
        'body_markdown' => $section->body_markdown,
        'is_applicable' => true,
        'override_reason' => null,
        'sort_order' => $section->sort_order,
    ])->all();

    $this->actingAs($owner)
        ->put(route('products.technical-documentation.update', [$product, $package]), [
            'title' => 'Updated title',
            'version_label' => '1.1',
            'locale' => 'bg',
            'notes' => 'Updated notes',
            'sections' => $sections,
        ])
        ->assertRedirect(route('products.technical-documentation.edit', [$product, $package]));

    $package->refresh();

    expect($package->title)->toBe('Updated title')
        ->and($package->version_label)->toBe('1.1')
        ->and($package->locale)->toBe('bg')
        ->and($package->notes)->toBe('Updated notes');
});

test('owner can edit authored section body and mark generated section not applicable', function () {
    ['owner' => $owner, 'product' => $product] = makeTechDocOrgWithOwner();

    $this->actingAs($owner)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => 'Section editor package',
            'version_label' => '1.0',
            'locale' => 'en',
        ])
        ->assertRedirect();

    $package = TechnicalDocumentationPackage::query()
        ->where('product_id', $product->id)
        ->firstOrFail()
        ->load('sections');

    $sections = $package->sections->map(function ($section) {
        $key = $section->section_key->value;
        $payload = [
            'section_key' => $key,
            'body_markdown' => $section->body_markdown,
            'is_applicable' => true,
            'override_reason' => null,
            'sort_order' => $section->sort_order,
        ];

        if ($section->section_key === TechnicalDocumentationSectionKey::Architecture) {
            $payload['body_markdown'] = "## Architecture\n\nTrust boundaries documented.";
        }

        if ($section->section_key === TechnicalDocumentationSectionKey::Sbom) {
            $payload['is_applicable'] = false;
            $payload['override_reason'] = 'SBOM tracked in separate release tooling for now.';
            $payload['body_markdown'] = 'Should not matter when N/A';
        }

        return $payload;
    })->all();

    $this->actingAs($owner)
        ->put(route('products.technical-documentation.update', [$product, $package]), [
            'title' => 'Section editor package',
            'version_label' => '1.0',
            'locale' => 'en',
            'notes' => null,
            'sections' => $sections,
        ])
        ->assertRedirect();

    $package->refresh()->load('sections');

    $architecture = $package->sections
        ->firstWhere('section_key', TechnicalDocumentationSectionKey::Architecture);
    $sbom = $package->sections
        ->firstWhere('section_key', TechnicalDocumentationSectionKey::Sbom);

    expect($architecture?->body_markdown)->toContain('Trust boundaries documented.')
        ->and($architecture?->source)->toBe(TechnicalDocumentationSectionSource::Authored)
        ->and($sbom?->is_applicable)->toBeFalse()
        ->and($sbom?->override_reason)->toContain('SBOM tracked')
        ->and($sbom?->generated_payload)->toBeArray();

    $this->actingAs($owner)
        ->get(route('products.technical-documentation.edit', [$product, $package]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/technical-documentation/Edit')
            ->where('package.sections', fn($sections) => collect($sections)->contains(
                fn($section) => $section['section_key'] === 'architecture'
                && str_contains((string) $section['body_markdown'], 'Trust boundaries'),
            ))
            ->where('package.sections', fn($sections) => collect($sections)->contains(
                fn($section) => $section['section_key'] === 'sbom'
                && $section['is_applicable'] === false
                && array_key_exists('generated_payload', $section)
                && array_key_exists('override_reason', $section),
            )));
});

test('owner can delete draft package', function () {
    ['owner' => $owner, 'product' => $product] = makeTechDocOrgWithOwner();

    $this->actingAs($owner)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => 'To delete',
            'version_label' => '1.0',
            'locale' => 'en',
        ])
        ->assertRedirect();

    $package = TechnicalDocumentationPackage::query()
        ->where('product_id', $product->id)
        ->firstOrFail();

    $this->actingAs($owner)
        ->delete(route('products.technical-documentation.destroy', [$product, $package]))
        ->assertRedirect(route('products.technical-documentation.index', $product));

    expect(TechnicalDocumentationPackage::query()->whereKey($package->id)->exists())->toBeFalse();
});

test('viewer can open index and api but cannot create', function () {
    ['organization' => $organization, 'owner' => $owner, 'product' => $product] = makeTechDocOrgWithOwner();
    $viewer = makeTechDocOrgViewer($organization);

    $this->actingAs($owner)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => 'Visible package',
            'version_label' => '1.0',
            'locale' => 'en',
        ])
        ->assertRedirect();

    $this->actingAs($viewer)
        ->get(route('products.technical-documentation.index', $product))
        ->assertOk();

    $this->actingAs($viewer)
        ->getJson(route('internal.products.technical-documentation.index', $product))
        ->assertOk()
        ->assertJsonPath('total', 1);

    $this->actingAs($viewer)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => 'Forbidden',
            'version_label' => '1.0',
            'locale' => 'en',
        ])
        ->assertForbidden();
});

test('edit page lists seeded sections', function () {
    ['owner' => $owner, 'product' => $product] = makeTechDocOrgWithOwner();

    $this->actingAs($owner)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => 'Edit me',
            'version_label' => '1.0',
            'locale' => 'en',
        ])
        ->assertRedirect();

    $package = TechnicalDocumentationPackage::query()
        ->where('product_id', $product->id)
        ->firstOrFail();

    $this->actingAs($owner)
        ->get(route('products.technical-documentation.edit', [$product, $package]))
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('products/technical-documentation/Edit')
            ->has('package.sections', 18)
            ->where('package.title', 'Edit me'));
});

test('create populates generated sections from product modules', function () {
    ['owner' => $owner, 'product' => $product] = makeTechDocOrgWithOwner();
    $version = seedTechDocModuleFacts($product, $owner);

    $this->actingAs($owner)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => 'Generated package',
            'version_label' => '1.0',
            'locale' => 'en',
            'product_version_id' => $version->id,
        ])
        ->assertRedirect();

    $package = TechnicalDocumentationPackage::query()
        ->where('product_id', $product->id)
        ->firstOrFail()
        ->load('sections');

    $identification = techDocSectionByKey($package, TechnicalDocumentationSectionKey::ProductIdentification);
    $risks = techDocSectionByKey($package, TechnicalDocumentationSectionKey::CybersecurityRiskAssessment);
    $sbom = techDocSectionByKey($package, TechnicalDocumentationSectionKey::Sbom);
    $releases = techDocSectionByKey($package, TechnicalDocumentationSectionKey::ReleaseHistory);

    expect($identification?->generated_payload['facts']['manufacturer'] ?? null)->toBe('Avalon Labs');
    expect($identification?->generated_payload['markdown'] ?? '')->toContain('Avalon Labs');
    expect($risks?->generated_payload['facts']['count'] ?? 0)->toBe(1);
    expect($risks?->generated_payload['markdown'] ?? '')->toContain('Weak default credentials');
    expect($sbom?->generated_payload['facts']['count'] ?? 0)->toBe(1);
    expect($sbom?->generated_payload['facts']['total_components'] ?? 0)->toBe(42);
    expect($sbom?->generated_payload['markdown'] ?? '')->toContain('sbom-2.1.0.json');
    expect($releases?->generated_payload['facts']['count'] ?? 0)->toBe(1);
    expect($releases?->generated_payload['markdown'] ?? '')->toContain('2.1.0');
});

test('refresh updates generated payload and preserves supplemental notes', function () {
    ['owner' => $owner, 'product' => $product] = makeTechDocOrgWithOwner();
    $version = seedTechDocModuleFacts($product, $owner);

    $this->actingAs($owner)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => 'Generated package',
            'version_label' => '1.0',
            'locale' => 'en',
            'product_version_id' => $version->id,
        ])
        ->assertRedirect();

    $package = TechnicalDocumentationPackage::query()
        ->where('product_id', $product->id)
        ->firstOrFail()
        ->load('sections');

    $this->actingAs($owner)
        ->put(route('products.technical-documentation.update', [$product, $package]), [
            'title' => 'Generated package',
            'version_label' => '1.0',
            'locale' => 'en',
            'product_version_id' => $version->id,
            'sections' => techDocSectionUpdatePayload($package->sections, [
                'sbom' => ['body_markdown' => 'Supplemental SBOM notes for auditors.'],
            ]),
        ])
        ->assertRedirect();

    ProductRisk::query()->create([
        'product_id' => $product->id,
        'title' => 'Unpatched dependency',
        'category' => RiskCategory::DependencyCompromise,
        'likelihood' => RiskLikelihood::Medium,
        'impact' => RiskImpact::Medium,
        'treatment' => RiskTreatment::Mitigate,
        'status' => ProductRiskStatus::Open,
    ]);

    $this->actingAs($owner)
        ->post(route('products.technical-documentation.refresh-generated', [$product, $package]))
        ->assertRedirect(route('products.technical-documentation.edit', [$product, $package]));

    $package->refresh()->load('sections');
    $risks = techDocSectionByKey($package, TechnicalDocumentationSectionKey::CybersecurityRiskAssessment);
    $sbom = techDocSectionByKey($package, TechnicalDocumentationSectionKey::Sbom);

    expect($risks?->generated_payload['facts']['count'] ?? 0)->toBe(2);
    expect($risks?->generated_payload['markdown'] ?? '')->toContain('Unpatched dependency');
    expect($sbom?->body_markdown)->toBe('Supplemental SBOM notes for auditors.');
    expect($sbom?->generated_payload['facts']['total_components'] ?? 0)->toBe(42);
    expect(AuditLog::query()
        ->where('event_type', AuditEventType::TechnicalDocumentationGeneratedRefreshed)
        ->exists())->toBeTrue();
});

test('refresh skips not-applicable generated sections and preserves supplemental notes', function () {
    ['owner' => $owner, 'product' => $product] = makeTechDocOrgWithOwner();

    $this->actingAs($owner)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => 'Skip N/A package',
            'version_label' => '1.0',
            'locale' => 'en',
        ])
        ->assertRedirect();

    $package = TechnicalDocumentationPackage::query()
        ->where('product_id', $product->id)
        ->firstOrFail()
        ->load('sections');

    $this->actingAs($owner)
        ->put(route('products.technical-documentation.update', [$product, $package]), [
            'title' => 'Skip N/A package',
            'version_label' => '1.0',
            'locale' => 'en',
            'sections' => techDocSectionUpdatePayload($package->sections, [
                'sbom' => [
                    'is_applicable' => false,
                    'override_reason' => 'Tracked externally.',
                    'body_markdown' => 'Do not overwrite me.',
                ],
            ]),
        ])
        ->assertRedirect();

    $before = techDocSectionByKey($package->fresh()->load('sections'), TechnicalDocumentationSectionKey::Sbom);
    $beforeGeneratedAt = $before?->generated_payload['generated_at'] ?? null;

    $this->actingAs($owner)
        ->post(route('products.technical-documentation.refresh-generated', [$product, $package]))
        ->assertRedirect();

    $after = techDocSectionByKey($package->fresh()->load('sections'), TechnicalDocumentationSectionKey::Sbom);

    expect($after?->is_applicable)->toBeFalse();
    expect($after?->body_markdown)->toBe('Do not overwrite me.');
    expect($after?->generated_payload['generated_at'] ?? null)->toBe($beforeGeneratedAt);
});

test('viewer cannot refresh generated technical documentation', function () {
    ['organization' => $organization, 'owner' => $owner, 'product' => $product] = makeTechDocOrgWithOwner();
    $viewer = makeTechDocOrgViewer($organization);

    $this->actingAs($owner)
        ->post(route('products.technical-documentation.store', $product), [
            'title' => 'Locked refresh',
            'version_label' => '1.0',
            'locale' => 'en',
        ])
        ->assertRedirect();

    $package = TechnicalDocumentationPackage::query()
        ->where('product_id', $product->id)
        ->firstOrFail();

    $this->actingAs($viewer)
        ->post(route('products.technical-documentation.refresh-generated', [$product, $package]))
        ->assertForbidden();
});
