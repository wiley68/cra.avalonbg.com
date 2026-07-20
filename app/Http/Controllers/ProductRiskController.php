<?php

namespace App\Http\Controllers;

use App\Enums\ProductRiskStatus;
use App\Enums\RiskCategory;
use App\Enums\RiskImpact;
use App\Enums\RiskLikelihood;
use App\Enums\RiskTreatment;
use App\Http\Requests\StoreProductRiskRequest;
use App\Http\Requests\UpdateProductRiskRequest;
use App\Models\Control;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductControl;
use App\Models\ProductRisk;
use App\Models\Requirement;
use App\Services\ProductRiskService;
use App\Support\Translations;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProductRiskController extends Controller
{
    public function __construct(
        private readonly ProductRiskService $risks,
    ) {
    }

    public function index(Product $product): Response
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('viewAny', [ProductRisk::class, $organization]);
        $this->authorize('view', [$product, $organization]);

        return Inertia::render('products/risks/Index', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productPayload($product),
            'canManage' => request()->user()->canManageRisks($organization),
            'options' => $this->enumOptions(),
        ]);
    }

    public function create(Product $product): Response
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('create', [ProductRisk::class, $organization]);

        return Inertia::render('products/risks/Create', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productPayload($product),
            'members' => $this->memberOptions($organization),
            'versions' => $this->versionOptions($product),
            'controls' => $this->controlOptions($product, $organization),
            'requirements' => $this->requirementOptions(),
            'options' => $this->enumOptions(),
        ]);
    }

    public function store(StoreProductRiskRequest $request, Product $product): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);

        $risk = $this->risks->create(
            $product,
            $this->validatedAttributes($request),
            $request->input('control_ids', []),
            $request->input('requirement_ids', []),
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.risks.created'),
        ]);

        return redirect()->route('products.risks.edit', [$product, $risk]);
    }

    public function edit(Product $product, ProductRisk $risk): Response
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertRiskBelongsToProduct($risk, $product);
        $this->authorize('view', [$risk, $organization]);

        $risk->load(['owner', 'controls', 'requirements', 'productVersion']);

        return Inertia::render('products/risks/Edit', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productPayload($product),
            'risk' => $this->risks->detailPayload($risk),
            'members' => $this->memberOptions($organization),
            'versions' => $this->versionOptions($product),
            'controls' => $this->controlOptions($product, $organization),
            'requirements' => $this->requirementOptions(),
            'options' => $this->enumOptions(),
            'canManage' => request()->user()->canManageRisks($organization),
        ]);
    }

    public function update(
        UpdateProductRiskRequest $request,
        Product $product,
        ProductRisk $risk,
    ): RedirectResponse {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertRiskBelongsToProduct($risk, $product);

        $this->risks->update(
            $risk,
            $this->validatedAttributes($request),
            $request->input('control_ids', []),
            $request->input('requirement_ids', []),
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.risks.updated'),
        ]);

        return redirect()->route('products.risks.edit', [$product, $risk]);
    }

    public function destroy(Product $product, ProductRisk $risk): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertRiskBelongsToProduct($risk, $product);
        $this->authorize('delete', [$risk, $organization]);

        $this->risks->delete($risk);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.risks.deleted'),
        ]);

        return redirect()->route('products.risks.index', $product);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedAttributes(StoreProductRiskRequest|UpdateProductRiskRequest $request): array
    {
        return [
            'title' => $request->string('title')->toString(),
            'asset' => $request->input('asset'),
            'threat' => $request->input('threat'),
            'weakness' => $request->input('weakness'),
            'attack_scenario' => $request->input('attack_scenario'),
            'category' => RiskCategory::from($request->string('category')->toString()),
            'likelihood' => RiskLikelihood::from((int) $request->input('likelihood')),
            'impact' => RiskImpact::from((int) $request->input('impact')),
            'residual_likelihood' => $request->filled('residual_likelihood')
                ? RiskLikelihood::from((int) $request->input('residual_likelihood'))
                : null,
            'residual_impact' => $request->filled('residual_impact')
                ? RiskImpact::from((int) $request->input('residual_impact'))
                : null,
            'treatment' => RiskTreatment::from($request->string('treatment')->toString()),
            'treatment_plan' => $request->input('treatment_plan'),
            'status' => ProductRiskStatus::from($request->string('status')->toString()),
            'owner_user_id' => $request->input('owner_user_id') ? (int) $request->input('owner_user_id') : null,
            'deadline' => $request->input('deadline'),
            'product_version_id' => $request->input('product_version_id')
                ? (int) $request->input('product_version_id')
                : null,
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

    private function assertRiskBelongsToProduct(ProductRisk $risk, Product $product): void
    {
        if ($risk->product_id !== $product->id) {
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
     * Prefer controls already assigned to the product; fall back to all active org controls.
     *
     * @return list<array{id: int, code: string, name: string, description: string|null, assigned: bool}>
     */
    private function controlOptions(Product $product, Organization $organization): array
    {
        $assignedIds = ProductControl::query()
            ->where('product_id', $product->id)
            ->pluck('control_id')
            ->all();

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
                'assigned' => in_array($control->id, $assignedIds, true),
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
     * @return array{
     *     categories: list<string>,
     *     likelihoods: list<int>,
     *     impacts: list<int>,
     *     treatments: list<string>,
     *     statuses: list<string>
     * }
     */
    private function enumOptions(): array
    {
        return [
            'categories' => array_column(RiskCategory::cases(), 'value'),
            'likelihoods' => array_column(RiskLikelihood::cases(), 'value'),
            'impacts' => array_column(RiskImpact::cases(), 'value'),
            'treatments' => array_column(RiskTreatment::cases(), 'value'),
            'statuses' => array_column(ProductRiskStatus::cases(), 'value'),
        ];
    }
}
