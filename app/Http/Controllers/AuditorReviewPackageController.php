<?php

namespace App\Http\Controllers;

use App\Enums\AuditorFindingSeverity;
use App\Enums\AuditorFindingStatus;
use App\Enums\AuditorReviewPackageStatus;
use App\Enums\RoleSlug;
use App\Http\Requests\StoreAuditorReviewPackageRequest;
use App\Http\Requests\UpdateAuditorReviewPackageRequest;
use App\Models\AuditorFinding;
use App\Models\AuditorReviewPackage;
use App\Models\Evidence;
use App\Models\Organization;
use App\Models\Product;
use App\Services\AuditorFindingService;
use App\Services\AuditorReviewPackageExportService;
use App\Services\AuditorReviewPackageService;
use App\Services\ProductReadinessService;
use App\Support\Translations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AuditorReviewPackageController extends Controller
{
    public function __construct(
        private readonly AuditorReviewPackageService $packages,
        private readonly AuditorFindingService $findings,
        private readonly ProductReadinessService $readiness,
        private readonly AuditorReviewPackageExportService $exports,
    ) {
    }

    public function index(Request $request): Response
    {
        $organization = $this->currentOrganization();
        $this->authorize('viewAny', [AuditorReviewPackage::class, $organization]);

        $status = null;
        if ($request->filled('status')) {
            $validated = $request->validate([
                'status' => ['required', Rule::enum(AuditorReviewPackageStatus::class)],
            ]);
            $status = AuditorReviewPackageStatus::from($validated['status'])->value;
        }

        return Inertia::render('auditor/Index', [
            'organization' => $this->organizationPayload($organization),
            'canManage' => request()->user()->canManageProducts($organization),
            'filters' => [
                'status' => $status,
            ],
        ]);
    }

    public function create(): Response
    {
        $organization = $this->currentOrganization();
        $this->authorize('create', [AuditorReviewPackage::class, $organization]);

        return Inertia::render('auditor/Create', [
            'organization' => $this->organizationPayload($organization),
            'products' => $this->productOptions($organization),
        ]);
    }

    public function store(StoreAuditorReviewPackageRequest $request): RedirectResponse
    {
        $organization = $this->currentOrganization();

        $package = $this->packages->create(
            $organization,
            [
                'product_id' => $request->integer('product_id'),
                'title' => $request->string('title')->toString(),
                'notes' => $request->input('notes'),
                'evidence_ids' => $request->input('evidence_ids', []),
            ],
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('auditor.created'),
        ]);

        return redirect()->route('auditor.packages.edit', $package);
    }

    public function show(AuditorReviewPackage $package): Response
    {
        $organization = $this->currentOrganization();
        $this->assertPackageInOrganization($package, $organization);
        $this->authorize('view', [$package, $organization]);

        $package->loadMissing([
            'product',
            'creator:id,name',
            'evidence:id,title,type,freshness_status,confidentiality',
            'findings' => fn($query) => $query
                ->with('creator:id,name')
                ->orderByDesc('id'),
        ]);

        $product = $package->product;
        $product->load([
            'productOwner:id,name,email',
            'securityContact:id,name,email',
            'versions' => fn($query) => $query
                ->orderByDesc('release_date')
                ->orderByDesc('id')
                ->limit(5),
            'supportPeriods' => fn($query) => $query
                ->orderBy('type')
                ->orderByDesc('id'),
        ]);

        $user = request()->user();

        return Inertia::render('auditor/Show', [
            'organization' => $this->organizationPayload($organization),
            'package' => $this->detailPayload($package),
            'product' => $this->passportProductPayload($product),
            'report' => $this->readiness->build($product),
            'findings' => $package->findings
                ->map(fn(AuditorFinding $finding) => $this->findings->payload($finding))
                ->values()
                ->all(),
            'findingOptions' => [
                'severities' => array_map(
                    fn(AuditorFindingSeverity $severity) => $severity->value,
                    AuditorFindingSeverity::cases(),
                ),
                'statuses' => array_map(
                    fn(AuditorFindingStatus $status) => $status->value,
                    AuditorFindingStatus::cases(),
                ),
            ],
            'canManage' => $user->canManageProducts($organization),
            'canCreateFindings' => $user->can('create', [AuditorFinding::class, $package, $organization]),
            'canManageFindingContent' => $user->hasRole(RoleSlug::Auditor, $organization)
                && $package->status === AuditorReviewPackageStatus::Shared,
            'canManageRemediation' => $user->canManageProducts($organization)
                && $package->status !== AuditorReviewPackageStatus::Draft,
        ]);
    }

    public function edit(AuditorReviewPackage $package): Response
    {
        $organization = $this->currentOrganization();
        $this->assertPackageInOrganization($package, $organization);
        $this->authorize('view', [$package, $organization]);

        $package->loadMissing([
            'product:id,name,slug',
            'creator:id,name',
            'evidence:id,title,type',
        ]);

        return Inertia::render('auditor/Edit', [
            'organization' => $this->organizationPayload($organization),
            'package' => $this->detailPayload($package),
            'evidenceOptions' => $this->evidenceOptions($package->product),
            'canManage' => request()->user()->canManageProducts($organization),
        ]);
    }

    public function update(
        UpdateAuditorReviewPackageRequest $request,
        AuditorReviewPackage $package,
    ): RedirectResponse {
        $organization = $this->currentOrganization();
        $this->assertPackageInOrganization($package, $organization);

        $this->packages->update(
            $package,
            [
                'title' => $request->string('title')->toString(),
                'notes' => $request->input('notes'),
                'evidence_ids' => $request->input('evidence_ids', []),
            ],
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('auditor.updated'),
        ]);

        return redirect()->route('auditor.packages.edit', $package);
    }

    public function destroy(AuditorReviewPackage $package): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertPackageInOrganization($package, $organization);
        $this->authorize('delete', [$package, $organization]);

        $this->packages->delete($package, request()->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('auditor.deleted'),
        ]);

        return redirect()->route('auditor.index');
    }

    public function share(AuditorReviewPackage $package): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertPackageInOrganization($package, $organization);
        $this->authorize('share', [$package, $organization]);

        $this->packages->share($package, request()->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('auditor.shared'),
        ]);

        return redirect()->route('auditor.packages.edit', $package);
    }

    public function close(AuditorReviewPackage $package): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertPackageInOrganization($package, $organization);
        $this->authorize('close', [$package, $organization]);

        $this->packages->close($package, request()->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('auditor.closed'),
        ]);

        return redirect()->route('auditor.packages.edit', $package);
    }

    public function export(AuditorReviewPackage $package): BinaryFileResponse
    {
        $organization = $this->currentOrganization();
        $this->assertPackageInOrganization($package, $organization);
        $this->authorize('view', [$package, $organization]);

        return $this->exports->downloadZip($package, $organization, request()->user());
    }

    private function currentOrganization(): Organization
    {
        $organization = request()->user()?->currentOrganization();

        if ($organization === null) {
            abort(403, 'No organization membership.');
        }

        return $organization;
    }

    private function assertPackageInOrganization(
        AuditorReviewPackage $package,
        Organization $organization,
    ): void {
        if ($package->organization_id !== $organization->id) {
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
     * @return list<array{id: int, name: string, evidence: list<array{id: int, title: string, type: string}>}>
     */
    private function productOptions(Organization $organization): array
    {
        return Product::query()
            ->where('organization_id', $organization->id)
            ->orderBy('name')
            ->with(['evidence' => fn($q) => $q->orderBy('title')->select(['id', 'product_id', 'title', 'type'])])
            ->get(['id', 'name'])
            ->map(fn(Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'evidence' => $product->evidence
                    ->map(fn(Evidence $evidence) => [
                        'id' => $evidence->id,
                        'title' => $evidence->title,
                        'type' => $evidence->type->value,
                    ])
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, title: string, type: string}>
     */
    private function evidenceOptions(Product $product): array
    {
        return Evidence::query()
            ->where('product_id', $product->id)
            ->orderBy('title')
            ->get(['id', 'title', 'type'])
            ->map(fn(Evidence $evidence) => [
                'id' => $evidence->id,
                'title' => $evidence->title,
                'type' => $evidence->type->value,
            ])
            ->all();
    }

    /**
     * @return array{
     *     id: int,
     *     title: string,
     *     status: string,
     *     notes: string|null,
     *     product_id: int,
     *     product_name: string,
     *     product_slug: string,
     *     shared_at: string|null,
     *     closed_at: string|null,
     *     created_by_name: string|null,
     *     evidence_ids: list<int>,
     *     evidence: list<array{id: int, title: string, type: string, freshness_status?: string|null, confidentiality?: string|null}>,
     *     is_editable: bool
     * }
     */
    private function detailPayload(AuditorReviewPackage $package): array
    {
        return [
            'id' => $package->id,
            'title' => $package->title,
            'status' => $package->status->value,
            'notes' => $package->notes,
            'product_id' => $package->product_id,
            'product_name' => $package->product?->name ?? '',
            'product_slug' => $package->product?->slug ?? '',
            'shared_at' => $package->shared_at?->toIso8601String(),
            'closed_at' => $package->closed_at?->toIso8601String(),
            'created_by_name' => $package->creator?->name,
            'evidence_ids' => $package->evidence->pluck('id')->map(fn($id) => (int) $id)->all(),
            'evidence' => $package->evidence
                ->map(fn(Evidence $evidence) => [
                    'id' => $evidence->id,
                    'title' => $evidence->title,
                    'type' => $evidence->type->value,
                    'freshness_status' => $evidence->freshness_status?->value,
                    'confidentiality' => $evidence->confidentiality?->value,
                ])
                ->values()
                ->all(),
            'is_editable' => $package->isEditable(),
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     slug: string,
     *     manufacturer: string|null,
     *     trademark: string|null,
     *     product_type: string|null,
     *     licensing_model: string|null,
     *     scope_status: string|null,
     *     classification_status: string|null,
     *     intended_purpose: string|null,
     *     product_owner: array{id: int, name: string, email: string}|null,
     *     security_contact: array{id: int, name: string, email: string}|null,
     *     versions: list<array{id: int, version_number: string, state: string|null, support_status: string|null, release_date: string|null}>,
     *     support_periods: list<array{id: int, type: string, start_basis: string, duration_months: int, effective_starts_at: string|null, effective_ends_at: string|null, schedule_resolved: bool, basis: string|null, is_extended: bool}>
     * }
     */
    private function passportProductPayload(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'manufacturer' => $product->manufacturer,
            'trademark' => $product->trademark,
            'product_type' => $product->product_type?->value,
            'licensing_model' => $product->licensing_model?->value,
            'scope_status' => $product->scope_status?->value,
            'classification_status' => $product->classification_status?->value,
            'intended_purpose' => $product->intended_purpose,
            'product_owner' => $product->productOwner
                ? [
                    'id' => $product->productOwner->id,
                    'name' => $product->productOwner->name,
                    'email' => $product->productOwner->email,
                ]
                : null,
            'security_contact' => $product->securityContact
                ? [
                    'id' => $product->securityContact->id,
                    'name' => $product->securityContact->name,
                    'email' => $product->securityContact->email,
                ]
                : null,
            'versions' => $product->versions->map(fn($version) => [
                'id' => $version->id,
                'version_number' => $version->version_number,
                'state' => $version->state?->value,
                'support_status' => $version->support_status?->value,
                'release_date' => $version->release_date?->toDateString(),
            ])->values()->all(),
            'support_periods' => $product->supportPeriods->map(fn($period) => [
                'id' => $period->id,
                'type' => $period->type->value,
                'start_basis' => $period->start_basis->value,
                'duration_months' => $period->duration_months,
                'effective_starts_at' => $period->effectiveStartsAt()?->toDateString(),
                'effective_ends_at' => $period->effectiveEndsAt()?->toDateString(),
                'schedule_resolved' => $period->scheduleResolved(),
                'basis' => $period->basis,
                'is_extended' => $period->is_extended,
            ])->values()->all(),
        ];
    }
}
