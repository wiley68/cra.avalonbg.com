<?php

namespace App\Http\Controllers;

use App\Enums\UserSecurityInstructionSectionKey;
use App\Enums\UserSecurityInstructionStatus;
use App\Http\Requests\StoreUserSecurityInstructionRequest;
use App\Http\Requests\UpdateUserSecurityInstructionRequest;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductDeployment;
use App\Models\UserSecurityInstruction;
use App\Services\AiAssistantService;
use App\Services\UserSecurityInstructionExportService;
use App\Services\UserSecurityInstructionService;
use App\Support\Translations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class UserSecurityInstructionController extends Controller
{
    public function __construct(
        private readonly UserSecurityInstructionService $instructions,
        private readonly UserSecurityInstructionExportService $exports,
        private readonly AiAssistantService $assistant,
    ) {
    }

    public function index(Product $product): InertiaResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('viewAny', [UserSecurityInstruction::class, $organization]);
        $this->authorize('view', [$product, $organization]);

        return Inertia::render('products/user-security-instructions/Index', [
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
        $this->authorize('create', [UserSecurityInstruction::class, $organization]);

        return Inertia::render('products/user-security-instructions/Create', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productPayload($product),
            'versions' => $this->versionOptions($product),
            'options' => $this->enumOptions($organization),
        ]);
    }

    public function template(Request $request, Product $product): JsonResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('create', [UserSecurityInstruction::class, $organization]);

        $validated = $request->validate([
            'locale' => ['nullable', 'string', Rule::in(Organization::LOCALES)],
        ]);

        $locale = $validated['locale'] ?? $organization->resolvedLocale();

        return response()->json($this->instructions->templatePayload($locale));
    }

    public function store(StoreUserSecurityInstructionRequest $request, Product $product): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);

        $instruction = $this->instructions->create(
            $product,
            [
                'title' => (string) ($request->input('title') ?? ''),
                'version_label' => (string) ($request->input('version_label') ?? ''),
                'locale' => $request->string('locale')->toString(),
                'notes' => $request->input('notes'),
                'use_template' => $request->boolean('use_template'),
                'product_version_id' => $request->input('product_version_id') !== null
                    ? (int) $request->input('product_version_id')
                    : null,
            ],
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.user_security_instructions.created'),
        ]);

        return redirect()->route('products.security-instructions.edit', [$product, $instruction]);
    }

    public function edit(Product $product, UserSecurityInstruction $instruction): InertiaResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertInstructionBelongsToProduct($instruction, $product);
        $this->authorize('view', [$instruction, $organization]);

        return Inertia::render('products/user-security-instructions/Edit', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productPayload($product),
            'instruction' => $this->instructions->detailPayload($instruction),
            'versions' => $this->versionOptions($product),
            'customers' => $this->customerOptions($organization),
            'deployments' => $this->deploymentOptions($product),
            'options' => $this->enumOptions($organization),
            'canManage' => request()->user()->canManageProducts($organization),
            'aiEnabled' => $this->assistant->isEnabled(),
            'memberOptions' => $this->memberOptions($organization),
            'reviewTask' => $this->instructions->openReviewTaskPayload($instruction),
        ]);
    }

    public function suggestAiDraft(
        Request $request,
        Product $product,
        UserSecurityInstruction $instruction,
    ): JsonResponse {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertInstructionBelongsToProduct($instruction, $product);
        $this->authorize('update', [$instruction, $organization]);

        $validated = $request->validate([
            'section_key' => ['required', 'string', Rule::enum(UserSecurityInstructionSectionKey::class)],
            'current_body' => ['nullable', 'string', 'max:50000'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $sectionKey = UserSecurityInstructionSectionKey::from($validated['section_key']);

        $result = $this->assistant->suggestUsiSectionDraft(
            $product,
            $instruction,
            $request->user(),
            $sectionKey,
            $validated['current_body'] ?? null,
            $validated['note'] ?? null,
        );

        return response()->json([
            'section_key' => $result['draft']['section_key'],
            'body_markdown' => $result['draft']['body_markdown'],
            'human_review_required' => true,
            'disclaimer' => $result['draft']['disclaimer'],
            'provider' => $result['provider'],
            'model' => $result['model'],
        ]);
    }

    public function update(
        UpdateUserSecurityInstructionRequest $request,
        Product $product,
        UserSecurityInstruction $instruction,
    ): RedirectResponse {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertInstructionBelongsToProduct($instruction, $product);

        $this->instructions->update(
            $instruction,
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
            'message' => Translations::get('products.user_security_instructions.updated'),
        ]);

        return redirect()->route('products.security-instructions.edit', [$product, $instruction]);
    }

    public function destroy(Product $product, UserSecurityInstruction $instruction): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertInstructionBelongsToProduct($instruction, $product);
        $this->authorize('delete', [$instruction, $organization]);

        $this->instructions->delete($instruction, request()->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.user_security_instructions.deleted'),
        ]);

        return redirect()->route('products.security-instructions.index', $product);
    }

    public function submitReview(
        Request $request,
        Product $product,
        UserSecurityInstruction $instruction,
    ): RedirectResponse {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertInstructionBelongsToProduct($instruction, $product);
        $this->authorize('update', [$instruction, $organization]);

        $validated = $request->validate([
            'assignee_user_id' => [
                'nullable',
                'integer',
                Rule::exists('organization_user', 'user_id')->where(
                    fn($query) => $query->where('organization_id', $organization->id),
                ),
            ],
        ]);

        $this->instructions->submitForReview(
            $instruction,
            $request->user(),
            isset($validated['assignee_user_id'])
            ? (int) $validated['assignee_user_id']
            : null,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.user_security_instructions.submitted'),
        ]);

        return redirect()->route('products.security-instructions.edit', [$product, $instruction]);
    }

    public function publish(Product $product, UserSecurityInstruction $instruction): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertInstructionBelongsToProduct($instruction, $product);
        $this->authorize('update', [$instruction, $organization]);

        $this->instructions->publish($instruction, request()->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.user_security_instructions.published'),
        ]);

        return redirect()->route('products.security-instructions.edit', [$product, $instruction]);
    }

    public function publishEvidence(Product $product, UserSecurityInstruction $instruction): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertInstructionBelongsToProduct($instruction, $product);
        $this->authorize('update', [$instruction, $organization]);

        $this->instructions->publishEvidence($instruction, $product, request()->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.user_security_instructions.published_evidence'),
        ]);

        return redirect()->route('products.security-instructions.edit', [$product, $instruction]);
    }

    public function retire(Product $product, UserSecurityInstruction $instruction): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertInstructionBelongsToProduct($instruction, $product);
        $this->authorize('update', [$instruction, $organization]);

        $this->instructions->retire($instruction, request()->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.user_security_instructions.retired'),
        ]);

        return redirect()->route('products.security-instructions.edit', [$product, $instruction]);
    }

    public function export(
        Request $request,
        Product $product,
        UserSecurityInstruction $instruction,
        string $format,
    ): Response|BinaryFileResponse|SymfonyResponse {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertInstructionBelongsToProduct($instruction, $product);
        $this->authorize('export', [$instruction, $organization]);

        $validated = $request->validate([
            'customer_id' => [
                'nullable',
                'integer',
                Rule::exists('customers', 'id')->where(
                    fn($query) => $query->where('organization_id', $organization->id),
                ),
            ],
            'deployment_id' => [
                'nullable',
                'integer',
                Rule::exists('product_deployments', 'id')->where(
                    fn($query) => $query
                        ->where('organization_id', $organization->id)
                        ->where('product_id', $product->id),
                ),
            ],
        ]);

        $customer = null;
        $deployment = null;

        if (!empty($validated['deployment_id'])) {
            $deployment = ProductDeployment::query()
                ->with(['customer', 'productVersion:id,version_number'])
                ->findOrFail((int) $validated['deployment_id']);

            if (
                isset($validated['customer_id'])
                && (int) $validated['customer_id'] !== $deployment->customer_id
            ) {
                throw ValidationException::withMessages([
                    'deployment_id' => [
                        Translations::get('products.user_security_instructions.export.customer_deployment_mismatch'),
                    ],
                ]);
            }

            $customer = $deployment->customer;
        } elseif (!empty($validated['customer_id'])) {
            $customer = Customer::query()->findOrFail((int) $validated['customer_id']);
        }

        return $this->exports->export(
            $instruction,
            $product,
            $organization,
            $format,
            $request->user(),
            $customer,
            $deployment,
        );
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
                fn(UserSecurityInstructionStatus $status) => $status->value,
                UserSecurityInstructionStatus::cases(),
            ),
            'section_keys' => array_map(
                fn(UserSecurityInstructionSectionKey $key) => $key->value,
                UserSecurityInstructionSectionKey::ordered(),
            ),
            'default_locale' => $organization->resolvedLocale(),
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
     * @return list<array{id: int, name: string, is_active: bool}>
     */
    private function customerOptions(Organization $organization): array
    {
        return Customer::query()
            ->where('organization_id', $organization->id)
            ->orderBy('name')
            ->get(['id', 'name', 'is_active'])
            ->map(fn(Customer $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'is_active' => $customer->is_active,
            ])
            ->all();
    }

    /**
     * @return list<array{
     *     id: int,
     *     customer_id: int,
     *     environment: string,
     *     product_version_number: string|null,
     *     notes: string|null
     * }>
     */
    private function deploymentOptions(Product $product): array
    {
        return ProductDeployment::query()
            ->where('product_id', $product->id)
            ->with('productVersion:id,version_number')
            ->orderByDesc('id')
            ->get()
            ->map(fn(ProductDeployment $deployment) => [
                'id' => $deployment->id,
                'customer_id' => $deployment->customer_id,
                'environment' => $deployment->environment->value,
                'product_version_number' => $deployment->productVersion?->version_number,
                'notes' => $deployment->notes,
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

    private function assertInstructionBelongsToProduct(
        UserSecurityInstruction $instruction,
        Product $product,
    ): void {
        if ($instruction->product_id !== $product->id) {
            abort(404);
        }
    }
}
