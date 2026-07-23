<?php

use App\Enums\AuditEventType;
use App\Enums\ClassificationStatus;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductIncident;
use App\Models\Role;
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
 *     incident: ProductIncident
 * }
 */
function makeIncidentExportFixture(): array
{
    test()->seed([RolePermissionSeeder::class]);

    $organization = Organization::query()->create([
        'name' => 'Incident Export Org',
        'slug' => 'incident-export-org-' . uniqid(),
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
        'name' => 'Incident Export Product',
        'slug' => 'incident-export-product-' . uniqid(),
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

    $incident = ProductIncident::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Exportable incident',
        'summary' => 'Summary for export coverage.',
        'status' => IncidentStatus::Investigating,
        'severity' => IncidentSeverity::High,
        'confidentiality_impact' => 'high',
        'integrity_impact' => 'none',
        'availability_impact' => 'low',
        'attack_vector' => 'network',
        'root_cause' => 'Misconfigured firewall rule',
        'corrective_measures' => 'Restored deny-by-default policy',
        'lessons_learned' => 'Add change review checklist',
        'awareness_at' => now()->subHours(3),
        'classified_at' => now()->subHours(2),
        'owner_user_id' => $owner->id,
    ]);

    return compact('organization', 'owner', 'viewer', 'product', 'incident');
}

test('owner can export incident as markdown and pdf with audit', function () {
    ['owner' => $owner, 'product' => $product, 'incident' => $incident] = makeIncidentExportFixture();

    $markdown = $this->actingAs($owner)
        ->get(route('products.incidents.export', [
            'product' => $product,
            'incident' => $incident,
            'format' => 'markdown',
        ]))
        ->assertOk();

    expect($markdown->headers->get('content-type'))->toContain('text/markdown')
        ->and($markdown->getContent())->toContain('Exportable incident')
        ->and($markdown->getContent())->toContain('Misconfigured firewall rule')
        ->and($markdown->getContent())->toContain('Attack vector')
        ->and($markdown->getContent())->toContain('Network');

    $pdf = $this->actingAs($owner)
        ->get(route('products.incidents.export', [
            'product' => $product,
            'incident' => $incident,
            'format' => 'pdf',
        ]))
        ->assertOk();

    expect($pdf->headers->get('content-type'))->toContain('application/pdf')
        ->and($pdf->getContent())->toStartWith('%PDF');

    expect(
        AuditLog::query()
            ->where('event_type', AuditEventType::IncidentExported->value)
            ->count(),
    )->toBe(2);
});

test('viewer can export incident summary', function () {
    ['viewer' => $viewer, 'product' => $product, 'incident' => $incident] = makeIncidentExportFixture();

    $this->actingAs($viewer)
        ->get(route('products.incidents.export', [
            'product' => $product,
            'incident' => $incident,
            'format' => 'markdown',
        ]))
        ->assertOk();
});

test('invalid incident export format is not found', function () {
    ['owner' => $owner, 'product' => $product, 'incident' => $incident] = makeIncidentExportFixture();

    $this->actingAs($owner)
        ->get(route('products.incidents.export', [
            'product' => $product,
            'incident' => $incident,
            'format' => 'html',
        ]))
        ->assertNotFound();
});
