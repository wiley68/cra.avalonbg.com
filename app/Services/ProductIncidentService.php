<?php

namespace App\Services;

use App\Enums\IncidentCommunicationChannel;
use App\Enums\IncidentReportChannel;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\VulnerabilityBusinessSeverity;
use App\Enums\VulnerabilityDiscoverySource;
use App\Enums\VulnerabilityExploitationStatus;
use App\Enums\VulnerabilityStatus;
use App\Models\Control;
use App\Models\Customer;
use App\Models\Evidence;
use App\Models\IncidentCustomerCommunication;
use App\Models\IncidentReport;
use App\Models\IncidentTimelineEvent;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductDeployment;
use App\Models\ProductIncident;
use App\Models\ProductVersion;
use App\Models\ProductVulnerability;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Translations;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductIncidentService
{
    public function __construct(
        private readonly ProductVulnerabilityService $vulnerabilities,
        private readonly TaskService $tasks,
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
            ->with(['owner', 'product:id,name']);

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
     * Org-wide incident list across all products in the tenant.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateForOrganization(
        Organization $organization,
        int $perPage = 10,
        int $page = 1,
        string $sortBy = 'title',
        string $sortOrder = 'asc',
        string $search = '',
    ): LengthAwarePaginator {
        $query = ProductIncident::query()
            ->where('organization_id', $organization->id)
            ->with(['owner', 'product:id,name']);

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('severity', 'like', "%{$search}%")
                    ->orWhere('summary', 'like', "%{$search}%")
                    ->orWhereHas(
                        'product',
                        fn($productQuery) => $productQuery->where('name', 'like', "%{$search}%"),
                    );

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
            'product_name' => 'product_id',
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
     * @param  list<int>  $customerIds
     * @param  list<int>  $versionIds
     * @param  list<int>  $customerIds
     * @param  list<int>  $deploymentIds
     * @param  list<int>  $evidenceIds
     * @param  list<int>  $controlIds
     */
    public function create(
        Product $product,
        array $attributes,
        array $versionIds,
        array $customerIds,
        array $deploymentIds,
        array $evidenceIds,
        array $controlIds,
        User $actor,
    ): ProductIncident {
        $incident = DB::transaction(function () use ($product, $attributes, $versionIds, $customerIds, $deploymentIds, $evidenceIds, $controlIds, ) {
            $this->assertVersionsBelongToProduct($product, $versionIds);
            $this->assertCustomersBelongToOrganization($product->organization_id, $customerIds);
            $this->assertDeploymentsBelongToProduct($product, $deploymentIds);
            $this->assertEvidenceBelongToProduct($product, $evidenceIds);
            $this->assertControlsBelongToOrganization($product->organization_id, $controlIds);

            /** @var ProductIncident $incident */
            $incident = ProductIncident::query()->create([
                ...$attributes,
                'organization_id' => $product->organization_id,
                'product_id' => $product->id,
            ]);

            $incident->versions()->sync($versionIds);
            $incident->customers()->sync($customerIds);
            $incident->deployments()->sync($deploymentIds);
            $incident->evidence()->sync($evidenceIds);
            $incident->controls()->sync($controlIds);

            return $incident->load(['owner', 'versions', 'customers', 'deployments', 'evidence', 'controls']);
        });

        AuditLogger::logIncidentCreated($incident, $actor);

        return $incident;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $versionIds
     * @param  list<int>  $customerIds
     * @param  list<int>  $deploymentIds
     * @param  list<int>  $evidenceIds
     * @param  list<int>  $controlIds
     */
    public function update(
        ProductIncident $incident,
        array $attributes,
        array $versionIds,
        array $customerIds,
        array $deploymentIds,
        array $evidenceIds,
        array $controlIds,
        User $actor,
    ): ProductIncident {
        $previousStatus = $incident->status->value;
        $attributes = $this->applyClosureTimestamps($incident, $attributes, $actor);

        $incident = DB::transaction(function () use ($incident, $attributes, $versionIds, $customerIds, $deploymentIds, $evidenceIds, $controlIds, ) {
            $this->assertVersionsBelongToProduct($incident->product, $versionIds);
            $this->assertCustomersBelongToOrganization($incident->organization_id, $customerIds);
            $this->assertDeploymentsBelongToProduct($incident->product, $deploymentIds);
            $this->assertEvidenceBelongToProduct($incident->product, $evidenceIds);
            $this->assertControlsBelongToOrganization($incident->organization_id, $controlIds);

            $incident->update($attributes);
            $incident->versions()->sync($versionIds);
            $incident->customers()->sync($customerIds);
            $incident->deployments()->sync($deploymentIds);
            $incident->evidence()->sync($evidenceIds);
            $incident->controls()->sync($controlIds);

            return $incident->fresh(['owner', 'versions', 'customers', 'deployments', 'evidence', 'controls', 'closer']);
        });

        if ($incident->status->value !== $previousStatus) {
            AuditLogger::logIncidentStatusUpdated($incident, $actor, $previousStatus);
        } else {
            AuditLogger::logIncidentUpdated($incident, $actor);
        }

        return $incident;
    }

    /**
     * Close an active incident, stamp closed_at/closed_by, optionally create a follow-up approval task.
     */
    public function close(
        ProductIncident $incident,
        User $actor,
        bool $createApprovalTask = false,
        ?int $assigneeUserId = null,
    ): ProductIncident {
        if ($incident->isTerminal()) {
            throw ValidationException::withMessages([
                'status' => [Translations::get('products.incidents.only_active_closable')],
            ]);
        }

        if ($incident->awareness_at === null) {
            throw ValidationException::withMessages([
                'awareness_at' => [Translations::get('products.incidents.close_requires_awareness')],
            ]);
        }

        if (blank($incident->root_cause)) {
            throw ValidationException::withMessages([
                'root_cause' => [Translations::get('products.incidents.close_requires_root_cause')],
            ]);
        }

        if (blank($incident->corrective_measures)) {
            throw ValidationException::withMessages([
                'corrective_measures' => [
                    Translations::get('products.incidents.close_requires_corrective_measures'),
                ],
            ]);
        }

        if ($assigneeUserId !== null) {
            $this->assertAssigneeBelongsToOrganization($incident->organization_id, $assigneeUserId);
        }

        $previousStatus = $incident->status->value;

        return DB::transaction(function () use ($incident, $actor, $createApprovalTask, $assigneeUserId, $previousStatus, ) {
            $incident->update([
                'status' => IncidentStatus::Closed,
                'closed_at' => now(),
                'closed_by' => $actor->id,
            ]);

            $fresh = $incident->fresh([
                'owner',
                'versions',
                'customers',
                'deployments',
                'closer',
                'product',
            ]);

            if ($createApprovalTask) {
                $this->tasks->create($fresh->product, [
                    'title' => Translations::get('products.incidents.closure_task_title', [
                        'title' => $fresh->title,
                    ]),
                    'description' => Translations::get('products.incidents.closure_task_description', [
                        'title' => $fresh->title,
                    ]),
                    'status' => TaskStatus::Open,
                    'priority' => TaskPriority::Medium,
                    'assignee_user_id' => $assigneeUserId
                        ?? $fresh->owner_user_id
                        ?? $actor->id,
                    'due_at' => now()->addDays(7),
                    'subject_type' => 'incident',
                    'subject_id' => $fresh->id,
                ], $actor);
            }

            AuditLogger::logIncidentClosed($fresh, $actor, $previousStatus);

            return $fresh;
        });
    }

    public function delete(ProductIncident $incident, ?User $actor = null): void
    {
        $actor ??= Auth::user();

        if ($actor instanceof User) {
            AuditLogger::logIncidentDeleted($incident, $actor);
        }

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

        $event->load('creator');

        if ($actor instanceof User) {
            AuditLogger::logIncidentTimelineEventAdded($incident, $event, $actor);
        }

        return $event;
    }

    /**
     * @param  array{
     *     authority: string,
     *     submitted_at: mixed,
     *     submission_channel: string,
     *     submission_reference?: string|null,
     *     summary?: string|null,
     *     notes?: string|null,
     *     evidence_id?: int|null
     * }  $attributes
     */
    public function addAuthorityReport(
        ProductIncident $incident,
        array $attributes,
        ?User $actor = null,
    ): IncidentReport {
        /** @var IncidentReport $report */
        $report = $incident->reports()->create([
            'authority' => $attributes['authority'],
            'submitted_at' => $attributes['submitted_at'],
            'submitted_by' => $actor?->id,
            'submission_channel' => IncidentReportChannel::from($attributes['submission_channel']),
            'submission_reference' => $attributes['submission_reference'] ?? null,
            'summary' => $attributes['summary'] ?? null,
            'notes' => $attributes['notes'] ?? null,
            'evidence_id' => $attributes['evidence_id'] ?? null,
        ]);

        $report->load(['submitter', 'evidence']);

        if ($actor instanceof User) {
            AuditLogger::logIncidentReportAdded($incident, $report, $actor);
        }

        return $report;
    }

    /**
     * @param  array{
     *     communicated_at: string|\DateTimeInterface,
     *     channel: string,
     *     customer_id?: int|null,
     *     audience?: string|null,
     *     subject: string,
     *     summary?: string|null,
     *     notes?: string|null,
     *     evidence_id?: int|null
     * }  $attributes
     */
    public function addCustomerCommunication(
        ProductIncident $incident,
        array $attributes,
        ?User $actor = null,
    ): IncidentCustomerCommunication {
        /** @var IncidentCustomerCommunication $communication */
        $communication = $incident->customerCommunications()->create([
            'communicated_at' => $attributes['communicated_at'],
            'recorded_by' => $actor?->id,
            'channel' => IncidentCommunicationChannel::from($attributes['channel']),
            'customer_id' => $attributes['customer_id'] ?? null,
            'audience' => $attributes['audience'] ?? null,
            'subject' => $attributes['subject'],
            'summary' => $attributes['summary'] ?? null,
            'notes' => $attributes['notes'] ?? null,
            'evidence_id' => $attributes['evidence_id'] ?? null,
        ]);

        $communication->load(['recorder', 'customer', 'evidence']);

        if ($actor instanceof User) {
            AuditLogger::logIncidentCustomerCommunicationAdded($incident, $communication, $actor);
        }

        return $communication;
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
            'product_id' => $incident->product_id,
            'product_name' => $incident->product?->name ?? '',
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

        if (!$incident->relationLoaded('customers')) {
            $incident->load('customers');
        }

        if (!$incident->relationLoaded('deployments')) {
            $incident->load('deployments');
        }

        if (!$incident->relationLoaded('evidence')) {
            $incident->load('evidence');
        }

        if (!$incident->relationLoaded('controls')) {
            $incident->load('controls');
        }

        if (!$incident->relationLoaded('closer')) {
            $incident->load('closer');
        }

        if (!$incident->relationLoaded('reports')) {
            $incident->load(['reports.submitter', 'reports.evidence']);
        } elseif (
            $incident->reports->isNotEmpty()
            && !$incident->reports->first()?->relationLoaded('submitter')
        ) {
            $incident->load(['reports.submitter', 'reports.evidence']);
        }

        return [
            'id' => $incident->id,
            'title' => $incident->title,
            'status' => $incident->status->value,
            'severity' => $incident->severity->value,
            'confidentiality_impact' => $incident->confidentiality_impact?->value,
            'integrity_impact' => $incident->integrity_impact?->value,
            'availability_impact' => $incident->availability_impact?->value,
            'attack_vector' => $incident->attack_vector?->value,
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
            'closed_at' => $incident->closed_at?->toIso8601String(),
            'closed_by' => $incident->closed_by,
            'closed_by_name' => $incident->closer?->name,
            'is_terminal' => $incident->isTerminal(),
            'notes' => $incident->notes,
            'version_ids' => $incident->versions->pluck('id')->all(),
            'customer_ids' => $incident->customers->pluck('id')->all(),
            'deployment_ids' => $incident->deployments->pluck('id')->all(),
            'evidence_ids' => $incident->evidence->pluck('id')->all(),
            'control_ids' => $incident->controls->pluck('id')->all(),
            'timeline_events' => $incident->timelineEvents
                ->map(fn(IncidentTimelineEvent $event) => $this->timelineEventPayload($event))
                ->values()
                ->all(),
            'authority_reports' => $incident->reports
                ->map(fn(IncidentReport $report) => $this->authorityReportPayload($report))
                ->values()
                ->all(),
            'customer_communications' => $incident->customerCommunications
                ->map(fn(IncidentCustomerCommunication $communication) => $this->customerCommunicationPayload($communication))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     authority: string,
     *     submitted_at: string,
     *     submission_channel: string,
     *     submission_reference: string|null,
     *     summary: string|null,
     *     notes: string|null,
     *     evidence_id: int|null,
     *     evidence_title: string|null,
     *     submitted_by: string|null,
     *     created_at: string|null
     * }
     */
    public function authorityReportPayload(IncidentReport $report): array
    {
        return [
            'id' => $report->id,
            'authority' => $report->authority,
            'submitted_at' => $report->submitted_at->toIso8601String(),
            'submission_channel' => $report->submission_channel->value,
            'submission_reference' => $report->submission_reference,
            'summary' => $report->summary,
            'notes' => $report->notes,
            'evidence_id' => $report->evidence_id,
            'evidence_title' => $report->evidence?->title,
            'submitted_by' => $report->submitter?->name,
            'created_at' => $report->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     communicated_at: string,
     *     channel: string,
     *     customer_id: int|null,
     *     customer_name: string|null,
     *     audience: string|null,
     *     subject: string,
     *     summary: string|null,
     *     notes: string|null,
     *     evidence_id: int|null,
     *     evidence_title: string|null,
     *     recorded_by: string|null,
     *     created_at: string|null
     * }
     */
    public function customerCommunicationPayload(IncidentCustomerCommunication $communication): array
    {
        return [
            'id' => $communication->id,
            'communicated_at' => $communication->communicated_at->toIso8601String(),
            'channel' => $communication->channel->value,
            'customer_id' => $communication->customer_id,
            'customer_name' => $communication->customer?->name,
            'audience' => $communication->audience,
            'subject' => $communication->subject,
            'summary' => $communication->summary,
            'notes' => $communication->notes,
            'evidence_id' => $communication->evidence_id,
            'evidence_title' => $communication->evidence?->title,
            'recorded_by' => $communication->recorder?->name,
            'created_at' => $communication->created_at?->toIso8601String(),
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

    /**
     * @param  list<int>  $customerIds
     */
    private function assertCustomersBelongToOrganization(int $organizationId, array $customerIds): void
    {
        if ($customerIds === []) {
            return;
        }

        $uniqueIds = array_values(array_unique(array_map('intval', $customerIds)));
        $count = Customer::query()
            ->where('organization_id', $organizationId)
            ->whereIn('id', $uniqueIds)
            ->count();

        if ($count !== count($uniqueIds)) {
            throw ValidationException::withMessages([
                'customer_ids' => ['One or more customers do not belong to this organization.'],
            ]);
        }
    }

    /**
     * @param  list<int>  $deploymentIds
     */
    private function assertDeploymentsBelongToProduct(Product $product, array $deploymentIds): void
    {
        if ($deploymentIds === []) {
            return;
        }

        $uniqueIds = array_values(array_unique(array_map('intval', $deploymentIds)));
        $count = ProductDeployment::query()
            ->where('product_id', $product->id)
            ->where('organization_id', $product->organization_id)
            ->whereIn('id', $uniqueIds)
            ->count();

        if ($count !== count($uniqueIds)) {
            throw ValidationException::withMessages([
                'deployment_ids' => ['One or more deployments do not belong to this product.'],
            ]);
        }
    }

    /**
     * @param  list<int>  $evidenceIds
     */
    private function assertEvidenceBelongToProduct(Product $product, array $evidenceIds): void
    {
        if ($evidenceIds === []) {
            return;
        }

        $uniqueIds = array_values(array_unique(array_map('intval', $evidenceIds)));
        $count = Evidence::query()
            ->where('product_id', $product->id)
            ->where('organization_id', $product->organization_id)
            ->whereIn('id', $uniqueIds)
            ->count();

        if ($count !== count($uniqueIds)) {
            throw ValidationException::withMessages([
                'evidence_ids' => ['One or more evidence records do not belong to this product.'],
            ]);
        }
    }

    /**
     * @param  list<int>  $controlIds
     */
    private function assertControlsBelongToOrganization(int $organizationId, array $controlIds): void
    {
        if ($controlIds === []) {
            return;
        }

        $uniqueIds = array_values(array_unique(array_map('intval', $controlIds)));
        $count = Control::query()
            ->where('organization_id', $organizationId)
            ->whereIn('id', $uniqueIds)
            ->count();

        if ($count !== count($uniqueIds)) {
            throw ValidationException::withMessages([
                'control_ids' => ['One or more controls do not belong to this organization.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function applyClosureTimestamps(
        ProductIncident $incident,
        array $attributes,
        User $actor,
    ): array {
        $nextStatus = $attributes['status'] ?? $incident->status;

        if (!$nextStatus instanceof IncidentStatus) {
            $nextStatus = IncidentStatus::from((string) $nextStatus);
        }

        $wasTerminal = $incident->status->isTerminal();
        $willBeTerminal = $nextStatus->isTerminal();

        if ($willBeTerminal && $incident->closed_at === null) {
            $attributes['closed_at'] = $attributes['closed_at'] ?? now();
            $attributes['closed_by'] = $attributes['closed_by'] ?? $actor->id;
        }

        if ($wasTerminal && !$willBeTerminal) {
            $attributes['closed_at'] = null;
            $attributes['closed_by'] = null;
        }

        return $attributes;
    }

    private function assertAssigneeBelongsToOrganization(int $organizationId, int $assigneeUserId): void
    {
        $belongs = Organization::query()
            ->whereKey($organizationId)
            ->whereHas(
                'users',
                fn($query) => $query->where('users.id', $assigneeUserId),
            )
            ->exists();

        if (!$belongs) {
            throw ValidationException::withMessages([
                'assignee_user_id' => [
                    Translations::get('products.incidents.close_assignee_invalid'),
                ],
            ]);
        }
    }
}
