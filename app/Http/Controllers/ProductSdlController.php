<?php

namespace App\Http\Controllers;

use App\Enums\SdlRunStatus;
use App\Enums\SdlStage;
use App\Enums\SdlStageStatus;
use App\Http\Requests\LinkSdlExternalEvidenceRequest;
use App\Http\Requests\StoreSdlRunRequest;
use App\Http\Requests\UpdateSdlRunRequest;
use App\Http\Requests\UpdateSdlStageRequest;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVersion;
use App\Models\Evidence;
use App\Models\SdlRun;
use App\Models\User;
use App\Enums\EvidenceType;
use App\Services\AiAssistantService;
use App\Services\ProductRepositoryService;
use App\Services\ProductSdlExportService;
use App\Services\ProductSdlService;
use App\Support\SdlStageNoteTemplates;
use App\Support\Translations;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProductSdlController extends Controller
{
    public function __construct(
        private readonly ProductSdlService $sdl,
        private readonly ProductRepositoryService $repositories,
        private readonly AiAssistantService $assistant,
        private readonly ProductSdlExportService $exports,
    ) {
    }

    public function index(Product $product): InertiaResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('viewAny', [SdlRun::class, $organization]);
        $this->authorize('view', [$product, $organization]);

        return Inertia::render('products/sdl/Index', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productPayload($product),
            'versions' => $this->versionOptions($product),
            'canManage' => request()->user()->canManageSdl($organization),
            'options' => $this->enumOptions(),
        ]);
    }

    public function create(Product $product): InertiaResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('create', [SdlRun::class, $organization]);

        return Inertia::render('products/sdl/Create', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productPayload($product),
            'members' => $this->memberOptions($organization),
            'versions' => $this->versionOptions($product),
            'evidence' => $this->evidenceOptions($product),
            'repository' => $this->repositories->payload($product->repository),
            'git_evidence' => $this->sdl->gitEvidenceOptions($product),
            'git_suggestions' => $this->sdl->gitSyncSuggestions($product),
            'options' => [
                ...$this->enumOptions(),
                'locales' => Organization::LOCALES,
                'default_locale' => $organization->resolvedLocale(),
                'template_stages' => array_map(
                    fn(SdlStage $stage) => $stage->value,
                    SdlStageNoteTemplates::templatedStages(),
                ),
            ],
        ]);
    }

    public function store(StoreSdlRunRequest $request, Product $product): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);

        $run = $this->sdl->create(
            $product,
            $this->validatedAttributes($request),
            array_map('intval', $request->input('evidence_ids', [])),
            $request->user(),
            $request->boolean('use_template'),
            (string) $request->input('locale', $organization->resolvedLocale()),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.sdl.created'),
        ]);

        return redirect()->route('products.sdl.edit', [$product, $run]);
    }

    public function stageTemplates(Request $request, Product $product): JsonResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('viewAny', [SdlRun::class, $organization]);
        $this->authorize('view', [$product, $organization]);

        $locale = SdlStageNoteTemplates::normalizeLocale(
            (string) $request->query('locale', $organization->resolvedLocale()),
        );

        return response()->json([
            'locale' => $locale,
            'stages' => SdlStageNoteTemplates::payload($locale),
        ]);
    }

    public function edit(Product $product, SdlRun $sdlRun): InertiaResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertRunBelongsToProduct($sdlRun, $product);
        $this->authorize('view', [$sdlRun, $organization]);

        $sdlRun->load(['owner', 'version', 'approver', 'evidence', 'stageEntries.completer', 'stageEntries.evidence']);

        return Inertia::render('products/sdl/Edit', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productPayload($product),
            'run' => $this->sdl->detailPayload($sdlRun),
            'members' => $this->memberOptions($organization),
            'versions' => $this->versionOptions($product),
            'evidence' => $this->evidenceOptions($product),
            'repository' => $this->repositories->payload($product->repository),
            'git_evidence' => $this->sdl->gitEvidenceOptions($product),
            'git_suggestions' => $this->sdl->gitSyncSuggestions($product, $sdlRun),
            'options' => $this->enumOptions(),
            'stage_note_templates' => SdlStageNoteTemplates::payload(
                $organization->resolvedLocale(),
            ),
            'template_locale' => $organization->resolvedLocale(),
            'canManage' => request()->user()->canManageSdl($organization),
            'aiEnabled' => $this->assistant->isEnabled(),
        ]);
    }

    public function suggestAiDraft(
        Request $request,
        Product $product,
        SdlRun $sdlRun,
    ): JsonResponse {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertRunBelongsToProduct($sdlRun, $product);
        $this->authorize('update', [$sdlRun, $organization]);

        $validated = $request->validate([
            'stage' => ['required', 'string', Rule::enum(SdlStage::class)],
            'current_notes' => ['nullable', 'string', 'max:50000'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $stage = SdlStage::from($validated['stage']);

        $result = $this->assistant->suggestSdlStageNotesDraft(
            $product,
            $sdlRun,
            $request->user(),
            $stage,
            $validated['current_notes'] ?? null,
            $validated['note'] ?? null,
            $organization->resolvedLocale(),
        );

        return response()->json([
            'notes_markdown' => $result['draft']['notes_markdown'],
            'human_review_required' => true,
            'disclaimer' => $result['draft']['disclaimer'],
            'provider' => $result['provider'],
            'model' => $result['model'],
            'stage' => $stage->value,
        ]);
    }

    public function export(
        Product $product,
        SdlRun $sdlRun,
        string $format,
    ): Response {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertRunBelongsToProduct($sdlRun, $product);
        $this->authorize('view', [$sdlRun, $organization]);

        return $this->exports->export(
            $sdlRun,
            $product,
            $organization,
            $format,
            request()->user(),
        );
    }

    public function update(
        UpdateSdlRunRequest $request,
        Product $product,
        SdlRun $sdlRun,
    ): RedirectResponse {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertRunBelongsToProduct($sdlRun, $product);

        $this->sdl->update(
            $sdlRun,
            $this->validatedAttributes($request),
            array_map('intval', $request->input('evidence_ids', [])),
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.sdl.updated'),
        ]);

        return redirect()->route('products.sdl.edit', [$product, $sdlRun]);
    }

    public function destroy(Product $product, SdlRun $sdlRun): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertRunBelongsToProduct($sdlRun, $product);
        $this->authorize('delete', [$sdlRun, $organization]);

        $this->sdl->delete($sdlRun, request()->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.sdl.deleted'),
        ]);

        return redirect()->route('products.sdl.index', $product);
    }

    public function updateStage(
        UpdateSdlStageRequest $request,
        Product $product,
        SdlRun $sdlRun,
        string $stage,
    ): RedirectResponse {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertRunBelongsToProduct($sdlRun, $product);

        $stageEnum = SdlStage::tryFrom($stage);

        if ($stageEnum === null) {
            abort(404);
        }

        $this->sdl->updateStage(
            $sdlRun,
            $stageEnum,
            [
                'status' => SdlStageStatus::from($request->string('status')->toString()),
                'notes' => $request->input('notes'),
                'evidence_ids' => array_map('intval', $request->input('evidence_ids', [])),
                'exception_owner_user_id' => $request->input('exception_owner_user_id'),
                'exception_expires_at' => $request->input('exception_expires_at'),
            ],
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.sdl.stage_updated'),
        ]);

        return redirect()->route('products.sdl.edit', [$product, $sdlRun]);
    }

    public function approve(Product $product, SdlRun $sdlRun): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertRunBelongsToProduct($sdlRun, $product);
        $this->authorize('update', [$sdlRun, $organization]);

        $this->sdl->approve($sdlRun, request()->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.sdl.approved'),
        ]);

        return redirect()->route('products.sdl.edit', [$product, $sdlRun]);
    }

    public function revokeApproval(Product $product, SdlRun $sdlRun): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertRunBelongsToProduct($sdlRun, $product);
        $this->authorize('update', [$sdlRun, $organization]);

        $this->sdl->revokeApproval($sdlRun, request()->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.sdl.approval_revoked'),
        ]);

        return redirect()->route('products.sdl.edit', [$product, $sdlRun]);
    }

    public function linkExternalEvidence(
        LinkSdlExternalEvidenceRequest $request,
        Product $product,
        SdlRun $sdlRun,
    ): RedirectResponse {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertRunBelongsToProduct($sdlRun, $product);

        $stage = $request->filled('stage')
            ? SdlStage::from((string) $request->input('stage'))
            : null;

        $this->sdl->linkExternalEvidence(
            $sdlRun,
            (string) $request->input('url'),
            $request->input('title'),
            $stage,
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.sdl.git_link_created'),
        ]);

        return redirect()->route('products.sdl.edit', [$product, $sdlRun]);
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

    private function assertRunBelongsToProduct(SdlRun $run, Product $product): void
    {
        if ($run->product_id !== $product->id) {
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
            ->map(fn(User $user) => [
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
     * @return list<array{
     *     id: int,
     *     title: string,
     *     type: string,
     *     source: string|null,
     *     collected_at: string|null
     * }>
     */
    private function evidenceOptions(Product $product): array
    {
        return Evidence::query()
            ->where('product_id', $product->id)
            ->where('organization_id', $product->organization_id)
            ->orderBy('title')
            ->get(['id', 'title', 'type', 'source', 'collected_at'])
            ->map(fn(Evidence $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'type' => $item->type instanceof EvidenceType
                    ? $item->type->value
                    : (string) $item->type,
                'source' => $item->source,
                'collected_at' => $item->collected_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array{statuses: list<string>, stages: list<string>, stage_statuses: list<string>}
     */
    private function enumOptions(): array
    {
        return [
            'statuses' => array_column(SdlRunStatus::cases(), 'value'),
            'stages' => array_map(
                fn(SdlStage $stage) => $stage->value,
                SdlStage::ordered(),
            ),
            'stage_statuses' => array_column(SdlStageStatus::cases(), 'value'),
        ];
    }

    /**
     * @return array{
     *     title: string,
     *     status: SdlRunStatus,
     *     current_stage: SdlStage|null,
     *     product_version_id: int|null,
     *     owner_user_id: int|null,
     *     notes: string|null
     * }
     */
    private function validatedAttributes(StoreSdlRunRequest|UpdateSdlRunRequest $request): array
    {
        $validated = $request->validated();

        return [
            'title' => $validated['title'],
            'status' => SdlRunStatus::from($validated['status']),
            'current_stage' => isset($validated['current_stage'])
                ? SdlStage::from($validated['current_stage'])
                : SdlStage::first(),
            'product_version_id' => isset($validated['product_version_id'])
                ? (int) $validated['product_version_id']
                : null,
            'owner_user_id' => isset($validated['owner_user_id'])
                ? (int) $validated['owner_user_id']
                : null,
            'notes' => $validated['notes'] ?? null,
        ];
    }
}
