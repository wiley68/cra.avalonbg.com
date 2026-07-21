<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePatchCampaignRequest;
use App\Http\Requests\UpdatePatchCampaignRequest;
use App\Enums\PatchCampaignStatus;
use App\Models\Organization;
use App\Models\PatchCampaign;
use App\Models\Product;
use App\Models\ProductVersion;
use App\Models\ProductVulnerability;
use App\Services\PatchCampaignService;
use App\Support\Translations;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PatchCampaignController extends Controller
{
    public function __construct(
        private readonly PatchCampaignService $campaigns,
    ) {
    }

    public function index(Product $product): Response
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('view', [$product, $organization]);

        return Inertia::render('products/campaigns/Index', [
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

        return Inertia::render('products/campaigns/Create', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productSummary($product),
            'versions' => $this->versionOptions($product),
            'vulnerabilities' => $this->vulnerabilityOptions($product),
        ]);
    }

    public function store(StorePatchCampaignRequest $request, Product $product): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);

        $campaign = $this->campaigns->create(
            $product,
            $this->attributesFromRequest($request),
            $request->user(),
        );

        $activated = $request->boolean('activate');

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get(
                $activated
                ? 'products.campaigns.activated'
                : 'products.campaigns.created',
            ),
        ]);

        return redirect()->route('products.campaigns.show', [$product, $campaign]);
    }

    public function show(Product $product, PatchCampaign $campaign): Response
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertCampaignBelongsToProduct($product, $campaign);
        $this->authorize('view', [$product, $organization]);

        return Inertia::render('products/campaigns/Show', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productSummary($product),
            'campaign' => $this->campaigns->showPayload($campaign),
            'canManage' => request()->user()->canManageProducts($organization),
        ]);
    }

    public function edit(Product $product, PatchCampaign $campaign): Response
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertCampaignBelongsToProduct($product, $campaign);
        $this->authorize('update', [$product, $organization]);

        if ($campaign->status !== PatchCampaignStatus::Draft) {
            abort(404);
        }

        return Inertia::render('products/campaigns/Edit', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productSummary($product),
            'campaign' => [
                'id' => $campaign->id,
                'title' => $campaign->title,
                'target_version_id' => $campaign->target_version_id,
                'product_vulnerability_id' => $campaign->product_vulnerability_id,
                'notes' => $campaign->notes,
                'status' => $campaign->status->value,
            ],
            'versions' => $this->versionOptions($product),
            'vulnerabilities' => $this->vulnerabilityOptions($product),
        ]);
    }

    public function update(
        UpdatePatchCampaignRequest $request,
        Product $product,
        PatchCampaign $campaign,
    ): RedirectResponse {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertCampaignBelongsToProduct($product, $campaign);

        $this->campaigns->update(
            $campaign,
            $this->attributesFromRequest($request),
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.campaigns.updated'),
        ]);

        return redirect()->route('products.campaigns.show', [$product, $campaign]);
    }

    public function activate(Product $product, PatchCampaign $campaign): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertCampaignBelongsToProduct($product, $campaign);
        $this->authorize('update', [$product, $organization]);

        $this->campaigns->activate($campaign, request()->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.campaigns.activated'),
        ]);

        return redirect()->route('products.campaigns.show', [$product, $campaign]);
    }

    public function destroy(Product $product, PatchCampaign $campaign): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertCampaignBelongsToProduct($product, $campaign);
        $this->authorize('update', [$product, $organization]);

        $this->campaigns->delete($campaign, request()->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.campaigns.deleted'),
        ]);

        return redirect()->route('products.campaigns.index', $product);
    }

    /**
     * @return array{
     *     title: string,
     *     target_version_id: int,
     *     product_vulnerability_id: int|null,
     *     notes: string|null,
     *     activate?: bool
     * }
     */
    private function attributesFromRequest(
        StorePatchCampaignRequest|UpdatePatchCampaignRequest $request,
    ): array {
        $attributes = [
            'title' => $request->string('title')->toString(),
            'target_version_id' => $request->integer('target_version_id'),
            'product_vulnerability_id' => $request->filled('product_vulnerability_id')
                ? $request->integer('product_vulnerability_id')
                : null,
            'notes' => $request->input('notes'),
        ];

        if ($request instanceof StorePatchCampaignRequest) {
            $attributes['activate'] = $request->boolean('activate');
        }

        return $attributes;
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

    private function assertCampaignBelongsToProduct(Product $product, PatchCampaign $campaign): void
    {
        if ($campaign->product_id !== $product->id) {
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
    private function versionOptions(Product $product): array
    {
        return $product->versions()
            ->orderByDesc('version_number')
            ->get(['id', 'version_number'])
            ->map(fn(ProductVersion $version) => [
                'id' => (int) $version->id,
                'version_number' => $version->version_number,
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, title: string, cve_id: string|null}>
     */
    private function vulnerabilityOptions(Product $product): array
    {
        return ProductVulnerability::query()
            ->where('product_id', $product->id)
            ->orderBy('title')
            ->get(['id', 'title', 'cve_id'])
            ->map(fn(ProductVulnerability $vulnerability) => [
                'id' => (int) $vulnerability->id,
                'title' => $vulnerability->title,
                'cve_id' => $vulnerability->cve_id,
            ])
            ->all();
    }
}
