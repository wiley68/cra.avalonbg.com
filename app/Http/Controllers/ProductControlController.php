<?php

namespace App\Http\Controllers;

use App\Enums\ProductControlStatus;
use App\Http\Requests\StoreProductControlRequest;
use App\Http\Requests\UpdateProductControlRequest;
use App\Models\Control;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductControl;
use App\Services\ControlService;
use App\Support\RelatedPolicyTypes;
use App\Support\Translations;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProductControlController extends Controller
{
    public function __construct(
        private readonly ControlService $controls,
    ) {
    }

    public function index(Product $product): Response
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('viewAny', [ProductControl::class, $organization]);
        $this->authorize('view', [$product, $organization]);

        return Inertia::render('products/controls/Index', [
            'organization' => $this->organizationPayload($organization),
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
            ],
            'canManage' => request()->user()->canManageControls($organization),
            'options' => [
                'statuses' => array_column(ProductControlStatus::cases(), 'value'),
            ],
        ]);
    }

    public function create(Product $product): Response
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('create', [ProductControl::class, $organization]);

        $assignedIds = ProductControl::query()
            ->where('product_id', $product->id)
            ->pluck('control_id')
            ->all();

        $availableControls = Control::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->whereNotIn('id', $assignedIds)
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn(Control $control) => [
                'id' => $control->id,
                'code' => $control->code,
                'name' => $control->name,
            ])
            ->all();

        return Inertia::render('products/controls/Create', [
            'organization' => $this->organizationPayload($organization),
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
            ],
            'availableControls' => $availableControls,
            'options' => [
                'statuses' => array_column(ProductControlStatus::cases(), 'value'),
            ],
        ]);
    }

    public function store(StoreProductControlRequest $request, Product $product): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);

        $control = Control::query()->findOrFail((int) $request->input('control_id'));
        $this->assertControlInOrganization($control, $organization);

        $productControl = $this->controls->assignToProduct(
            $product,
            $control,
            ProductControlStatus::from($request->string('status')->toString()),
            $request->input('notes'),
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.controls.assigned'),
        ]);

        return redirect()->route('products.controls.edit', [$product, $productControl]);
    }

    public function edit(Product $product, ProductControl $productControl): Response
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertProductControlBelongsToProduct($productControl, $product);
        $this->authorize('view', [$productControl, $organization]);

        $productControl->load('control.requirements');

        return Inertia::render('products/controls/Edit', [
            'organization' => $this->organizationPayload($organization),
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
            ],
            'productControl' => [
                'id' => $productControl->id,
                'status' => $productControl->status->value,
                'notes' => $productControl->notes,
                'reviewed_at' => $productControl->reviewed_at?->toIso8601String(),
                'control' => [
                    'id' => $productControl->control->id,
                    'code' => $productControl->control->code,
                    'name' => $productControl->control->name,
                    'description' => $productControl->control->description,
                    'implementation_guidance' => $productControl->control->implementation_guidance,
                    'requirement_codes' => $productControl->control->requirements->pluck('code')->all(),
                ],
            ],
            'relatedPolicyTypes' => RelatedPolicyTypes::forControl(
                $productControl->control->code,
                $productControl->control->requirements->pluck('code')->all(),
            ),
            'canManage' => request()->user()->canManageControls($organization),
            'options' => [
                'statuses' => array_column(ProductControlStatus::cases(), 'value'),
            ],
        ]);
    }

    public function update(
        UpdateProductControlRequest $request,
        Product $product,
        ProductControl $productControl,
    ): RedirectResponse {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertProductControlBelongsToProduct($productControl, $product);

        $this->controls->updateProductControl(
            $productControl,
            ProductControlStatus::from($request->string('status')->toString()),
            $request->input('notes'),
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.controls.updated'),
        ]);

        return redirect()->route('products.controls.edit', [$product, $productControl]);
    }

    public function destroy(Product $product, ProductControl $productControl): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertProductControlBelongsToProduct($productControl, $product);
        $this->authorize('delete', [$productControl, $organization]);

        $productControl->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.controls.removed'),
        ]);

        return redirect()->route('products.controls.index', $product);
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

    private function assertControlInOrganization(Control $control, Organization $organization): void
    {
        if ($control->organization_id !== $organization->id) {
            abort(404);
        }
    }

    private function assertProductControlBelongsToProduct(ProductControl $productControl, Product $product): void
    {
        if ($productControl->product_id !== $product->id) {
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
}
