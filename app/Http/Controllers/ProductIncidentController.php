<?php

namespace App\Http\Controllers;

use App\Enums\IncidentAttackVector;
use App\Enums\IncidentCiaImpact;
use App\Enums\IncidentCommunicationChannel;
use App\Enums\IncidentReportChannel;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Http\Requests\CloseProductIncidentRequest;
use App\Http\Requests\CreateIncidentVulnerabilityRequest;
use App\Http\Requests\LinkIncidentVulnerabilityRequest;
use App\Http\Requests\StoreIncidentCustomerCommunicationRequest;
use App\Http\Requests\StoreIncidentReportRequest;
use App\Http\Requests\StoreIncidentTimelineEventRequest;
use App\Http\Requests\StoreProductIncidentRequest;
use App\Http\Requests\UpdateProductIncidentRequest;
use App\Models\Control;
use App\Models\Customer;
use App\Models\Evidence;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductControl;
use App\Models\ProductDeployment;
use App\Models\ProductIncident;
use App\Models\ProductVulnerability;
use App\Services\ProductIncidentExportService;
use App\Services\ProductIncidentService;
use App\Services\AiAssistantService;
use App\Support\Translations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProductIncidentController extends Controller
{
    public function __construct(
        private readonly ProductIncidentService $incidents,
        private readonly ProductIncidentExportService $exports,
        private readonly AiAssistantService $assistant,
    ) {
    }

    public function index(Product $product): InertiaResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('viewAny', [ProductIncident::class, $organization]);
        $this->authorize('view', [$product, $organization]);

        return Inertia::render('products/incidents/Index', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productPayload($product),
            'canManage' => request()->user()->canManageIncidents($organization),
            'options' => $this->enumOptions(),
        ]);
    }

    public function create(Product $product): InertiaResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('create', [ProductIncident::class, $organization]);

        return Inertia::render('products/incidents/Create', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productPayload($product),
            'members' => $this->memberOptions($organization),
            'versions' => $this->versionOptions($product),
            'customers' => $this->customerOptions($organization),
            'deployments' => $this->deploymentOptions($product),
            'evidence' => $this->evidenceOptions($product),
            'controls' => $this->controlOptions($product, $organization),
            'options' => $this->enumOptions(),
        ]);
    }

    public function store(StoreProductIncidentRequest $request, Product $product): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);

        $incident = $this->incidents->create(
            $product,
            $this->validatedAttributes($request),
            array_map('intval', $request->input('version_ids', [])),
            array_map('intval', $request->input('customer_ids', [])),
            array_map('intval', $request->input('deployment_ids', [])),
            array_map('intval', $request->input('evidence_ids', [])),
            array_map('intval', $request->input('control_ids', [])),
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.incidents.created'),
        ]);

        return redirect()->route('products.incidents.edit', [$product, $incident]);
    }

    public function edit(Product $product, ProductIncident $incident): InertiaResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertIncidentBelongsToProduct($incident, $product);
        $this->authorize('view', [$incident, $organization]);

        $incident->load([
            'owner',
            'versions',
            'customers',
            'deployments',
            'evidence',
            'controls',
            'closer',
            'timelineEvents.creator',
            'reports.submitter',
            'reports.evidence',
            'customerCommunications.recorder',
            'customerCommunications.customer',
            'customerCommunications.evidence',
            'vulnerability',
        ]);

        return Inertia::render('products/incidents/Edit', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productPayload($product),
            'incident' => $this->incidents->detailPayload($incident),
            'members' => $this->memberOptions($organization),
            'versions' => $this->versionOptions($product),
            'customers' => $this->customerOptions($organization),
            'deployments' => $this->deploymentOptions($product),
            'evidence' => $this->evidenceOptions($product),
            'controls' => $this->controlOptions($product, $organization),
            'vulnerabilities' => $this->incidents->linkableVulnerabilityOptions($product),
            'options' => $this->enumOptions(),
            'canManage' => request()->user()->canManageIncidents($organization),
            'aiEnabled' => $this->assistant->isEnabled(),
        ]);
    }

    public function suggestAiDraft(
        Request $request,
        Product $product,
        ProductIncident $incident,
    ): JsonResponse {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertIncidentBelongsToProduct($incident, $product);
        $this->authorize('update', [$incident, $organization]);

        $validated = $request->validate([
            'current_summary' => ['nullable', 'string', 'max:50000'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $result = $this->assistant->suggestIncidentSummaryDraft(
            $product,
            $incident,
            $request->user(),
            $validated['current_summary'] ?? null,
            $validated['note'] ?? null,
            $organization->resolvedLocale(),
        );

        return response()->json([
            'summary_markdown' => $result['draft']['summary_markdown'],
            'human_review_required' => true,
            'disclaimer' => $result['draft']['disclaimer'],
            'provider' => $result['provider'],
            'model' => $result['model'],
        ]);
    }

    public function storeTimeline(
        StoreIncidentTimelineEventRequest $request,
        Product $product,
        ProductIncident $incident,
    ): RedirectResponse {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertIncidentBelongsToProduct($incident, $product);

        $this->incidents->addTimelineEvent(
            $incident,
            [
                'occurred_at' => $request->input('occurred_at'),
                'label' => $request->string('label')->toString(),
                'notes' => $request->input('notes'),
            ],
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.incidents.timeline_added'),
        ]);

        return redirect()->route('products.incidents.edit', [$product, $incident]);
    }

    public function storeReport(
        StoreIncidentReportRequest $request,
        Product $product,
        ProductIncident $incident,
    ): RedirectResponse {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertIncidentBelongsToProduct($incident, $product);

        $this->incidents->addAuthorityReport(
            $incident,
            [
                'authority' => $request->string('authority')->toString(),
                'submitted_at' => $request->input('submitted_at'),
                'submission_channel' => $request->string('submission_channel')->toString(),
                'submission_reference' => $request->input('submission_reference'),
                'summary' => $request->input('summary'),
                'notes' => $request->input('notes'),
                'evidence_id' => $request->filled('evidence_id')
                    ? (int) $request->input('evidence_id')
                    : null,
            ],
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.incidents.report_added'),
        ]);

        return redirect()->route('products.incidents.edit', [$product, $incident]);
    }

    public function storeCustomerCommunication(
        StoreIncidentCustomerCommunicationRequest $request,
        Product $product,
        ProductIncident $incident,
    ): RedirectResponse {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertIncidentBelongsToProduct($incident, $product);

        $this->incidents->addCustomerCommunication(
            $incident,
            [
                'communicated_at' => $request->input('communicated_at'),
                'channel' => $request->string('channel')->toString(),
                'customer_id' => $request->filled('customer_id')
                    ? (int) $request->input('customer_id')
                    : null,
                'audience' => $request->input('audience'),
                'subject' => $request->string('subject')->toString(),
                'summary' => $request->input('summary'),
                'notes' => $request->input('notes'),
                'evidence_id' => $request->filled('evidence_id')
                    ? (int) $request->input('evidence_id')
                    : null,
            ],
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.incidents.communication_added'),
        ]);

        return redirect()->route('products.incidents.edit', [$product, $incident]);
    }

    public function linkVulnerability(
        LinkIncidentVulnerabilityRequest $request,
        Product $product,
        ProductIncident $incident,
    ): RedirectResponse {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertIncidentBelongsToProduct($incident, $product);

        $vulnerability = ProductVulnerability::query()->findOrFail(
            (int) $request->input('product_vulnerability_id'),
        );

        $this->incidents->linkVulnerability($incident, $vulnerability);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.incidents.vulnerability_linked'),
        ]);

        return redirect()->route('products.incidents.edit', [$product, $incident]);
    }

    public function unlinkVulnerability(
        Product $product,
        ProductIncident $incident,
    ): RedirectResponse {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertIncidentBelongsToProduct($incident, $product);
        $this->authorize('update', [$incident, $organization]);

        $this->incidents->unlinkVulnerability($incident);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.incidents.vulnerability_unlinked'),
        ]);

        return redirect()->route('products.incidents.edit', [$product, $incident]);
    }

    public function createVulnerability(
        CreateIncidentVulnerabilityRequest $request,
        Product $product,
        ProductIncident $incident,
    ): RedirectResponse {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertIncidentBelongsToProduct($incident, $product);

        $vulnerability = $this->incidents->createVulnerabilityFromIncident($incident);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.incidents.vulnerability_created'),
        ]);

        return redirect()->route('products.vulnerabilities.edit', [$product, $vulnerability]);
    }

    public function close(
        CloseProductIncidentRequest $request,
        Product $product,
        ProductIncident $incident,
    ): RedirectResponse {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertIncidentBelongsToProduct($incident, $product);

        $this->incidents->close(
            $incident,
            $request->user(),
            $request->boolean('create_approval_task'),
            $request->filled('assignee_user_id')
            ? (int) $request->input('assignee_user_id')
            : null,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.incidents.closed'),
        ]);

        return redirect()->route('products.incidents.edit', [$product, $incident]);
    }

    public function export(
        Product $product,
        ProductIncident $incident,
        string $format,
    ): Response {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertIncidentBelongsToProduct($incident, $product);
        $this->authorize('view', [$incident, $organization]);

        return $this->exports->export(
            $incident,
            $product,
            $organization,
            $format,
            request()->user(),
        );
    }

    public function update(
        UpdateProductIncidentRequest $request,
        Product $product,
        ProductIncident $incident,
    ): RedirectResponse {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertIncidentBelongsToProduct($incident, $product);

        $this->incidents->update(
            $incident,
            $this->validatedAttributes($request),
            array_map('intval', $request->input('version_ids', [])),
            array_map('intval', $request->input('customer_ids', [])),
            array_map('intval', $request->input('deployment_ids', [])),
            array_map('intval', $request->input('evidence_ids', [])),
            array_map('intval', $request->input('control_ids', [])),
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.incidents.updated'),
        ]);

        return redirect()->route('products.incidents.edit', [$product, $incident]);
    }

    public function destroy(Product $product, ProductIncident $incident): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertIncidentBelongsToProduct($incident, $product);
        $this->authorize('delete', [$incident, $organization]);

        $this->incidents->delete($incident, request()->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.incidents.deleted'),
        ]);

        return redirect()->route('products.incidents.index', $product);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedAttributes(
        StoreProductIncidentRequest|UpdateProductIncidentRequest $request,
    ): array {
        return [
            'title' => $request->string('title')->toString(),
            'status' => IncidentStatus::from($request->string('status')->toString()),
            'severity' => IncidentSeverity::from($request->string('severity')->toString()),
            'confidentiality_impact' => $request->filled('confidentiality_impact')
                ? IncidentCiaImpact::from($request->string('confidentiality_impact')->toString())
                : null,
            'integrity_impact' => $request->filled('integrity_impact')
                ? IncidentCiaImpact::from($request->string('integrity_impact')->toString())
                : null,
            'availability_impact' => $request->filled('availability_impact')
                ? IncidentCiaImpact::from($request->string('availability_impact')->toString())
                : null,
            'attack_vector' => $request->filled('attack_vector')
                ? IncidentAttackVector::from($request->string('attack_vector')->toString())
                : null,
            'summary' => $request->input('summary'),
            'root_cause' => $request->input('root_cause'),
            'corrective_measures' => $request->input('corrective_measures'),
            'lessons_learned' => $request->input('lessons_learned'),
            'owner_user_id' => $request->input('owner_user_id')
                ? (int) $request->input('owner_user_id')
                : null,
            'actual_started_at' => $request->input('actual_started_at') ?: null,
            'detected_at' => $request->input('detected_at') ?: null,
            'awareness_at' => $request->input('awareness_at') ?: null,
            'classified_at' => $request->input('classified_at') ?: null,
            'notes' => $request->input('notes'),
        ];
    }

    private function currentOrganization(): Organization
    {
        $organization = request()->user()?->currentOrganization();

        if ($organization === null) {
            abort(403, 'No organization membership.');
        }

        return $organization;
    }

    private function assertProductInOrganization(Product $product, Organization $organization): void
    {
        if ($product->organization_id !== $organization->id) {
            abort(404);
        }
    }

    private function assertIncidentBelongsToProduct(ProductIncident $incident, Product $product): void
    {
        if ($incident->product_id !== $product->id) {
            abort(404);
        }
    }

    /**
     * @return array{id: int, name: string, slug: string}
     */
    private function organizationPayload(Organization $organization): array
    {
        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'slug' => $organization->slug,
        ];
    }

    /**
     * @return array{id: int, name: string, slug: string}
     */
    private function productPayload(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
        ];
    }

    /**
     * @return list<array{id: int, name: string, email: string}>
     */
    private function memberOptions(Organization $organization): array
    {
        return $organization->users()
            ->orderBy('name')
            ->get(['users.id', 'users.name', 'users.email'])
            ->map(fn($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, version_number: string}>
     */
    private function versionOptions(Product $product): array
    {
        return $product->versions()
            ->orderByDesc('id')
            ->get(['id', 'version_number'])
            ->map(fn($version) => [
                'id' => $version->id,
                'version_number' => $version->version_number,
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, is_active: bool}>
     */
    private function customerOptions(Organization $organization): array
    {
        return Customer::query()
            ->where('organization_id', $organization->id)
            ->orderBy('name')
            ->get(['id', 'name', 'is_active'])
            ->map(fn(Customer $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'is_active' => $customer->is_active,
            ])
            ->all();
    }

    /**
     * @return list<array{
     *     id: int,
     *     customer_id: int,
     *     customer_name: string,
     *     environment: string,
     *     product_version_number: string|null
     * }>
     */
    private function deploymentOptions(Product $product): array
    {
        return ProductDeployment::query()
            ->where('product_id', $product->id)
            ->with(['customer:id,name', 'productVersion:id,version_number'])
            ->orderByDesc('id')
            ->get()
            ->map(fn(ProductDeployment $deployment) => [
                'id' => $deployment->id,
                'customer_id' => $deployment->customer_id,
                'customer_name' => $deployment->customer?->name ?? ('#' . $deployment->customer_id),
                'environment' => $deployment->environment->value,
                'product_version_number' => $deployment->productVersion?->version_number,
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, title: string}>
     */
    private function evidenceOptions(Product $product): array
    {
        return Evidence::query()
            ->where('product_id', $product->id)
            ->where('organization_id', $product->organization_id)
            ->orderBy('title')
            ->get(['id', 'title'])
            ->map(fn(Evidence $item) => [
                'id' => $item->id,
                'title' => $item->title,
            ])
            ->all();
    }

    /**
     * Prefer controls already assigned to the product; fall back to all active org controls.
     *
     * @return list<array{id: int, code: string, name: string, assigned: bool}>
     */
    private function controlOptions(Product $product, Organization $organization): array
    {
        $assignedIds = ProductControl::query()
            ->where('product_id', $product->id)
            ->pluck('control_id')
            ->all();

        return Control::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn(Control $control) => [
                'id' => $control->id,
                'code' => $control->code,
                'name' => $control->name,
                'assigned' => in_array($control->id, $assignedIds, true),
            ])
            ->all();
    }

    /**
     * @return array{
     *     statuses: list<string>,
     *     severities: list<string>,
     *     cia_impacts: list<string>,
     *     attack_vectors: list<string>,
     *     report_channels: list<string>,
     *     communication_channels: list<string>
     * }
     */
    private function enumOptions(): array
    {
        return [
            'statuses' => array_column(IncidentStatus::cases(), 'value'),
            'severities' => array_column(IncidentSeverity::cases(), 'value'),
            'cia_impacts' => array_column(IncidentCiaImpact::cases(), 'value'),
            'attack_vectors' => array_column(IncidentAttackVector::cases(), 'value'),
            'report_channels' => array_column(IncidentReportChannel::cases(), 'value'),
            'communication_channels' => array_column(IncidentCommunicationChannel::cases(), 'value'),
        ];
    }
}
