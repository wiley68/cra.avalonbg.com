<?php

use App\Enums\AuditEventType;
use App\Enums\ClassificationStatus;
use App\Enums\EvidenceType;
use App\Enums\ImportSuggestionKind;
use App\Enums\ImportSuggestionStatus;
use App\Enums\IntegrationAuthType;
use App\Enums\IntegrationCategory;
use App\Enums\IntegrationConnectionStatus;
use App\Enums\IntegrationProvider;
use App\Enums\IntegrationSyncRunStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Enums\VulnerabilityDiscoverySource;
use App\Enums\VulnerabilityStatus;
use App\Models\AuditLog;
use App\Models\Evidence;
use App\Models\ImportSuggestion;
use App\Models\IntegrationSyncRun;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Models\Product;
use App\Models\ProductIntegrationLink;
use App\Models\ProductVulnerability;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, owner: User}
 */
function makeSarifSettingsFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'SARIF Settings Org',
        'slug' => 'sarif-settings-org-' . uniqid(),
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

    return compact('organization', 'owner');
}

/**
 * @return array{
 *     organization: Organization,
 *     owner: User,
 *     product: Product,
 *     integration: OrganizationIntegration
 * }
 */
function makeSarifProductFixture(): array
{
    ['organization' => $organization, 'owner' => $owner] = makeSarifSettingsFixture();

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'SARIF Product',
        'slug' => 'sarif-product',
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => false,
        'scope_status' => ScopeStatus::InsufficientInformation,
        'classification_status' => ClassificationStatus::Unclassified,
    ]);

    $integration = OrganizationIntegration::query()->create([
        'organization_id' => $organization->id,
        'provider' => IntegrationProvider::Sarif,
        'category' => IntegrationCategory::Scanner,
        'auth_type' => IntegrationAuthType::None,
        'credentials' => null,
        'label' => 'SARIF / Trivy',
        'status' => IntegrationConnectionStatus::Active,
        'last_verified_at' => now(),
    ]);

    return compact('organization', 'owner', 'product', 'integration');
}

function sarifFixtureFile(string $name = 'trivy-sample.sarif.json'): UploadedFile
{
    $path = base_path('tests/Fixtures/sarif/' . $name);

    return new UploadedFile($path, $name, 'application/json', null, true);
}

test('owner can enable org SARIF uploads without a token', function () {
    ['owner' => $owner] = makeSarifSettingsFixture();

    $this->actingAs($owner)
        ->post(route('settings.integrations.sarif.store'), [
            'label' => 'CI SARIF',
        ])
        ->assertRedirect();

    $integration = OrganizationIntegration::query()->first();

    expect($integration)->not->toBeNull()
        ->and($integration->provider)->toBe(IntegrationProvider::Sarif)
        ->and($integration->auth_type)->toBe(IntegrationAuthType::None)
        ->and($integration->credentials)->toBeNull()
        ->and($integration->label)->toBe('CI SARIF')
        ->and(AuditLog::query()->where('event_type', AuditEventType::IntegrationConnected)->count())->toBe(1);
});

test('product SARIF upload creates pending vulnerability suggestions', function () {
    Storage::fake('local');

    ['owner' => $owner, 'product' => $product] = makeSarifProductFixture();

    $this->actingAs($owner)
        ->post(route('products.integrations.sarif.upload', $product), [
            'file' => sarifFixtureFile(),
        ])
        ->assertRedirect();

    $link = ProductIntegrationLink::query()->first();
    $suggestion = ImportSuggestion::query()->first();
    $run = IntegrationSyncRun::query()->first();
    $scanEvidence = Evidence::query()->where('type', EvidenceType::VulnerabilityScan)->first();
    $snapshot = Evidence::query()->where('type', EvidenceType::IntegrationSnapshot)->first();

    expect($link)->not->toBeNull()
        ->and($link->integration->provider)->toBe(IntegrationProvider::Sarif)
        ->and($run?->status)->toBe(IntegrationSyncRunStatus::Succeeded)
        ->and($run?->summary['soft_fail'] ?? false)->toBeFalse()
        ->and($run?->summary['tool_name'] ?? null)->toBe('Trivy')
        ->and($run?->summary['findings_count'] ?? null)->toBe(1)
        ->and($suggestion)->not->toBeNull()
        ->and($suggestion->kind)->toBe(ImportSuggestionKind::Vulnerability)
        ->and($suggestion->status)->toBe(ImportSuggestionStatus::Pending)
        ->and($suggestion->payload['cve_id'] ?? null)->toBe('CVE-2026-4242')
        ->and($suggestion->payload['package_name'] ?? null)->toBe('lodash')
        ->and($scanEvidence)->not->toBeNull()
        ->and($snapshot)->not->toBeNull();
});

test('re-uploading the same SARIF upserts without duplicate pending suggestions', function () {
    Storage::fake('local');

    ['owner' => $owner, 'product' => $product] = makeSarifProductFixture();

    $this->actingAs($owner)
        ->post(route('products.integrations.sarif.upload', $product), [
            'file' => sarifFixtureFile(),
        ])
        ->assertRedirect();

    $this->actingAs($owner)
        ->post(route('products.integrations.sarif.upload', $product), [
            'file' => sarifFixtureFile(),
        ])
        ->assertRedirect();

    expect(ImportSuggestion::query()->where('status', ImportSuggestionStatus::Pending)->count())->toBe(1)
        ->and(IntegrationSyncRun::query()->count())->toBe(2);
});

test('invalid SARIF soft-fails without creating suggestions', function () {
    Storage::fake('local');

    ['owner' => $owner, 'product' => $product] = makeSarifProductFixture();

    $invalid = UploadedFile::fake()->createWithContent('broken.sarif.json', '{not-json');

    $this->actingAs($owner)
        ->post(route('products.integrations.sarif.upload', $product), [
            'file' => $invalid,
        ])
        ->assertRedirect();

    $run = IntegrationSyncRun::query()->first();

    expect($run?->status)->toBe(IntegrationSyncRunStatus::Succeeded)
        ->and($run?->summary['soft_fail'] ?? false)->toBeTrue()
        ->and($run?->summary['last_error'] ?? null)->not->toBeNull()
        ->and(ImportSuggestion::query()->count())->toBe(0);
});

test('accept SARIF suggestion creates product vulnerability', function () {
    Storage::fake('local');

    ['owner' => $owner, 'product' => $product] = makeSarifProductFixture();

    $this->actingAs($owner)
        ->post(route('products.integrations.sarif.upload', $product), [
            'file' => sarifFixtureFile(),
        ])
        ->assertRedirect();

    $suggestion = ImportSuggestion::query()->firstOrFail();

    $this->actingAs($owner)
        ->post(route('products.import-suggestions.accept', [$product, $suggestion]))
        ->assertRedirect();

    $vulnerability = ProductVulnerability::query()->first();

    expect($vulnerability)->not->toBeNull()
        ->and($vulnerability->cve_id)->toBe('CVE-2026-4242')
        ->and($vulnerability->discovery_source)->toBe(VulnerabilityDiscoverySource::DependencyScanner)
        ->and($vulnerability->status)->toBe(VulnerabilityStatus::Reported)
        ->and($suggestion->fresh()->status)->toBe(ImportSuggestionStatus::Accepted);
});

test('product edit exposes SARIF integration props', function () {
    ['owner' => $owner, 'product' => $product, 'integration' => $integration] = makeSarifProductFixture();

    ProductIntegrationLink::query()->create([
        'product_id' => $product->id,
        'integration_id' => $integration->id,
        'external_label' => 'SARIF uploads',
        'config' => ['source' => 'upload'],
    ]);

    $this->actingAs($owner)
        ->get(route('products.edit', $product))
        ->assertOk()
        ->assertInertia(fn(Assert $page) => $page
            ->component('products/Edit')
            ->where('sarif_integration.connected', true)
            ->where(
                'sarif_link.external_label',
                fn(mixed $label): bool => $label === 'SARIF uploads',
            ));
});
