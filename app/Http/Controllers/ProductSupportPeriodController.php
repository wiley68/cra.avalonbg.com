<?php

namespace App\Http\Controllers;

use App\Enums\SupportPeriodStartBasis;
use App\Enums\SupportPeriodType;
use App\Http\Requests\StoreProductSupportPeriodRequest;
use App\Http\Requests\UpdateProductSupportPeriodRequest;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductSupportPeriod;
use App\Models\ProductVersion;
use App\Support\Translations;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProductSupportPeriodController extends Controller
{
    public function index(Product $product): Response
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('view', [$product, $organization]);

        $periods = $product->supportPeriods()
            ->with(['versions:id,version_number,release_date'])
            ->orderByDesc('id')
            ->get()
            ->map(fn(ProductSupportPeriod $period) => $this->periodPayload($period));

        return Inertia::render('products/support-periods/Index', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productSummary($product),
            'periods' => $periods,
            'canManage' => request()->user()->canManageProducts($organization),
        ]);
    }

    public function create(Product $product): Response
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('update', [$product, $organization]);

        return Inertia::render('products/support-periods/Create', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productSummary($product),
            'versions' => $this->versionOptions($product),
            'options' => $this->enumOptions(),
        ]);
    }

    public function store(StoreProductSupportPeriodRequest $request, Product $product): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);

        $period = $product->supportPeriods()->create($this->validatedAttributes($request));
        $period->versions()->sync($this->validatedVersionIds($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.support_periods.created'),
        ]);

        return redirect()->route('products.support-periods.index', $product);
    }

    public function edit(Product $product, ProductSupportPeriod $support_period): Response
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertPeriodBelongsToProduct($product, $support_period);
        $this->authorize('update', [$product, $organization]);

        $support_period->load(['versions:id,version_number,release_date']);

        return Inertia::render('products/support-periods/Edit', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productSummary($product),
            'period' => $this->periodPayload($support_period),
            'versions' => $this->versionOptions($product),
            'options' => $this->enumOptions(),
        ]);
    }

    public function update(
        UpdateProductSupportPeriodRequest $request,
        Product $product,
        ProductSupportPeriod $support_period,
    ): RedirectResponse {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertPeriodBelongsToProduct($product, $support_period);

        $support_period->update($this->validatedAttributes($request));
        $support_period->versions()->sync($this->validatedVersionIds($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.support_periods.updated'),
        ]);

        return redirect()->route('products.support-periods.index', $product);
    }

    public function destroy(Product $product, ProductSupportPeriod $support_period): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertPeriodBelongsToProduct($product, $support_period);
        $this->authorize('update', [$product, $organization]);

        $support_period->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.support_periods.deleted'),
        ]);

        return redirect()->route('products.support-periods.index', $product);
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

    private function assertPeriodBelongsToProduct(Product $product, ProductSupportPeriod $period): void
    {
        if ($period->product_id !== $product->id) {
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
     * @return list<array{id: int, version_number: string, release_date: string|null}>
     */
    private function versionOptions(Product $product): array
    {
        return $product->versions()
            ->orderByDesc('version_number')
            ->get(['id', 'version_number', 'release_date'])
            ->map(fn(ProductVersion $version) => [
                'id' => (int) $version->id,
                'version_number' => $version->version_number,
                'release_date' => $version->release_date?->toDateString(),
            ])
            ->all();
    }

    /**
     * @return array{types: list<string>, start_bases: list<string>}
     */
    private function enumOptions(): array
    {
        return [
            'types' => array_column(SupportPeriodType::cases(), 'value'),
            'start_bases' => array_column(SupportPeriodStartBasis::cases(), 'value'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedAttributes(StoreProductSupportPeriodRequest $request): array
    {
        return [
            'type' => $request->string('type')->toString(),
            'start_basis' => $request->string('start_basis')->toString(),
            'duration_months' => $request->integer('duration_months'),
            'basis' => $request->input('basis'),
            'is_extended' => $request->boolean('is_extended'),
            'exceptions_notes' => $request->input('exceptions_notes'),
        ];
    }

    /**
     * @return list<int>
     */
    private function validatedVersionIds(StoreProductSupportPeriodRequest $request): array
    {
        /** @var list<int>|null $ids */
        $ids = $request->validated('version_ids');

        return array_values(array_map('intval', $ids ?? []));
    }

    /**
     * @return array<string, mixed>
     */
    private function periodPayload(ProductSupportPeriod $period): array
    {
        return [
            'id' => $period->id,
            'type' => $period->type->value,
            'start_basis' => $period->start_basis->value,
            'duration_months' => $period->duration_months,
            'basis' => $period->basis,
            'is_extended' => $period->is_extended,
            'exceptions_notes' => $period->exceptions_notes,
            'schedule_resolved' => $period->scheduleResolved(),
            'effective_starts_at' => $period->effectiveStartsAt()?->toDateString(),
            'effective_ends_at' => $period->effectiveEndsAt()?->toDateString(),
            'is_active' => $period->isActive(),
            'days_until_end' => $period->daysUntilEnd(),
            'version_ids' => $period->versions->pluck('id')->map(fn($id) => (int) $id)->values()->all(),
            'versions' => $period->versions->map(fn(ProductVersion $version) => [
                'id' => (int) $version->id,
                'version_number' => $version->version_number,
                'release_date' => $version->release_date?->toDateString(),
            ])->values()->all(),
        ];
    }
}
