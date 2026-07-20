<?php

namespace App\Http\Controllers;

use App\Enums\EvidenceConfidentiality;
use App\Enums\EvidenceFreshnessStatus;
use App\Enums\EvidenceType;
use App\Http\Requests\StoreEvidenceRequest;
use App\Http\Requests\UpdateEvidenceRequest;
use App\Models\Control;
use App\Models\Evidence;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductRisk;
use App\Models\ProductVulnerability;
use App\Models\Requirement;
use App\Services\EvidenceService;
use App\Support\Translations;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EvidenceController extends Controller
{
    public function __construct(
        private readonly EvidenceService $evidence,
    ) {
    }

    public function index(Product $product): Response
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('viewAny', [Evidence::class, $organization]);
        $this->authorize('view', [$product, $organization]);

        return Inertia::render('products/evidence/Index', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productPayload($product),
            'canManage' => request()->user()->canManageEvidence($organization),
            'options' => $this->enumOptions(),
        ]);
    }

    public function create(Product $product): Response
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('create', [Evidence::class, $organization]);

        return Inertia::render('products/evidence/Create', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productPayload($product),
            'members' => $this->memberOptions($organization),
            'versions' => $this->versionOptions($product),
            'requirements' => $this->requirementOptions(),
            'controls' => $this->controlOptions($organization),
            'risks' => $this->riskOptions($product),
            'vulnerabilities' => $this->vulnerabilityOptions($product),
            'evidenceOptions' => $this->evidenceOptions($product),
            'options' => $this->enumOptions(),
        ]);
    }

    public function store(StoreEvidenceRequest $request, Product $product): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);

        $item = $this->evidence->create(
            $product,
            $this->validatedAttributes($request),
            $request->file('file'),
            $request->user(),
            $request->input('requirement_ids', []),
            $request->input('control_ids', []),
            $request->input('risk_ids', []),
            $request->input('vulnerability_ids', []),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.evidence.created'),
        ]);

        return redirect()->route('products.evidence.edit', [$product, $item]);
    }

    public function edit(Product $product, Evidence $evidence): Response
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertEvidenceBelongsToProduct($evidence, $product);
        $this->authorize('view', [$evidence, $organization]);

        $evidence->load([
            'owner',
            'productVersion',
            'requirements',
            'controls',
            'risks',
            'vulnerabilities',
        ]);

        return Inertia::render('products/evidence/Edit', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productPayload($product),
            'evidence' => $this->evidence->detailPayload($evidence),
            'members' => $this->memberOptions($organization),
            'versions' => $this->versionOptions($product),
            'requirements' => $this->requirementOptions(),
            'controls' => $this->controlOptions($organization),
            'risks' => $this->riskOptions($product),
            'vulnerabilities' => $this->vulnerabilityOptions($product),
            'evidenceOptions' => $this->evidenceOptions($product, $evidence->id),
            'options' => $this->enumOptions(),
            'canManage' => request()->user()->canManageEvidence($organization),
        ]);
    }

    public function update(
        UpdateEvidenceRequest $request,
        Product $product,
        Evidence $evidence,
    ): RedirectResponse {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertEvidenceBelongsToProduct($evidence, $product);

        $this->evidence->update(
            $evidence,
            $this->validatedAttributes($request),
            $request->file('file'),
            $request->input('requirement_ids', []),
            $request->input('control_ids', []),
            $request->input('risk_ids', []),
            $request->input('vulnerability_ids', []),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.evidence.updated'),
        ]);

        return redirect()->route('products.evidence.edit', [$product, $evidence]);
    }

    public function destroy(Product $product, Evidence $evidence): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertEvidenceBelongsToProduct($evidence, $product);
        $this->authorize('delete', [$evidence, $organization]);

        $this->evidence->delete($evidence);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.evidence.deleted'),
        ]);

        return redirect()->route('products.evidence.index', $product);
    }

    public function download(Product $product, Evidence $evidence): StreamedResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertEvidenceBelongsToProduct($evidence, $product);
        $this->authorize('download', [$evidence, $organization]);

        return $this->evidence->download($evidence);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedAttributes(
        StoreEvidenceRequest|UpdateEvidenceRequest $request,
    ): array {
        return [
            'title' => $request->string('title')->toString(),
            'type' => EvidenceType::from($request->string('type')->toString()),
            'source' => $request->input('source'),
            'owner_user_id' => $request->input('owner_user_id')
                ? (int) $request->input('owner_user_id')
                : null,
            'product_version_id' => $request->input('product_version_id')
                ? (int) $request->input('product_version_id')
                : null,
            'confidentiality' => EvidenceConfidentiality::from(
                $request->string('confidentiality')->toString(),
            ),
            'collected_at' => $request->input('collected_at'),
            'valid_until' => $request->input('valid_until'),
            'review_due_at' => $request->input('review_due_at'),
            'freshness_status' => EvidenceFreshnessStatus::from(
                $request->string('freshness_status')->toString(),
            ),
            'supersedes_evidence_id' => $request->input('supersedes_evidence_id')
                ? (int) $request->input('supersedes_evidence_id')
                : null,
            'notes' => $request->input('notes'),
            'review_notes' => $request->input('review_notes'),
            'reviewer_user_id' => $request->input('reviewer_user_id')
                ? (int) $request->input('reviewer_user_id')
                : null,
            'reviewed_at' => $request->input('reviewed_at'),
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

    private function assertEvidenceBelongsToProduct(Evidence $evidence, Product $product): void
    {
        if ($evidence->product_id !== $product->id) {
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
     * @return list<array{id: int, code: string, article_ref: string|null, requirement_text: string|null}>
     */
    private function requirementOptions(): array
    {
        return Requirement::query()
            ->where('is_active', true)
            ->with('currentVersion')
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get()
            ->map(fn(Requirement $requirement) => [
                'id' => $requirement->id,
                'code' => $requirement->code,
                'article_ref' => $requirement->article_ref,
                'requirement_text' => $requirement->currentVersion?->localized('requirement_text'),
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, code: string, name: string, description: string|null}>
     */
    private function controlOptions(Organization $organization): array
    {
        return Control::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'description'])
            ->map(fn(Control $control) => [
                'id' => $control->id,
                'code' => $control->code,
                'name' => $control->name,
                'description' => $control->description,
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, title: string, asset: string|null, threat: string|null, weakness: string|null, attack_scenario: string|null}>
     */
    private function riskOptions(Product $product): array
    {
        return ProductRisk::query()
            ->where('product_id', $product->id)
            ->orderBy('title')
            ->get(['id', 'title', 'asset', 'threat', 'weakness', 'attack_scenario'])
            ->map(fn(ProductRisk $risk) => [
                'id' => $risk->id,
                'title' => $risk->title,
                'asset' => $risk->asset,
                'threat' => $risk->threat,
                'weakness' => $risk->weakness,
                'attack_scenario' => $risk->attack_scenario,
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, title: string, cve_id: string|null, summary: string|null, corrective_action: string|null}>
     */
    private function vulnerabilityOptions(Product $product): array
    {
        return ProductVulnerability::query()
            ->where('product_id', $product->id)
            ->orderBy('title')
            ->get(['id', 'title', 'cve_id', 'summary', 'corrective_action'])
            ->map(fn(ProductVulnerability $vulnerability) => [
                'id' => $vulnerability->id,
                'title' => $vulnerability->title,
                'cve_id' => $vulnerability->cve_id,
                'summary' => $vulnerability->summary,
                'corrective_action' => $vulnerability->corrective_action,
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, title: string}>
     */
    private function evidenceOptions(Product $product, ?int $exceptId = null): array
    {
        $query = Evidence::query()
            ->where('product_id', $product->id)
            ->orderBy('title');

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query
            ->get(['id', 'title'])
            ->map(fn(Evidence $item) => [
                'id' => $item->id,
                'title' => $item->title,
            ])
            ->all();
    }

    /**
     * @return array{
     *     types: list<string>,
     *     confidentialities: list<string>,
     *     freshness_statuses: list<string>
     * }
     */
    private function enumOptions(): array
    {
        return [
            'types' => array_column(EvidenceType::cases(), 'value'),
            'confidentialities' => array_column(EvidenceConfidentiality::cases(), 'value'),
            'freshness_statuses' => array_column(EvidenceFreshnessStatus::cases(), 'value'),
        ];
    }
}
