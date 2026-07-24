<?php

use App\Enums\AuditEventType;
use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\TechnicalDocumentationSectionKey;
use App\Enums\TechnicalDocumentationSectionSource;
use App\Enums\TechnicalDocumentationStatus;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Role;
use App\Models\TechnicalDocumentationPackage;
use App\Models\TechnicalDocumentationSection;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     organization: Organization,
 *     owner: User,
 *     viewer: User,
 *     product: Product,
 *     package: TechnicalDocumentationPackage
 * }
 */
function makeTechDocExportFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Tech Doc Export Org',
        'slug' => 'tech-doc-export-org-' . uniqid(),
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
        'name' => 'Tech Doc Export Product',
        'slug' => 'tech-doc-export-product-' . uniqid(),
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

    $package = TechnicalDocumentationPackage::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Exportable tech doc package',
        'status' => TechnicalDocumentationStatus::Draft,
        'version_label' => '1.0',
        'locale' => 'en',
        'notes' => 'Internal export notes for assessors.',
    ]);

    TechnicalDocumentationSection::query()->create([
        'package_id' => $package->id,
        'section_key' => TechnicalDocumentationSectionKey::Architecture,
        'source' => TechnicalDocumentationSectionSource::Authored,
        'body_markdown' => "## Architecture overview\n\n- Edge gateway\n- Secure boot chain",
        'generated_payload' => null,
        'sort_order' => 1,
        'is_applicable' => true,
        'override_reason' => null,
        'changed_since_parent' => false,
    ]);

    TechnicalDocumentationSection::query()->create([
        'package_id' => $package->id,
        'section_key' => TechnicalDocumentationSectionKey::Sbom,
        'source' => TechnicalDocumentationSectionSource::Generated,
        'body_markdown' => 'Manual SBOM notes kept after refresh.',
        'generated_payload' => [
            'markdown' => "## SBOM snapshot\n\n- openssl 3.0",
            'generated_at' => now()->toIso8601String(),
        ],
        'sort_order' => 2,
        'is_applicable' => true,
        'override_reason' => null,
        'changed_since_parent' => false,
    ]);

    TechnicalDocumentationSection::query()->create([
        'package_id' => $package->id,
        'section_key' => TechnicalDocumentationSectionKey::DeclarationInformation,
        'source' => TechnicalDocumentationSectionSource::Authored,
        'body_markdown' => null,
        'generated_payload' => null,
        'sort_order' => 3,
        'is_applicable' => false,
        'override_reason' => 'DoC handled outside this package.',
        'changed_since_parent' => false,
    ]);

    return compact('organization', 'owner', 'viewer', 'product', 'package');
}

test('owner can export technical documentation as markdown and pdf with audit', function () {
    ['owner' => $owner, 'product' => $product, 'package' => $package] = makeTechDocExportFixture();

    $markdown = $this->actingAs($owner)
        ->get(route('products.technical-documentation.export', [
            'product' => $product,
            'package' => $package,
            'format' => 'markdown',
        ]))
        ->assertOk();

    expect($markdown->headers->get('content-type'))->toContain('text/markdown')
        ->and($markdown->getContent())->toContain('Exportable tech doc package')
        ->and($markdown->getContent())->toContain('Architecture overview')
        ->and($markdown->getContent())->toContain('Secure boot chain')
        ->and($markdown->getContent())->toContain('SBOM snapshot')
        ->and($markdown->getContent())->toContain('Manual SBOM notes kept after refresh')
        ->and($markdown->getContent())->toContain('Internal export notes for assessors')
        ->and($markdown->getContent())->toContain('Not applicable');

    $pdf = $this->actingAs($owner)
        ->get(route('products.technical-documentation.export', [
            'product' => $product,
            'package' => $package,
            'format' => 'pdf',
        ]))
        ->assertOk();

    expect($pdf->headers->get('content-type'))->toContain('application/pdf')
        ->and($pdf->getContent())->toStartWith('%PDF');

    expect(
        AuditLog::query()
            ->where('event_type', AuditEventType::TechnicalDocumentationExported->value)
            ->count(),
    )->toBe(2);
});

test('viewer can export technical documentation summary', function () {
    ['viewer' => $viewer, 'product' => $product, 'package' => $package] = makeTechDocExportFixture();

    $this->actingAs($viewer)
        ->get(route('products.technical-documentation.export', [
            'product' => $product,
            'package' => $package,
            'format' => 'markdown',
        ]))
        ->assertOk();
});

test('invalid technical documentation export format is not found', function () {
    ['owner' => $owner, 'product' => $product, 'package' => $package] = makeTechDocExportFixture();

    $this->actingAs($owner)
        ->get('/products/' . $product->id . '/technical-documentation/' . $package->id . '/export/html')
        ->assertNotFound();
});
