<?php

namespace App\Http\Controllers;

use App\Enums\ComponentSupportStatus;
use App\Enums\PackageEcosystem;
use App\Enums\SbomFormat;
use App\Http\Requests\ImportSbomRequest;
use App\Http\Requests\StoreProductComponentRequest;
use App\Http\Requests\UpdateProductComponentRequest;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductComponent;
use App\Models\ProductVersion;
use App\Services\ComponentService;
use App\Services\SbomImportService;
use App\Support\Translations;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProductComponentController extends Controller
{
    public function __construct(
        private readonly ComponentService $components,
        private readonly SbomImportService $imports,
    ) {
    }

    public function index(Product $product): Response
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('viewAny', [ProductComponent::class, $organization]);
        $this->authorize('view', [$product, $organization]);

        return Inertia::render('products/components/Index', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productPayload($product),
            'versions' => $this->versionOptions($product),
            'canManage' => request()->user()->canManageComponents($organization),
            'options' => $this->enumOptions(),
        ]);
    }

    public function create(Product $product): Response
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('create', [ProductComponent::class, $organization]);

        return Inertia::render('products/components/Create', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productPayload($product),
            'versions' => $this->versionOptions($product),
            'options' => $this->enumOptions(),
        ]);
    }

    public function store(StoreProductComponentRequest $request, Product $product): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);

        $component = $this->components->create($product, $this->validatedAttributes($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.components.created'),
        ]);

        return redirect()->route('products.components.edit', [$product, $component]);
    }

    public function edit(Product $product, ProductComponent $component): Response
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertComponentBelongsToProduct($component, $product);
        $this->authorize('view', [$component, $organization]);

        $component->load('productVersion');

        return Inertia::render('products/components/Edit', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productPayload($product),
            'component' => $this->components->detailPayload($component),
            'versions' => $this->versionOptions($product),
            'options' => $this->enumOptions(),
            'canManage' => request()->user()->canManageComponents($organization),
        ]);
    }

    public function update(
        UpdateProductComponentRequest $request,
        Product $product,
        ProductComponent $component,
    ): RedirectResponse {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertComponentBelongsToProduct($component, $product);

        $this->components->update($component, $this->validatedAttributes($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.components.updated'),
        ]);

        return redirect()->route('products.components.edit', [$product, $component]);
    }

    public function destroy(Product $product, ProductComponent $component): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertComponentBelongsToProduct($component, $product);
        $this->authorize('delete', [$component, $organization]);

        $this->components->delete($component);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.components.deleted'),
        ]);

        return redirect()->route('products.components.index', $product);
    }

    public function importForm(Product $product): Response
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('create', [ProductComponent::class, $organization]);

        return Inertia::render('products/components/Import', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productPayload($product),
            'versions' => $this->versionOptions($product),
            'options' => [
                'formats' => [
                    SbomFormat::CycloneDxJson->value,
                    SbomFormat::ComposerLock->value,
                ],
            ],
        ]);
    }

    public function import(ImportSbomRequest $request, Product $product): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);

        $version = ProductVersion::query()->findOrFail((int) $request->input('product_version_id'));
        $forcedFormat = $request->filled('format')
            ? SbomFormat::from($request->string('format')->toString())
            : null;

        if ($forcedFormat === SbomFormat::Manual) {
            $forcedFormat = null;
        }

        $result = $this->imports->import(
            $product,
            $version,
            $request->file('file'),
            $request->user(),
            $forcedFormat,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.components.imported', [
                'count' => (string) ($result['imported'] + $result['updated']),
            ]),
        ]);

        return redirect()->route('products.components.index', [
            'product' => $product,
            'version_id' => $version->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedAttributes(
        StoreProductComponentRequest|UpdateProductComponentRequest $request,
    ): array {
        $purl = $request->input('purl');
        $purl = is_string($purl) && trim($purl) !== '' ? trim($purl) : null;

        return [
            'product_version_id' => (int) $request->input('product_version_id'),
            'name' => $request->string('name')->toString(),
            'supplier' => $request->input('supplier'),
            'package_ecosystem' => PackageEcosystem::from($request->string('package_ecosystem')->toString()),
            'version' => $request->input('version'),
            'licence' => $request->input('licence'),
            'purl' => $purl,
            'hash' => $request->input('hash'),
            'is_direct' => $request->boolean('is_direct', true),
            'is_dev' => $request->boolean('is_dev', false),
            'usage_context' => $request->input('usage_context'),
            'support_status' => ComponentSupportStatus::from($request->string('support_status')->toString()),
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

    private function assertComponentBelongsToProduct(ProductComponent $component, Product $product): void
    {
        if ($component->product_id !== $product->id) {
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
     * @return array{ecosystems: list<string>, support_statuses: list<string>}
     */
    private function enumOptions(): array
    {
        return [
            'ecosystems' => array_column(PackageEcosystem::cases(), 'value'),
            'support_statuses' => array_column(ComponentSupportStatus::cases(), 'value'),
        ];
    }
}
