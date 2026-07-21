<?php

namespace App\Http\Controllers;

use App\Enums\PolicyStatus;
use App\Enums\PolicyType;
use App\Http\Requests\StoreOrgPolicyRequest;
use App\Http\Requests\UpdateOrgPolicyRequest;
use App\Models\Organization;
use App\Models\OrgPolicy;
use App\Models\Product;
use App\Services\OrgPolicyService;
use App\Support\AuditLogger;
use App\Support\Translations;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrgPolicyController extends Controller
{
    public function __construct(
        private readonly OrgPolicyService $policies,
    ) {
    }

    public function index(Request $request): Response
    {
        $organization = $this->currentOrganization();
        $this->authorize('viewAny', [OrgPolicy::class, $organization]);

        $policyType = null;
        if ($request->filled('policy_type')) {
            $validated = $request->validate([
                'policy_type' => ['required', Rule::enum(PolicyType::class)],
            ]);
            $policyType = PolicyType::from($validated['policy_type'])->value;
        }

        return Inertia::render('policies/Index', [
            'organization' => $this->organizationPayload($organization),
            'canManage' => request()->user()->canManageProducts($organization),
            'filters' => [
                'policy_type' => $policyType,
            ],
        ]);
    }

    public function create(): Response
    {
        $organization = $this->currentOrganization();
        $this->authorize('create', [OrgPolicy::class, $organization]);

        return Inertia::render('policies/Create', [
            'organization' => $this->organizationPayload($organization),
            'options' => $this->enumOptions(),
            'supersedeOptions' => $this->supersedeOptions($organization),
        ]);
    }

    public function store(StoreOrgPolicyRequest $request): RedirectResponse
    {
        $organization = $this->currentOrganization();

        $org_policy = $this->policies->create(
            $organization,
            [
                'policy_type' => PolicyType::from($request->string('policy_type')->toString()),
                'title' => (string) ($request->input('title') ?? ''),
                'version_label' => (string) ($request->input('version_label') ?? ''),
                'body' => (string) ($request->input('body') ?? ''),
                'notes' => $request->input('notes'),
                'supersedes_id' => $request->filled('supersedes_id')
                    ? $request->integer('supersedes_id')
                    : null,
                'use_template' => $request->boolean('use_template'),
            ],
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('policies.created'),
        ]);

        return redirect()->route('policies.edit', $org_policy);
    }

    public function edit(OrgPolicy $org_policy): Response
    {
        $organization = $this->currentOrganization();
        $this->assertPolicyInOrganization($org_policy, $organization);
        $this->authorize('view', [$org_policy, $organization]);

        $org_policy->loadMissing([
            'approver:id,name',
            'supersedes:id,title,version_label,status,body',
            'evidence:id,product_id,title',
        ]);

        return Inertia::render('policies/Edit', [
            'organization' => $this->organizationPayload($organization),
            'policy' => $this->detailPayload($org_policy),
            'options' => $this->enumOptions(),
            'productOptions' => $this->productOptions($organization),
            'memberOptions' => $this->memberOptions($organization),
            'reviewTask' => $this->policies->openReviewTaskPayload($org_policy),
            'canManage' => request()->user()->canManageProducts($organization),
        ]);
    }

    public function update(UpdateOrgPolicyRequest $request, OrgPolicy $org_policy): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertPolicyInOrganization($org_policy, $organization);

        $this->policies->update(
            $org_policy,
            [
                'title' => $request->string('title')->toString(),
                'version_label' => $request->string('version_label')->toString(),
                'body' => $request->string('body')->toString(),
                'notes' => $request->input('notes'),
            ],
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('policies.updated'),
        ]);

        return redirect()->route('policies.edit', $org_policy);
    }

    public function destroy(OrgPolicy $org_policy): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertPolicyInOrganization($org_policy, $organization);
        $this->authorize('delete', [$org_policy, $organization]);

        $this->policies->delete($org_policy, request()->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('policies.deleted'),
        ]);

        return redirect()->route('policies.index');
    }

    public function submitReview(Request $request, OrgPolicy $org_policy): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertPolicyInOrganization($org_policy, $organization);
        $this->authorize('update', [$org_policy, $organization]);

        $validated = $request->validate([
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where(
                    fn($query) => $query->where('organization_id', $organization->id),
                ),
            ],
            'assignee_user_id' => [
                'nullable',
                'integer',
                Rule::exists('organization_user', 'user_id')->where(
                    fn($query) => $query->where('organization_id', $organization->id),
                ),
            ],
        ]);

        $product = Product::query()->findOrFail($validated['product_id']);

        $this->policies->submitForReview(
            $org_policy,
            $request->user(),
            $product,
            isset($validated['assignee_user_id'])
            ? (int) $validated['assignee_user_id']
            : null,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('policies.submitted'),
        ]);

        return redirect()->route('policies.edit', $org_policy);
    }

    public function approve(OrgPolicy $org_policy): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertPolicyInOrganization($org_policy, $organization);
        $this->authorize('update', [$org_policy, $organization]);

        $this->policies->approve($org_policy, request()->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('policies.approved'),
        ]);

        return redirect()->route('policies.edit', $org_policy);
    }

    public function retire(OrgPolicy $org_policy): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertPolicyInOrganization($org_policy, $organization);
        $this->authorize('update', [$org_policy, $organization]);

        $this->policies->retire($org_policy, request()->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('policies.retired'),
        ]);

        return redirect()->route('policies.edit', $org_policy);
    }

    public function publishEvidence(Request $request, OrgPolicy $org_policy): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertPolicyInOrganization($org_policy, $organization);
        $this->authorize('update', [$org_policy, $organization]);

        $validated = $request->validate([
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where(
                    fn($query) => $query->where('organization_id', $organization->id),
                ),
            ],
        ]);

        $product = Product::query()->findOrFail($validated['product_id']);

        $this->policies->publishEvidence($org_policy, $product, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('policies.published_evidence'),
        ]);

        return redirect()->route('policies.edit', $org_policy);
    }

    public function template(Request $request): JsonResponse
    {
        $organization = $this->currentOrganization();
        $this->authorize('create', [OrgPolicy::class, $organization]);

        $validated = $request->validate([
            'policy_type' => ['required', Rule::enum(PolicyType::class)],
        ]);

        $type = PolicyType::from($validated['policy_type']);
        $locale = $organization->resolvedLocale();

        return response()->json($this->policies->templatePayload($type, $locale));
    }

    public function export(OrgPolicy $org_policy): HttpResponse
    {
        $organization = $this->currentOrganization();
        $this->assertPolicyInOrganization($org_policy, $organization);
        $this->authorize('view', [$org_policy, $organization]);

        $org_policy->loadMissing(['approver:id,name']);

        AuditLogger::logOrgPolicyExported($org_policy, request()->user());

        $slug = Str::slug($org_policy->title) ?: 'policy';
        $filename = sprintf(
            'policy-%s-%s-%s.pdf',
            $slug,
            Str::slug($org_policy->version_label) ?: 'version',
            now()->format('Y-m-d'),
        );

        $bodyHtml = Str::markdown($org_policy->body, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return Pdf::loadView('pdf.org-policy', [
            'organization' => $this->organizationPayload($organization),
            'policy' => [
                'title' => $org_policy->title,
                'policy_type' => $org_policy->policy_type->value,
                'status' => $org_policy->status->value,
                'version_label' => $org_policy->version_label,
                'approved_at' => $org_policy->approved_at?->toIso8601String(),
                'approved_by_name' => $org_policy->approver?->name,
                'body_html' => $bodyHtml,
            ],
            'generated_at' => now()->toIso8601String(),
        ])
            ->setPaper('a4')
            ->stream($filename);
    }

    private function currentOrganization(): Organization
    {
        $organization = request()->user()?->currentOrganization();

        if ($organization === null) {
            abort(403, 'No organization membership.');
        }

        return $organization;
    }

    private function assertPolicyInOrganization(OrgPolicy $org_policy, Organization $organization): void
    {
        if ($org_policy->organization_id !== $organization->id) {
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
     * @return array{policy_types: list<string>, statuses: list<string>}
     */
    private function enumOptions(): array
    {
        return [
            'policy_types' => array_column(PolicyType::cases(), 'value'),
            'statuses' => array_column(PolicyStatus::cases(), 'value'),
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function productOptions(Organization $organization): array
    {
        return Product::query()
            ->where('organization_id', $organization->id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn(Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
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
     * @return list<array{id: int, title: string, policy_type: string, version_label: string, status: string, body: string}>
     */
    private function supersedeOptions(Organization $organization): array
    {
        return OrgPolicy::query()
            ->where('organization_id', $organization->id)
            ->whereIn('status', [PolicyStatus::Approved->value, PolicyStatus::Retired->value])
            ->orderByDesc('id')
            ->get(['id', 'title', 'policy_type', 'version_label', 'status', 'body'])
            ->map(fn(OrgPolicy $org_policy) => [
                'id' => $org_policy->id,
                'title' => $org_policy->title,
                'policy_type' => $org_policy->policy_type->value,
                'version_label' => $org_policy->version_label,
                'status' => $org_policy->status->value,
                'body' => $org_policy->body,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function detailPayload(OrgPolicy $org_policy): array
    {
        return [
            'id' => $org_policy->id,
            'policy_type' => $org_policy->policy_type->value,
            'title' => $org_policy->title,
            'status' => $org_policy->status->value,
            'version_label' => $org_policy->version_label,
            'body' => $org_policy->body,
            'notes' => $org_policy->notes,
            'supersedes_id' => $org_policy->supersedes_id,
            'supersedes_title' => $org_policy->supersedes
                ? $org_policy->supersedes->title . ' (' . $org_policy->supersedes->version_label . ')'
                : null,
            'supersedes_body' => $org_policy->supersedes?->body,
            'approved_at' => $org_policy->approved_at?->toIso8601String(),
            'approved_by_name' => $org_policy->approver?->name,
            'is_editable' => $org_policy->isEditable(),
            'evidence_id' => $org_policy->evidence_id,
            'evidence_product_id' => $org_policy->evidence?->product_id,
            'evidence_title' => $org_policy->evidence?->title,
        ];
    }
}
