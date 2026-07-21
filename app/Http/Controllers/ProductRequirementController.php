<?php

namespace App\Http\Controllers;

use App\Enums\RequirementApplicabilityStatus;
use App\Http\Requests\UpdateProductRequirementRequest;
use App\Models\Control;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductRequirement;
use App\Models\ProductRequirementHistory;
use App\Services\ProductRequirementService;
use App\Support\RelatedPolicyTypes;
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
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productPayload($product),
            'canManage' => request()->user()->canManageRequirements($organization),
            'options' => [
                'statuses' => array_column(RequirementApplicabilityStatus::cases(), 'value'),
            ],
            'members' => $this->memberOptions($organization),
        ]);
    }

    public function edit(Product $product, ProductRequirement $requirement): Response
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertRequirementBelongsToProduct($requirement, $product);
        $this->authorize('view', [$requirement, $organization]);

        $requirement->load([
            'requirement.regulation',
            'requirementVersion',
            'owner',
            'histories' => fn($query) => $query->latest('id')->limit(20),
        ]);

        return Inertia::render('products/requirements/Edit', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productPayload($product),
            'productRequirement' => $this->requirements->listItemPayload($requirement),
            'linkedControls' => $this->linkedControlsPayload($organization, $product, $requirement),
            'relatedPolicyTypes' => RelatedPolicyTypes::forRequirement(
                (string) ($requirement->requirement?->code ?? ''),
            ),
            'histories' => $this->historiesPayload($requirement),
            'canManage' => request()->user()->canManageRequirements($organization),
            'canManageControls' => request()->user()->canManageControls($organization),
            'options' => [
                'statuses' => array_column(RequirementApplicabilityStatus::cases(), 'value'),
            ],
            'members' => $this->memberOptions($organization),
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
     * @return list<array{
     *     id: int,
     *     code: string,
     *     name: string,
     *     product_control: array{id: int, status: string, notes: string|null}|null
     * }>
     */
    private function linkedControlsPayload(
        Organization $organization,
        Product $product,
        ProductRequirement $requirement,
    ): array {
        return Control::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->whereHas(
                'requirements',
                fn($query) => $query->where('requirements.id', $requirement->requirement_id),
            )
            ->with(['productControls' => fn($query) => $query->where('product_id', $product->id)])
            ->orderBy('name')
            ->get()
            ->map(function (Control $control): array {
                $productControl = $control->productControls->first();

                return [
                    'id' => $control->id,
                    'code' => $control->code,
                    'name' => $control->name,
                    'product_control' => $productControl === null
                        ? null
                        : [
                            'id' => $productControl->id,
                            'status' => $productControl->status->value,
                            'notes' => $productControl->notes,
                        ],
                ];
            })
            ->all();
    }

    /**
     * @return list<array{
     *     id: int,
     *     from_status: string|null,
     *     to_status: string,
     *     rationale: string|null,
     *     changed_by: int|null,
     *     created_at: string|null
     * }>
     */
    private function historiesPayload(ProductRequirement $requirement): array
    {
        return $requirement->histories
            ->map(function (ProductRequirementHistory $history): array {
                return [
                    'id' => $history->id,
                    'from_status' => $history->from_status?->value,
                    'to_status' => $history->to_status instanceof RequirementApplicabilityStatus
                        ? $history->to_status->value
                        : (string) $history->to_status,
                    'rationale' => $history->rationale,
                    'changed_by' => $history->changed_by,
                    'created_at' => $history->created_at?->toIso8601String(),
                ];
            })
            ->all();
    }
}
