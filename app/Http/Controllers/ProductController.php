<?php

namespace App\Http\Controllers;

use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Organization;
use App\Models\Product;
use App\Services\ClassificationAssessmentService;
use App\Services\ProductReadinessService;
use App\Services\ProductRepositoryService;
use App\Services\ScopeAssessmentService;
use App\Services\VcsImportSuggestionService;
use App\Support\AuditLogger;
use App\Support\Translations;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(
        private readonly ScopeAssessmentService $scopeAssessments,
        private readonly ClassificationAssessmentService $classificationAssessments,
        private readonly ProductReadinessService $readiness,
        private readonly ProductRepositoryService $repositories,
        private readonly VcsImportSuggestionService $vcsSuggestions,
    ) {
    }

    public function index(): Response
    {
        $organization = $this->currentOrganization();
        $this->authorize('viewAny', [Product::class, $organization]);

        return Inertia::render('products/Index', [
            'organization' => $this->organizationPayload($organization),
            'canManage' => request()->user()->canManageProducts($organization),
        ]);
    }

    public function create(): Response
    {
        $organization = $this->currentOrganization();
        $this->authorize('create', [Product::class, $organization]);

        return Inertia::render('products/Create', [
            'organization' => $this->organizationPayload($organization),
            'members' => $this->memberOptions($organization),
            'options' => $this->enumOptions(),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $user = $request->user();

        $product = Product::query()->create([
            ...$this->validatedAttributes($request),
            'organization_id' => $organization->id,
            'scope_reviewed_at' => now(),
            'scope_reviewed_by' => $user->id,
            'classification_reviewed_at' => now(),
            'classification_reviewed_by' => $user->id,
        ]);

        $openScopeWizard = !$request->boolean('skip_scope_wizard');
        $openClassificationWizard = !$request->boolean('skip_classification_wizard');

        if ($request->filled('scope_assessment.answers')) {
            $this->scopeAssessments->storeAndApply(
                $product,
                $request->input('scope_assessment.answers', []),
                ScopeStatus::from($request->string('scope_assessment.final_status')->toString()),
                $request->input('scope_assessment.rationale'),
                $user,
            );
            $openScopeWizard = false;
        }

        if ($request->filled('classification_assessment.answers')) {
            $this->classificationAssessments->storeAndApply(
                $product,
                $request->input('classification_assessment.answers', []),
                ClassificationStatus::from($request->string('classification_assessment.final_status')->toString()),
                $request->input('classification_assessment.rationale'),
                $request->string('classification_assessment.regulatory_content_version')->toString(),
                $request->input('classification_assessment.evidence_notes'),
                $request->input('classification_assessment.next_review_at'),
                $user,
            );
            $openClassificationWizard = false;
        }

        AuditLogger::logProductCreated($product, $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.created'),
        ]);

        if ($openScopeWizard) {
            return redirect()->route('products.edit', [
                'product' => $product,
                'scope_wizard' => 1,
            ]);
        }

        if ($openClassificationWizard) {
            return redirect()->route('products.edit', [
                'product' => $product,
                'classification_wizard' => 1,
            ]);
        }

        return redirect()->route('products.edit', $product);
    }

    public function edit(Product $product): Response
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('update', [$product, $organization]);

        return Inertia::render('products/Edit', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productPayload($product),
            'members' => $this->memberOptions($organization),
            'options' => $this->enumOptions(),
            'module_statuses' => $this->readiness->cardModuleStatuses($product),
            'canManage' => true,
            'repository' => $this->repositories->payload($product->repository),
            'vcs_connections' => ProductRepositoryController::connectionOptions($organization),
            'vcs_suggestions' => $this->vcsSuggestions->pendingPayloadForProduct($product->id),
            'latestScopeAssessment' => $this->scopeAssessments->latestPayload(
                $product->latestScopeAssessment(),
            ),
            'latestClassification' => $this->classificationAssessments->latestPayload(
                $product->latestClassification(),
            ),
            'openScopeWizard' => request()->boolean('scope_wizard'),
            'openClassificationWizard' => request()->boolean('classification_wizard'),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);

        $attributes = $this->validatedAttributes($request);
        $user = $request->user();

        if (
            $attributes['scope_status'] !== $product->scope_status->value
            || ($attributes['scope_rationale'] ?? null) !== $product->scope_rationale
        ) {
            $attributes['scope_reviewed_at'] = now();
            $attributes['scope_reviewed_by'] = $user->id;
        }

        if (
            $attributes['classification_status'] !== $product->classification_status->value
            || ($attributes['classification_rationale'] ?? null) !== $product->classification_rationale
        ) {
            $attributes['classification_reviewed_at'] = now();
            $attributes['classification_reviewed_by'] = $user->id;
        }

        $product->update($attributes);

        AuditLogger::logProductUpdated($product->fresh(), $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.updated'),
        ]);

        return redirect()->route('products.edit', $product);
    }

    public function destroy(Product $product): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('delete', [$product, $organization]);

        $actor = request()->user();
        AuditLogger::logProductDeleted($product, $actor);

        $product->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.deleted'),
        ]);

        return redirect()->route('products.index');
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
     * @return array{
     *     product_types: list<string>,
     *     licensing_models: list<string>,
     *     scope_statuses: list<string>,
     *     classification_statuses: list<string>
     * }
     */
    private function enumOptions(): array
    {
        return [
            'product_types' => array_column(ProductType::cases(), 'value'),
            'licensing_models' => array_column(LicensingModel::cases(), 'value'),
            'scope_statuses' => array_column(ScopeStatus::cases(), 'value'),
            'classification_statuses' => array_column(ClassificationStatus::cases(), 'value'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedAttributes(StoreProductRequest $request): array
    {
        return [
            'name' => $request->string('name')->toString(),
            'slug' => $request->string('slug')->toString(),
            'product_line' => $request->input('product_line'),
            'description' => $request->input('description'),
            'intended_purpose' => $request->input('intended_purpose'),
            'product_type' => $request->string('product_type')->toString(),
            'manufacturer' => $request->input('manufacturer'),
            'trademark' => $request->input('trademark'),
            'licensing_model' => $request->string('licensing_model')->toString(),
            'has_remote_data_processing' => $request->boolean('has_remote_data_processing'),
            'has_network_connectivity' => $request->boolean('has_network_connectivity'),
            'deployment_model' => $request->input('deployment_model'),
            'support_period_notes' => $request->input('support_period_notes'),
            'end_of_support_policy' => $request->input('end_of_support_policy'),
            'product_owner_user_id' => $request->input('product_owner_user_id') ?: null,
            'security_contact_user_id' => $request->input('security_contact_user_id') ?: null,
            'scope_status' => $request->string('scope_status')->toString(),
            'scope_rationale' => $request->input('scope_rationale'),
            'classification_status' => $request->string('classification_status')->toString(),
            'classification_rationale' => $request->input('classification_rationale'),
            'classification_next_review_at' => $request->input('classification_next_review_at') ?: null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productPayload(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'product_line' => $product->product_line,
            'description' => $product->description,
            'intended_purpose' => $product->intended_purpose,
            'product_type' => $product->product_type->value,
            'manufacturer' => $product->manufacturer,
            'trademark' => $product->trademark,
            'licensing_model' => $product->licensing_model->value,
            'has_remote_data_processing' => $product->has_remote_data_processing,
            'has_network_connectivity' => $product->has_network_connectivity,
            'deployment_model' => $product->deployment_model,
            'support_period_notes' => $product->support_period_notes,
            'end_of_support_policy' => $product->end_of_support_policy,
            'product_owner_user_id' => $product->product_owner_user_id,
            'security_contact_user_id' => $product->security_contact_user_id,
            'scope_status' => $product->scope_status->value,
            'scope_rationale' => $product->scope_rationale,
            'scope_reviewed_at' => $product->scope_reviewed_at?->toIso8601String(),
            'classification_status' => $product->classification_status->value,
            'classification_rationale' => $product->classification_rationale,
            'classification_reviewed_at' => $product->classification_reviewed_at?->toIso8601String(),
            'classification_next_review_at' => $product->classification_next_review_at?->toDateString(),
        ];
    }
}
