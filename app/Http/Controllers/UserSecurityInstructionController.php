<?php

namespace App\Http\Controllers;

use App\Enums\UserSecurityInstructionSectionKey;
use App\Enums\UserSecurityInstructionStatus;
use App\Http\Requests\StoreUserSecurityInstructionRequest;
use App\Http\Requests\UpdateUserSecurityInstructionRequest;
use App\Models\Organization;
use App\Models\Product;
use App\Models\UserSecurityInstruction;
use App\Services\UserSecurityInstructionService;
use App\Support\Translations;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserSecurityInstructionController extends Controller
{
    public function __construct(
        private readonly UserSecurityInstructionService $instructions,
    ) {
    }

    public function index(Product $product): Response
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('viewAny', [UserSecurityInstruction::class, $organization]);
        $this->authorize('view', [$product, $organization]);

        return Inertia::render('products/user-security-instructions/Index', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productPayload($product),
            'canManage' => request()->user()->canManageProducts($organization),
        ]);
    }

    public function create(Product $product): Response
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('create', [UserSecurityInstruction::class, $organization]);

        return Inertia::render('products/user-security-instructions/Create', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productPayload($product),
            'options' => $this->enumOptions($organization),
        ]);
    }

    public function store(StoreUserSecurityInstructionRequest $request, Product $product): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);

        $instruction = $this->instructions->create(
            $product,
            [
                'title' => $request->string('title')->toString(),
                'version_label' => $request->string('version_label')->toString(),
                'locale' => $request->string('locale')->toString(),
                'notes' => $request->input('notes'),
            ],
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.user_security_instructions.created'),
        ]);

        return redirect()->route('products.security-instructions.edit', [$product, $instruction]);
    }

    public function edit(Product $product, UserSecurityInstruction $instruction): Response
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertInstructionBelongsToProduct($instruction, $product);
        $this->authorize('view', [$instruction, $organization]);

        return Inertia::render('products/user-security-instructions/Edit', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productPayload($product),
            'instruction' => $this->instructions->detailPayload($instruction),
            'options' => $this->enumOptions($organization),
            'canManage' => request()->user()->canManageProducts($organization),
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
