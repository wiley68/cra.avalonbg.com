<?php

namespace App\Http\Controllers;

use App\Enums\RequirementApplicabilityStatus;
use App\Http\Requests\UpdateProductRequirementRequest;
use App\Models\Control;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductRequirement;
use App\Services\ProductRequirementService;
use App\Support\Translations;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProductRequirementController extends Controller
{
    public function __construct(
        private readonly ProductRequirementService $requirements,
    ) {
    }

    public function index(Product $product): Response
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('viewAny', [ProductRequirement::class, $organization]);
        $this->authorize('view', [$product, $organization]);

        $this->requirements->ensureMatrix($product);

        return Inertia::render('products/requirements/Index', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
            ],
            'canManage' => request()->user()->canManageRequirements($organization),
            'options' => [
                'statuses' => array_column(RequirementApplicabilityStatus::cases(), 'value'),
            ],
            'members' => $organization->users()
                ->orderBy('name')
                ->get(['users.id', 'users.name', 'users.email'])
                ->map(fn($user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ])
                ->all(),
        ]);
    }

    public function edit(Product $product, ProductRequirement $requirement): Response
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertRequirementBelongsToProduct($requirement, $product);
        $this->authorize('view', [$requirement, $organization]);

        $requirement->load(['requirement.regulation', 'requirementVersion', 'owner', 'histories' => fn($q) => $q->latest('id')->limit(20)]);

        $linkedControls = Control::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->whereHas('requirements', fn($q) => $q->where('requirements.id', $requirement->requirement_id))
            ->with(['productControls' => fn($q) => $q->where('product_id', $product->id)])
            ->orderBy('name')
            ->get()
            ->map(fn(Control $control) => [
                'id' => $control->id,
                'code' => $control->code,
                'name' => $control->localized('name') ?? $control->name,
                'product_control' => ($pc = $control->productControls->first())
                    ? [
                        'id' => $pc->id,
                        'status' => $pc->status->value,
                        'notes' => $pc->notes,
                    ]
                    : null,
            ])
            ->all();

        return Inertia::render('products/requirements/Edit', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
            ],
            'productRequirement' => $this->requirements->listItemPayload($requirement),
            'linkedControls' => $linkedControls,
            'histories' => $requirement->histories->map(fn($history) => [
                'id' => $history->id,
                'from_status' => $history->from_status?->value ?? $history->getRawOriginal('from_status'),
                'to_status' => $history->to_status instanceof RequirementApplicabilityStatus
                    ? $history->to_status->value
                    : (string) $history->to_status,
                'rationale' => $history->rationale,
                'changed_by' => $history->changed_by,
                'created_at' => $history->created_at?->toIso8601String(),
            ])->all(),
            'canManage' => request()->user()->canManageRequirements($organization),
            'canManageControls' => request()->user()->canManageControls($organization),
            'options' => [
                'statuses' => array_column(RequirementApplicabilityStatus::cases(), 'value'),
            ],
            'members' => $organization->users()
                ->orderBy('name')
                ->get(['users.id', 'users.name', 'users.email'])
                ->map(fn($user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ])
                ->all(),
        ]);
    }

    public function update(
        UpdateProductRequirementRequest $request,
        Product $product,
        ProductRequirement $requirement,
    ): RedirectResponse {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertRequirementBelongsToProduct($requirement, $product);

        $this->requirements->updateApplicability(
            $requirement,
            RequirementApplicabilityStatus::from($request->string('status')->toString()),
            $request->input('rationale'),
            $request->input('owner_user_id') ? (int) $request->input('owner_user_id') : null,
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.requirements.updated'),
        ]);

        return redirect()->route('products.requirements.edit', [$product, $requirement]);
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

    private function assertRequirementBelongsToProduct(ProductRequirement $requirement, Product $product): void
    {
        if ($requirement->product_id !== $product->id) {
            abort(404);
        }
    }
}
