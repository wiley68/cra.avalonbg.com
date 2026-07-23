<?php

namespace App\Http\Controllers;

use App\Enums\IncidentReportChannel;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Http\Requests\CloseProductIncidentRequest;
use App\Http\Requests\CreateIncidentVulnerabilityRequest;
use App\Http\Requests\LinkIncidentVulnerabilityRequest;
use App\Http\Requests\StoreIncidentReportRequest;
use App\Http\Requests\StoreIncidentTimelineEventRequest;
use App\Http\Requests\StoreProductIncidentRequest;
use App\Http\Requests\UpdateProductIncidentRequest;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductDeployment;
use App\Models\ProductIncident;
use App\Models\ProductVulnerability;
use App\Services\ProductIncidentExportService;
use App\Services\ProductIncidentService;
use App\Support\Translations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProductIncidentController extends Controller
{
    public function __construct(
        private readonly ProductIncidentService $incidents,
        private readonly ProductIncidentExportService $exports,
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
            'closer',
            'timelineEvents.creator',
            'reports.submitter',
            'reports.evidence',
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
            'vulnerabilities' => $this->incidents->linkableVulnerabilityOptions($product),
            'options' => $this->enumOptions(),
            'canManage' => request()->user()->canManageIncidents($organization),
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
     * @return array{statuses: list<string>, severities: list<string>, report_channels: list<string>}
     */
    private function enumOptions(): array
    {
        return [
            'statuses' => array_column(IncidentStatus::cases(), 'value'),
            'severities' => array_column(IncidentSeverity::cases(), 'value'),
            'report_channels' => array_column(IncidentReportChannel::cases(), 'value'),
        ];
    }
}
