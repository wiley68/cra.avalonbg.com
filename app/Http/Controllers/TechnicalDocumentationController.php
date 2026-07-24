<?php

namespace App\Http\Controllers;

use App\Enums\TechnicalDocumentationSectionKey;
use App\Enums\TechnicalDocumentationStatus;
use App\Http\Requests\StoreTechnicalDocumentationRequest;
use App\Http\Requests\UpdateTechnicalDocumentationRequest;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVersion;
use App\Models\TechnicalDocumentationPackage;
use App\Services\TechnicalDocumentationService;
use App\Support\Translations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TechnicalDocumentationController extends Controller
{
    public function __construct(
        private readonly TechnicalDocumentationService $packages,
    ) {
    }

    public function index(Product $product): InertiaResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('viewAny', [TechnicalDocumentationPackage::class, $organization]);
        $this->authorize('view', [$product, $organization]);

        return Inertia::render('products/technical-documentation/Index', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productPayload($product),
            'versions' => $this->versionOptions($product),
            'canManage' => request()->user()->canManageProducts($organization),
        ]);
    }

    public function create(Product $product): InertiaResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('create', [TechnicalDocumentationPackage::class, $organization]);

        return Inertia::render('products/technical-documentation/Create', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productPayload($product),
            'versions' => $this->versionOptions($product),
            'options' => $this->enumOptions($organization),
            'hasPublishedPrevious' => $this->packages->hasPublishedPrevious($product),
        ]);
    }

    public function store(StoreTechnicalDocumentationRequest $request, Product $product): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);

        $package = $this->packages->create(
            $product,
            [
                'title' => $request->string('title')->toString(),
                'version_label' => $request->string('version_label')->toString(),
                'locale' => $request->string('locale')->toString(),
                'notes' => $request->input('notes'),
                'inherit_from_previous' => $request->boolean('inherit_from_previous', true),
                'product_version_id' => $request->input('product_version_id') !== null
                    ? (int) $request->input('product_version_id')
                    : null,
            ],
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.technical_documentation.created'),
        ]);

        return redirect()->route('products.technical-documentation.edit', [$product, $package]);
    }

    public function edit(Product $product, TechnicalDocumentationPackage $package): InertiaResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertPackageBelongsToProduct($package, $product);
        $this->authorize('view', [$package, $organization]);

        return Inertia::render('products/technical-documentation/Edit', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productPayload($product),
            'package' => $this->packages->detailPayload($package),
            'versions' => $this->versionOptions($product),
            'options' => $this->enumOptions($organization),
            'canManage' => request()->user()->canManageProducts($organization),
            'memberOptions' => $this->memberOptions($organization),
            'reviewTask' => $this->packages->openReviewTaskPayload($package),
        ]);
    }

    public function update(
        UpdateTechnicalDocumentationRequest $request,
        Product $product,
        TechnicalDocumentationPackage $package,
    ): RedirectResponse {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertPackageBelongsToProduct($package, $product);

        $this->packages->update(
            $package,
            [
                'title' => $request->string('title')->toString(),
                'version_label' => $request->string('version_label')->toString(),
                'locale' => $request->string('locale')->toString(),
                'notes' => $request->input('notes'),
                'product_version_id' => $request->input('product_version_id') !== null
                    ? (int) $request->input('product_version_id')
                    : null,
                'sections' => $request->input('sections', []),
            ],
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.technical_documentation.updated'),
        ]);

        return redirect()->route('products.technical-documentation.edit', [$product, $package]);
    }

    public function destroy(Product $product, TechnicalDocumentationPackage $package): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertPackageBelongsToProduct($package, $product);
        $this->authorize('delete', [$package, $organization]);

        $this->packages->delete($package, request()->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.technical_documentation.deleted'),
        ]);

        return redirect()->route('products.technical-documentation.index', $product);
    }

    public function refreshGenerated(
        Product $product,
        TechnicalDocumentationPackage $package,
    ): RedirectResponse {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertPackageBelongsToProduct($package, $product);
        $this->authorize('update', [$package, $organization]);

        $this->packages->refreshGenerated($package, request()->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.technical_documentation.generated_refreshed'),
        ]);

        return redirect()->route('products.technical-documentation.edit', [$product, $package]);
    }

    public function submitReview(
        Request $request,
        Product $product,
        TechnicalDocumentationPackage $package,
    ): RedirectResponse {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertPackageBelongsToProduct($package, $product);
        $this->authorize('update', [$package, $organization]);

        $validated = $request->validate([
            'assignee_user_id' => [
                'nullable',
                'integer',
                Rule::exists('organization_user', 'user_id')->where(
                    fn($query) => $query->where('organization_id', $organization->id),
                ),
            ],
        ]);

        $this->packages->submitForReview(
            $package,
            $request->user(),
            isset($validated['assignee_user_id'])
            ? (int) $validated['assignee_user_id']
            : null,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.technical_documentation.submitted'),
        ]);

        return redirect()->route('products.technical-documentation.edit', [$product, $package]);
    }

    public function publish(
        Product $product,
        TechnicalDocumentationPackage $package,
    ): RedirectResponse {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertPackageBelongsToProduct($package, $product);
        $this->authorize('update', [$package, $organization]);

        $this->packages->publish($package, request()->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.technical_documentation.published'),
        ]);

        return redirect()->route('products.technical-documentation.edit', [$product, $package]);
    }

    public function retire(
        Product $product,
        TechnicalDocumentationPackage $package,
    ): RedirectResponse {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertPackageBelongsToProduct($package, $product);
        $this->authorize('update', [$package, $organization]);

        $this->packages->retire($package, request()->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.technical_documentation.retired'),
        ]);

        return redirect()->route('products.technical-documentation.edit', [$product, $package]);
    }

    /**
     * @return array{
     *     locales: list<string>,
     *     statuses: list<string>,
     *     section_keys: list<string>,
     *     default_locale: string
     * }
     */
    private function enumOptions(Organization $organization): array
    {
        return [
            'locales' => Organization::LOCALES,
            'statuses' => array_map(
                fn(TechnicalDocumentationStatus $status) => $status->value,
                TechnicalDocumentationStatus::cases(),
            ),
            'section_keys' => array_map(
                fn(TechnicalDocumentationSectionKey $key) => $key->value,
                TechnicalDocumentationSectionKey::ordered(),
            ),
            'default_locale' => $organization->resolvedLocale(),
        ];
    }

    /**
     * @return list<array{id: int, version_number: string}>
     */
    private function versionOptions(Product $product): array
    {
        return ProductVersion::query()
            ->where('product_id', $product->id)
            ->orderByDesc('id')
            ->get(['id', 'version_number'])
            ->map(fn(ProductVersion $version) => [
                'id' => $version->id,
                'version_number' => $version->version_number,
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function memberOptions(Organization $organization): array
    {
        return $organization->users()
            ->orderBy('name')
            ->get(['users.id', 'users.name'])
            ->map(fn($user) => [
                'id' => $user->id,
                'name' => $user->name,
            ])
            ->all();
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

    private function assertPackageBelongsToProduct(
        TechnicalDocumentationPackage $package,
        Product $product,
    ): void {
        if ($package->product_id !== $product->id) {
            abort(404);
        }
    }
}
