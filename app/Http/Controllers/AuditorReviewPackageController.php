<?php

namespace App\Http\Controllers;

use App\Enums\AuditorReviewPackageStatus;
use App\Http\Requests\StoreAuditorReviewPackageRequest;
use App\Http\Requests\UpdateAuditorReviewPackageRequest;
use App\Models\AuditorReviewPackage;
use App\Models\Evidence;
use App\Models\Organization;
use App\Models\Product;
use App\Services\AuditorReviewPackageService;
use App\Support\Translations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AuditorReviewPackageController extends Controller
{
    public function __construct(
        private readonly AuditorReviewPackageService $packages,
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
     *     evidence: list<array{id: int, title: string, type: string}>,
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
                ])
                ->values()
                ->all(),
            'is_editable' => $package->isEditable(),
        ];
    }
}
