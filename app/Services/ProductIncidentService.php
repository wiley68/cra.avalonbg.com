<?php

namespace App\Services;

use App\Enums\IncidentSeverity;
use App\Enums\VulnerabilityBusinessSeverity;
use App\Enums\VulnerabilityDiscoverySource;
use App\Enums\VulnerabilityExploitationStatus;
use App\Enums\VulnerabilityStatus;
use App\Models\IncidentTimelineEvent;
use App\Models\Product;
use App\Models\ProductIncident;
use App\Models\ProductVersion;
use App\Models\ProductVulnerability;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductIncidentService
{
    public function __construct(
        private readonly ProductVulnerabilityService $vulnerabilities,
    ) {
    }
    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(
        Product $product,
        int $perPage = 10,
        int $page = 1,
        string $sortBy = 'title',
        string $sortOrder = 'asc',
        string $search = '',
    ): LengthAwarePaginator {
        $query = ProductIncident::query()
            ->where('product_id', $product->id)
            ->with('owner');

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('severity', 'like', "%{$search}%")
                    ->orWhere('summary', 'like', "%{$search}%");

                if (ctype_digit($search)) {
                    $builder->orWhere('id', (int) $search);
                }
            });
        }

        $orderColumn = match ($sortBy) {
            'id' => 'id',
            'status' => 'status',
            'severity' => 'severity',
            'awareness_at' => 'awareness_at',
            'detected_at' => 'detected_at',
            'classified_at' => 'classified_at',
            default => 'title',
        };

        $query->orderBy($orderColumn, $sortOrder === 'desc' ? 'desc' : 'asc');

        return $query
            ->paginate($perPage, ['*'], 'page', $page)
            ->through(fn(ProductIncident $incident) => $this->listItemPayload($incident));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $versionIds
     */
    public function create(Product $product, array $attributes, array $versionIds): ProductIncident
    {
        return DB::transaction(function () use ($product, $attributes, $versionIds) {
            $this->assertVersionsBelongToProduct($product, $versionIds);

            /** @var ProductIncident $incident */
            $incident = ProductIncident::query()->create([
                ...$attributes,
                'organization_id' => $product->organization_id,
                'product_id' => $product->id,
            ]);

            $incident->versions()->sync($versionIds);

            return $incident->load(['owner', 'versions']);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $versionIds
     */
    public function update(ProductIncident $incident, array $attributes, array $versionIds): ProductIncident
    {
        return DB::transaction(function () use ($incident, $attributes, $versionIds) {
            $this->assertVersionsBelongToProduct($incident->product, $versionIds);

            $incident->update($attributes);
            $incident->versions()->sync($versionIds);

            return $incident->fresh(['owner', 'versions']);
        });
    }

    public function delete(ProductIncident $incident): void
    {
        $incident->delete();
    }

    /**
     * @param  array{occurred_at: mixed, label: string, notes?: string|null}  $attributes
     */
    public function addTimelineEvent(
        ProductIncident $incident,
        array $attributes,
        ?User $actor = null,
    ): IncidentTimelineEvent {
        /** @var IncidentTimelineEvent $event */
        $event = $incident->timelineEvents()->create([
            'occurred_at' => $attributes['occurred_at'],
            'label' => $attributes['label'],
            'notes' => $attributes['notes'] ?? null,
            'created_by' => $actor?->id,
        ]);

        return $event->load('creator');
    }

    public function linkVulnerability(
        ProductIncident $incident,
        ProductVulnerability $vulnerability,
    ): ProductIncident {
        if ($vulnerability->product_id !== $incident->product_id) {
            throw ValidationException::withMessages([
                'product_vulnerability_id' => [
                    'The vulnerability must belong to the same product.',
                ],
            ]);
        }

        $incident->update([
            'product_vulnerability_id' => $vulnerability->id,
        ]);

        return $incident->fresh(['owner', 'versions', 'vulnerability']);
    }

    public function unlinkVulnerability(ProductIncident $incident): ProductIncident
    {
        $incident->update([
            'product_vulnerability_id' => null,
        ]);

        return $incident->fresh(['owner', 'versions', 'vulnerability']);
    }

    /**
     * Create a vulnerability from the incident and link it.
     * Discovery source is always incident_investigation.
     */
    public function createVulnerabilityFromIncident(ProductIncident $incident): ProductVulnerability
    {
        return DB::transaction(function () use ($incident) {
            $incident->loadMissing(['versions', 'product']);

            $versionIds = $incident->versions->pluck('id')->map(fn($id) => (int) $id)->all();

            $vulnerability = $this->vulnerabilities->create(
                $incident->product,
                [
                    'title' => $incident->title,
                    'summary' => $incident->summary,
                    'cve_id' => null,
                    'advisory_url' => null,
                    'discovery_source' => VulnerabilityDiscoverySource::IncidentInvestigation,
                    'discovered_at' => $incident->detected_at,
                    'awareness_at' => $incident->awareness_at,
                    'status' => VulnerabilityStatus::Reported,
                    'cvss_score' => null,
                    'business_severity' => $this->mapSeverity($incident->severity),
                    'exploitation_status' => VulnerabilityExploitationStatus::Unknown,
                    'is_public' => false,
                    'workaround' => null,
                    'corrective_action' => $incident->corrective_measures,
                    'owner_user_id' => $incident->owner_user_id,
                    'substitute_owner_user_id' => null,
                    'corrective_measure_available_at' => null,
                    'notes' => $incident->notes,
                ],
                [],
                $versionIds,
                [],
            );

            $incident->update([
                'product_vulnerability_id' => $vulnerability->id,
            ]);

            return $vulnerability;
        });
    }

    /**
     * @return array{id: int, title: string, cve_id: string|null, status: string, business_severity: string}|null
     */
    public function linkedVulnerabilityPayload(ProductIncident $incident): ?array
    {
        $vulnerability = $incident->vulnerability;

        if ($vulnerability === null) {
            return null;
        }

        return [
            'id' => $vulnerability->id,
            'title' => $vulnerability->title,
            'cve_id' => $vulnerability->cve_id,
            'status' => $vulnerability->status->value,
            'business_severity' => $vulnerability->business_severity->value,
        ];
    }

    /**
     * @return list<array{id: int, title: string, cve_id: string|null, status: string}>
     */
    public function linkableVulnerabilityOptions(Product $product): array
    {
        return ProductVulnerability::query()
            ->where('product_id', $product->id)
            ->orderByDesc('id')
            ->get(['id', 'title', 'cve_id', 'status'])
            ->map(fn(ProductVulnerability $vulnerability) => [
                'id' => $vulnerability->id,
                'title' => $vulnerability->title,
                'cve_id' => $vulnerability->cve_id,
                'status' => $vulnerability->status->value,
            ])
            ->all();
    }

    private function mapSeverity(IncidentSeverity $severity): VulnerabilityBusinessSeverity
    {
        return VulnerabilityBusinessSeverity::from($severity->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function listItemPayload(ProductIncident $incident): array
    {
        return [
            'id' => $incident->id,
            'title' => $incident->title,
            'status' => $incident->status->value,
            'severity' => $incident->severity->value,
            'owner_name' => $incident->owner?->name,
            'awareness_at' => $incident->awareness_at?->toIso8601String(),
            'detected_at' => $incident->detected_at?->toIso8601String(),
            'classified_at' => $incident->classified_at?->toIso8601String(),
            'product_vulnerability_id' => $incident->product_vulnerability_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detailPayload(ProductIncident $incident): array
    {
        if (!$incident->relationLoaded('timelineEvents')) {
            $incident->load(['timelineEvents.creator']);
        } elseif (
            $incident->timelineEvents->isNotEmpty()
            && !$incident->timelineEvents->first()?->relationLoaded('creator')
        ) {
            $incident->load(['timelineEvents.creator']);
        }

        if (!$incident->relationLoaded('vulnerability')) {
            $incident->load('vulnerability');
        }

        return [
            'id' => $incident->id,
            'title' => $incident->title,
            'status' => $incident->status->value,
            'severity' => $incident->severity->value,
            'summary' => $incident->summary,
            'root_cause' => $incident->root_cause,
            'corrective_measures' => $incident->corrective_measures,
            'lessons_learned' => $incident->lessons_learned,
            'product_vulnerability_id' => $incident->product_vulnerability_id,
            'linked_vulnerability' => $this->linkedVulnerabilityPayload($incident),
            'owner_user_id' => $incident->owner_user_id,
            'actual_started_at' => $incident->actual_started_at?->format('Y-m-d\TH:i'),
            'detected_at' => $incident->detected_at?->format('Y-m-d\TH:i'),
            'awareness_at' => $incident->awareness_at?->format('Y-m-d\TH:i'),
            'classified_at' => $incident->classified_at?->format('Y-m-d\TH:i'),
            'closed_at' => $incident->closed_at?->format('Y-m-d\TH:i'),
            'notes' => $incident->notes,
            'version_ids' => $incident->versions->pluck('id')->all(),
            'timeline_events' => $incident->timelineEvents
                ->map(fn(IncidentTimelineEvent $event) => $this->timelineEventPayload($event))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     occurred_at: string,
     *     label: string,
     *     notes: string|null,
     *     created_by: string|null,
     *     created_at: string|null
     * }
     */
    public function timelineEventPayload(IncidentTimelineEvent $event): array
    {
        return [
            'id' => $event->id,
            'occurred_at' => $event->occurred_at->toIso8601String(),
            'label' => $event->label,
            'notes' => $event->notes,
            'created_by' => $event->creator?->name,
            'created_at' => $event->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  list<int>  $versionIds
     */
    private function assertVersionsBelongToProduct(Product $product, array $versionIds): void
    {
        if ($versionIds === []) {
            return;
        }

        $uniqueIds = array_values(array_unique(array_map('intval', $versionIds)));
        $count = ProductVersion::query()
            ->where('product_id', $product->id)
            ->whereIn('id', $uniqueIds)
            ->count();

        if ($count !== count($uniqueIds)) {
            throw ValidationException::withMessages([
                'version_ids' => ['One or more versions do not belong to this product.'],
            ]);
        }
    }
}
