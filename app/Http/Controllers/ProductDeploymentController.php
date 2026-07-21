<?php

namespace App\Http\Controllers;

use App\Enums\DeploymentEnvironment;
use App\Http\Requests\StoreProductDeploymentRequest;
use App\Http\Requests\UpdateProductDeploymentRequest;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductDeployment;
use App\Models\ProductVersion;
use App\Services\ProductDeploymentService;
use App\Support\Translations;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProductDeploymentController extends Controller
{
    public function __construct(
        private readonly ProductDeploymentService $deployments,
    ) {
    }

    public function index(Product $product): Response
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('view', [$product, $organization]);

        return Inertia::render('products/deployments/Index', [
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

        return Inertia::render('products/deployments/Create', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productSummary($product),
            'customers' => $this->customerOptions($organization),
            'versions' => $this->versionOptions($product),
            'options' => $this->enumOptions(),
        ]);
    }

    public function store(StoreProductDeploymentRequest $request, Product $product): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);

        $this->deployments->create(
            $product,
            $this->attributesFromRequest($request),
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.deployments.created'),
        ]);

        return redirect()->route('products.deployments.index', $product);
    }

    public function edit(Product $product, ProductDeployment $deployment): Response
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertDeploymentBelongsToProduct($product, $deployment);
        $this->authorize('view', [$product, $organization]);

        return Inertia::render('products/deployments/Edit', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productSummary($product),
            'deployment' => $this->deploymentPayload($deployment),
            'customers' => $this->customerOptions($organization),
            'versions' => $this->versionOptions($product),
            'options' => $this->enumOptions(),
            'canManage' => request()->user()->canManageProducts($organization),
        ]);
    }

    public function update(
        UpdateProductDeploymentRequest $request,
        Product $product,
        ProductDeployment $deployment,
    ): RedirectResponse {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertDeploymentBelongsToProduct($product, $deployment);

        $this->deployments->update(
            $deployment,
            $this->attributesFromRequest($request),
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.deployments.updated'),
        ]);

        return redirect()->route('products.deployments.index', $product);
    }

    public function destroy(Product $product, ProductDeployment $deployment): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertDeploymentBelongsToProduct($product, $deployment);
        $this->authorize('update', [$product, $organization]);

        $this->deployments->delete($deployment, request()->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.deployments.deleted'),
        ]);

        return redirect()->route('products.deployments.index', $product);
    }

    /**
     * @return array{
     *     customer_id: int,
     *     product_version_id: int|null,
     *     environment: DeploymentEnvironment,
     *     installation_date: string|null,
     *     internet_exposure: bool,
     *     update_channel: string|null,
     *     last_confirmed_at: string|null,
     *     custom_modifications: bool,
     *     end_of_support_exception: bool,
     *     notes: string|null
     * }
     */
    private function attributesFromRequest(
        StoreProductDeploymentRequest|UpdateProductDeploymentRequest $request,
    ): array {
        return [
            'customer_id' => $request->integer('customer_id'),
            'product_version_id' => $request->filled('product_version_id')
                ? $request->integer('product_version_id')
                : null,
            'environment' => DeploymentEnvironment::from($request->string('environment')->toString()),
            'installation_date' => $request->input('installation_date'),
            'internet_exposure' => $request->boolean('internet_exposure'),
            'update_channel' => $request->input('update_channel'),
            'last_confirmed_at' => $request->input('last_confirmed_at'),
            'custom_modifications' => $request->boolean('custom_modifications'),
            'end_of_support_exception' => $request->boolean('end_of_support_exception'),
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

    private function assertDeploymentBelongsToProduct(Product $product, ProductDeployment $deployment): void
    {
        if ($deployment->product_id !== $product->id) {
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
     * @return list<array{id: int, name: string, criticality: string, is_active: bool}>
     */
    private function customerOptions(Organization $organization): array
    {
        return Customer::query()
            ->where('organization_id', $organization->id)
            ->orderBy('name')
            ->get(['id', 'name', 'criticality', 'is_active'])
            ->map(fn(Customer $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'criticality' => $customer->criticality->value,
                'is_active' => $customer->is_active,
            ])
            ->all();
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
     * @return array{environments: list<string>}
     */
    private function enumOptions(): array
    {
        return [
            'environments' => array_column(DeploymentEnvironment::cases(), 'value'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function deploymentPayload(ProductDeployment $deployment): array
    {
        return [
            'id' => $deployment->id,
            'customer_id' => $deployment->customer_id,
            'product_version_id' => $deployment->product_version_id,
            'environment' => $deployment->environment->value,
            'installation_date' => $deployment->installation_date?->toDateString(),
            'internet_exposure' => $deployment->internet_exposure,
            'update_channel' => $deployment->update_channel,
            'last_confirmed_at' => $deployment->last_confirmed_at?->toDateString(),
            'custom_modifications' => $deployment->custom_modifications,
            'end_of_support_exception' => $deployment->end_of_support_exception,
            'notes' => $deployment->notes,
        ];
    }
}
