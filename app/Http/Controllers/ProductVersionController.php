<?php

namespace App\Http\Controllers;

use App\Enums\ProductVersionState;
use App\Enums\SupportStatus;
use App\Http\Requests\StoreProductVersionRequest;
use App\Http\Requests\UpdateProductVersionRequest;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVersion;
use App\Services\AiAssistantService;
use App\Services\MergedPrSummaryService;
use App\Support\AuditLogger;
use App\Support\Translations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductVersionController extends Controller
{
    public function __construct(
        private readonly MergedPrSummaryService $mergedPrSummaries,
        private readonly AiAssistantService $assistant,
    ) {
    }

    public function index(Product $product): Response
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('view', [$product, $organization]);

        return Inertia::render('products/versions/Index', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productSummary($product),
            'canManage' => request()->user()->canManageProducts($organization),
        ]);
    }

    public function create(Product $product): Response
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('update', [$product, $organization]);

        return Inertia::render('products/versions/Create', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productSummary($product),
            'previousVersions' => $this->previousVersionOptions($product),
            'options' => $this->enumOptions(),
        ]);
    }

    public function store(StoreProductVersionRequest $request, Product $product): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);

        $version = $product->versions()->create($this->validatedAttributes($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.versions.created'),
        ]);

        return redirect()->route('products.versions.show', [$product, $version]);
    }

    public function show(Product $product, ProductVersion $version): Response
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertVersionBelongsToProduct($product, $version);
        $this->authorize('view', [$product, $organization]);

        return Inertia::render('products/versions/Show', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productSummary($product),
            'version' => $this->versionPayload($version),
            'mergedPrSummary' => $this->mergedPrSummaries->summarize($product, $version),
            'mergedPrEvidence' => $this->mergedPrSummaries->latestSavedEvidenceSummary($version),
            'canManage' => request()->user()->canManageProducts($organization),
            'aiEnabled' => $this->assistant->isEnabled(),
        ]);
    }

    public function refreshMergedPrs(Product $product, ProductVersion $version): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertVersionBelongsToProduct($product, $version);
        $this->authorize('update', [$product, $organization]);

        $summary = $this->mergedPrSummaries->summarize($product, $version, forceRefresh: true);

        AuditLogger::logMergedPrSummaryRefreshed(
            $product,
            $version,
            request()->user(),
            $summary,
        );

        Inertia::flash('toast', [
            'type' => $summary['available'] ? 'success' : 'error',
            'message' => $summary['available']
                ? Translations::get('products.versions.merged_prs.refreshed')
                : ($summary['error']
                    ?? Translations::get('products.versions.merged_prs.reasons.' . ($summary['reason'] ?? 'fetch_failed'))),
        ]);

        return redirect()->route('products.versions.show', [$product, $version]);
    }

    public function saveMergedPrsAsEvidence(Product $product, ProductVersion $version): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertVersionBelongsToProduct($product, $version);
        $this->authorize('update', [$product, $organization]);

        $this->mergedPrSummaries->saveAsEvidence($product, $version, request()->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.versions.merged_prs.saved_as_evidence'),
        ]);

        return redirect()->route('products.versions.show', [$product, $version]);
    }

    public function suggestMergedPrNarrative(
        Request $request,
        Product $product,
        ProductVersion $version,
    ): JsonResponse {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertVersionBelongsToProduct($product, $version);
        $this->authorize('update', [$product, $organization]);

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $result = $this->assistant->suggestMergedPrNarrativeDraft(
            $product,
            $version,
            $request->user(),
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

    public function edit(Product $product, ProductVersion $version): Response
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertVersionBelongsToProduct($product, $version);
        $this->authorize('update', [$product, $organization]);

        return Inertia::render('products/versions/Edit', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productSummary($product),
            'version' => $this->versionPayload($version),
            'previousVersions' => $this->previousVersionOptions($product, $version->id),
            'options' => $this->enumOptions(),
        ]);
    }

    public function update(
        UpdateProductVersionRequest $request,
        Product $product,
        ProductVersion $version,
    ): RedirectResponse {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertVersionBelongsToProduct($product, $version);

        $version->update($this->validatedAttributes($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.versions.updated'),
        ]);

        return redirect()->route('products.versions.show', [$product, $version]);
    }

    public function destroy(Product $product, ProductVersion $version): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertVersionBelongsToProduct($product, $version);
        $this->authorize('update', [$product, $organization]);

        $version->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.versions.deleted'),
        ]);

        return redirect()->route('products.versions.index', $product);
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

    private function assertVersionBelongsToProduct(Product $product, ProductVersion $version): void
    {
        if ($version->product_id !== $product->id) {
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
    private function productSummary(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
        ];
    }

    /**
     * @return list<array{id: int, version_number: string}>
     */
    private function previousVersionOptions(Product $product, ?int $excludeId = null): array
    {
        return $product->versions()
            ->when($excludeId !== null, fn($query) => $query->whereKeyNot($excludeId))
            ->orderByDesc('version_number')
            ->get(['id', 'version_number'])
            ->map(fn(ProductVersion $version) => [
                'id' => $version->id,
                'version_number' => $version->version_number,
            ])
            ->all();
    }

    /**
     * @return array{states: list<string>, support_statuses: list<string>}
     */
    private function enumOptions(): array
    {
        return [
            'states' => array_column(ProductVersionState::cases(), 'value'),
            'support_statuses' => array_column(SupportStatus::cases(), 'value'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedAttributes(StoreProductVersionRequest $request): array
    {
        return [
            'version_number' => $request->string('version_number')->toString(),
            'release_date' => $request->input('release_date') ?: null,
            'state' => $request->string('state')->toString(),
            'support_status' => $request->string('support_status')->toString(),
            'security_support_deadline' => $request->input('security_support_deadline') ?: null,
            'git_ref' => $request->input('git_ref'),
            'build_identifier' => $request->input('build_identifier'),
            'artifact_hash' => $request->input('artifact_hash'),
            'changelog' => $request->input('changelog'),
            'previous_version_id' => $request->input('previous_version_id') ?: null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function versionPayload(ProductVersion $version): array
    {
        return [
            'id' => $version->id,
            'version_number' => $version->version_number,
            'release_date' => $version->release_date?->toDateString(),
            'state' => $version->state->value,
            'support_status' => $version->support_status->value,
            'security_support_deadline' => $version->security_support_deadline?->toDateString(),
            'git_ref' => $version->git_ref,
            'build_identifier' => $version->build_identifier,
            'artifact_hash' => $version->artifact_hash,
            'changelog' => $version->changelog,
            'previous_version_id' => $version->previous_version_id,
        ];
    }
}
