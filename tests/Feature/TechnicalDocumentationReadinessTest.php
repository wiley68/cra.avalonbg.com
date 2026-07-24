<?php

use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\TechnicalDocumentationSectionKey;
use App\Enums\TechnicalDocumentationSectionSource;
use App\Enums\TechnicalDocumentationStatus;
use App\Enums\UserSecurityInstructionStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Role;
use App\Models\TechnicalDocumentationPackage;
use App\Models\TechnicalDocumentationSection;
use App\Models\User;
use App\Models\UserSecurityInstruction;
use App\Services\ProductReadinessService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User, product: Product}
 */
function makeTechDocReadinessFixture(
    ScopeStatus $scope = ScopeStatus::LikelyInScope,
): array {
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Tech Doc Readiness Org',
        'slug' => 'tech-doc-readiness-org-' . uniqid(),
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
        'name' => 'Tech Doc Readiness Product',
        'slug' => 'tech-doc-readiness-product-' . uniqid(),
        'manufacturer' => 'Acme Soft',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => true,
        'has_network_connectivity' => true,
        'scope_status' => $scope,
        'classification_status' => ClassificationStatus::General,
        'scope_reviewed_at' => now(),
        'scope_reviewed_by' => $owner->id,
        'classification_reviewed_at' => now(),
        'classification_reviewed_by' => $owner->id,
    ]);

    return compact('organization', 'owner', 'product');
}

/**
 * @return TechnicalDocumentationPackage
 */
function seedTechDocPackage(
    Organization $organization,
    Product $product,
    User $owner,
    TechnicalDocumentationStatus $status,
    bool $fillSections = true,
    ?int $usiId = null,
): TechnicalDocumentationPackage {
    $package = TechnicalDocumentationPackage::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Readiness package',
        'status' => $status,
        'version_label' => '1.0',
        'locale' => 'en',
        'published_at' => $status === TechnicalDocumentationStatus::Published ? now() : null,
        'published_by' => $status === TechnicalDocumentationStatus::Published ? $owner->id : null,
        'user_security_instruction_id' => $usiId,
    ]);

    foreach (TechnicalDocumentationSectionKey::ordered() as $key) {
        $source = $key->defaultSource();
        $body = null;
        $payload = null;

        if ($fillSections && $status === TechnicalDocumentationStatus::Published) {
            if ($source === TechnicalDocumentationSectionSource::Authored) {
                $body = 'Authored content for ' . $key->value;
            }
            if ($source === TechnicalDocumentationSectionSource::Generated) {
                $payload = [
                    'markdown' => 'Generated ' . $key->value,
                    'generated_at' => now()->toIso8601String(),
                ];
            }
        }

        TechnicalDocumentationSection::query()->create([
            'package_id' => $package->id,
            'section_key' => $key,
            'source' => $source,
            'body_markdown' => $body,
            'generated_payload' => $payload,
            'sort_order' => $key->defaultSortOrder(),
            'is_applicable' => true,
            'override_reason' => null,
            'changed_since_parent' => false,
        ]);
    }

    return $package;
}

test('in-scope product without published tech-doc produces missing gap', function () {
    ['owner' => $owner, 'organization' => $organization, 'product' => $product] = makeTechDocReadinessFixture();

    seedTechDocPackage(
        $organization,
        $product,
        $owner,
        TechnicalDocumentationStatus::Draft,
        false,
    );

    $this->actingAs($owner)
        ->get(route('products.readiness.show', $product))
        ->assertOk()
        ->assertInertia(function ($page) {
            $props = $page->toArray()['props'];
            $sections = collect($props['report']['sections']);
            $gaps = collect($props['report']['gaps']);
            $section = $sections->firstWhere('key', 'technical_documentation');

            expect($section['status'])->toBe('fail')
                ->and($section['summary'])->toBe('draft_or_review')
                ->and($section['metrics']['published'])->toBe(0)
                ->and($section['metrics']['draft_or_review'])->toBe(1)
                ->and($gaps->contains(
                    fn($gap) => $gap['message_key'] === 'products.readiness.gaps.technical_documentation_missing'
                    && $gap['section'] === 'technical_documentation'
                    && $gap['status'] === 'fail'
                    && $gap['link'] === 'technical-documentation',
                ))->toBeTrue();
        });
});

test('published tech-doc with linked USI clears readiness gap', function () {
    ['owner' => $owner, 'organization' => $organization, 'product' => $product] = makeTechDocReadinessFixture();

    $usi = UserSecurityInstruction::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Published USI',
        'status' => UserSecurityInstructionStatus::Published,
        'version_label' => '1.0',
        'locale' => 'en',
        'published_at' => now(),
        'published_by' => $owner->id,
    ]);

    seedTechDocPackage(
        $organization,
        $product,
        $owner,
        TechnicalDocumentationStatus::Published,
        true,
        $usi->id,
    );

    $this->actingAs($owner)
        ->get(route('products.readiness.show', $product))
        ->assertOk()
        ->assertInertia(function ($page) {
            $props = $page->toArray()['props'];
            $sections = collect($props['report']['sections']);
            $gaps = collect($props['report']['gaps']);
            $section = $sections->firstWhere('key', 'technical_documentation');

            expect($section['status'])->toBe('pass')
                ->and($section['summary'])->toBe('published')
                ->and($section['metrics']['published'])->toBe(1)
                ->and($section['metrics']['linked_usi'])->toBeTrue()
                ->and($gaps->contains(
                    fn($gap) => $gap['section'] === 'technical_documentation',
                ))->toBeFalse();
        });
});

test('published tech-doc without linked USI produces warn gap', function () {
    ['owner' => $owner, 'organization' => $organization, 'product' => $product] = makeTechDocReadinessFixture();

    seedTechDocPackage(
        $organization,
        $product,
        $owner,
        TechnicalDocumentationStatus::Published,
        true,
    );

    $this->actingAs($owner)
        ->get(route('products.readiness.show', $product))
        ->assertOk()
        ->assertInertia(function ($page) {
            $props = $page->toArray()['props'];
            $sections = collect($props['report']['sections']);
            $gaps = collect($props['report']['gaps']);
            $section = $sections->firstWhere('key', 'technical_documentation');

            expect($section['status'])->toBe('warn')
                ->and($section['summary'])->toBe('usi_unlinked')
                ->and($gaps->contains(
                    fn($gap) => $gap['message_key'] === 'products.readiness.gaps.technical_documentation_usi_unlinked'
                    && $gap['link'] === 'technical-documentation'
                    && $gap['status'] === 'warn',
                ))->toBeTrue();
        });
});

test('published tech-doc with empty authored sections produces incomplete gap', function () {
    ['owner' => $owner, 'organization' => $organization, 'product' => $product] = makeTechDocReadinessFixture();

    $package = seedTechDocPackage(
        $organization,
        $product,
        $owner,
        TechnicalDocumentationStatus::Published,
        true,
    );

    $package->sections()
        ->where('section_key', TechnicalDocumentationSectionKey::Architecture->value)
        ->update(['body_markdown' => null]);

    $this->actingAs($owner)
        ->get(route('products.readiness.show', $product))
        ->assertOk()
        ->assertInertia(function ($page) {
            $props = $page->toArray()['props'];
            $sections = collect($props['report']['sections']);
            $gaps = collect($props['report']['gaps']);
            $section = $sections->firstWhere('key', 'technical_documentation');

            expect($section['status'])->toBe('fail')
                ->and($section['summary'])->toBe('incomplete')
                ->and($section['metrics']['sections_incomplete'])->toBeGreaterThan(0)
                ->and($gaps->contains(
                    fn($gap) => $gap['message_key'] === 'products.readiness.gaps.technical_documentation_incomplete',
                ))->toBeTrue();
        });
});

test('out-of-scope product does not require technical documentation', function () {
    ['owner' => $owner, 'product' => $product] = makeTechDocReadinessFixture(ScopeStatus::OutOfScope);

    $this->actingAs($owner)
        ->get(route('products.readiness.show', $product))
        ->assertOk()
        ->assertInertia(function ($page) {
            $props = $page->toArray()['props'];
            $sections = collect($props['report']['sections']);
            $gaps = collect($props['report']['gaps']);
            $section = $sections->firstWhere('key', 'technical_documentation');

            expect($section['status'])->toBe('na')
                ->and($section['summary'])->toBe('not_required')
                ->and($gaps->contains(
                    fn($gap) => $gap['section'] === 'technical_documentation',
                ))->toBeFalse();
        });
});

test('missing tech-doc marks readiness card incomplete', function () {
    ['owner' => $owner, 'product' => $product] = makeTechDocReadinessFixture();

    $statuses = app(ProductReadinessService::class)->cardModuleStatuses($product);

    expect($statuses['readiness'])->toBe('incomplete');
});
