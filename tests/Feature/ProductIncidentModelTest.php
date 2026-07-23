<?php

use App\Enums\ClassificationStatus;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ProductVersionState;
use App\Enums\ScopeStatus;
use App\Enums\SupportStatus;
use App\Models\IncidentTimelineEvent;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductIncident;
use App\Models\ProductVersion;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{organization: Organization, product: Product, version: ProductVersion}
 */
function makeIncidentModelFixture(): array
{
    $organization = Organization::query()->create([
        'name' => 'Incident Model Org',
        'slug' => 'incident-model-org-' . uniqid(),
        'is_active' => true,
        'locale' => 'en',
    ]);

    $product = Product::query()->create([
        'organization_id' => $organization->id,
        'name' => 'Incident Model Product',
        'slug' => 'incident-model-product-' . uniqid(),
        'product_type' => ProductType::Software,
        'licensing_model' => LicensingModel::Paid,
        'has_remote_data_processing' => false,
        'has_network_connectivity' => true,
        'scope_status' => ScopeStatus::LikelyInScope,
        'classification_status' => ClassificationStatus::General,
    ]);

    $version = ProductVersion::query()->create([
        'product_id' => $product->id,
        'version_number' => '1.0.0',
        'state' => ProductVersionState::Released,
        'support_status' => SupportStatus::Supported,
    ]);

    return compact('organization', 'product', 'version');
}

test('product incident persists with enums casts and relations', function () {
    ['organization' => $organization, 'product' => $product, 'version' => $version] = makeIncidentModelFixture();

    $incident = ProductIncident::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Suspicious auth spikes',
        'status' => IncidentStatus::Open,
        'severity' => IncidentSeverity::High,
        'summary' => 'Elevated failed logins.',
        'awareness_at' => now(),
    ]);

    $incident->versions()->attach($version->id);

    $event = IncidentTimelineEvent::query()->create([
        'incident_id' => $incident->id,
        'occurred_at' => now()->subHour(),
        'label' => 'Customer report',
        'notes' => 'Support ticket received.',
    ]);

    $incident->refresh()->load(['timelineEvents', 'versions', 'product', 'organization']);

    expect($incident->status)->toBe(IncidentStatus::Open)
        ->and($incident->severity)->toBe(IncidentSeverity::High)
        ->and($incident->isOpen())->toBeTrue()
        ->and($incident->isTerminal())->toBeFalse()
        ->and($incident->product->is($product))->toBeTrue()
        ->and($incident->organization->is($organization))->toBeTrue()
        ->and($incident->versions)->toHaveCount(1)
        ->and($incident->versions->first()->id)->toBe($version->id)
        ->and($incident->timelineEvents)->toHaveCount(1)
        ->and($incident->timelineEvents->first()->is($event))->toBeTrue()
        ->and($product->incidents()->count())->toBe(1);
});

test('incident version pivot enforces uniqueness', function () {
    ['organization' => $organization, 'product' => $product, 'version' => $version] = makeIncidentModelFixture();

    $incident = ProductIncident::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Duplicate pivot check',
        'status' => IncidentStatus::Investigating,
        'severity' => IncidentSeverity::Medium,
    ]);

    $incident->versions()->attach($version->id);

    expect(fn() => $incident->versions()->attach($version->id))
        ->toThrow(QueryException::class);
});

test('deleting incident cascades timeline events and version pivots', function () {
    ['organization' => $organization, 'product' => $product, 'version' => $version] = makeIncidentModelFixture();

    $incident = ProductIncident::query()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'title' => 'Cascade check',
        'status' => IncidentStatus::Contained,
        'severity' => IncidentSeverity::Low,
    ]);

    $incident->versions()->attach($version->id);
    IncidentTimelineEvent::query()->create([
        'incident_id' => $incident->id,
        'occurred_at' => now(),
        'label' => 'Mitigation applied',
    ]);

    $incidentId = $incident->id;
    $incident->delete();

    expect(ProductIncident::query()->whereKey($incidentId)->exists())->toBeFalse()
        ->and(IncidentTimelineEvent::query()->where('incident_id', $incidentId)->exists())->toBeFalse()
        ->and(
            \Illuminate\Support\Facades\DB::table('incident_product_versions')
                ->where('incident_id', $incidentId)
                ->exists(),
        )->toBeFalse();
});

test('incident status helpers recognise terminal states', function () {
    expect(IncidentStatus::Closed->isTerminal())->toBeTrue()
        ->and(IncidentStatus::Cancelled->isTerminal())->toBeTrue()
        ->and(IncidentStatus::Open->isTerminal())->toBeFalse()
        ->and(IncidentStatus::active())->toContain(IncidentStatus::Investigating);
});
