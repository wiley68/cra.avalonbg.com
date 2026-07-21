<?php

namespace App\Http\Controllers;

use App\Enums\TaskApprovalStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Requests\ApproveTaskRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Evidence;
use App\Models\Organization;
use App\Models\OrgPolicy;
use App\Models\Product;
use App\Models\ProductRisk;
use App\Models\ProductVulnerability;
use App\Models\Task;
use App\Services\TaskService;
use App\Support\Translations;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function __construct(
        private readonly TaskService $tasks,
    ) {
    }

    public function index(Product $product): Response
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('viewAny', [Task::class, $organization]);
        $this->authorize('view', [$product, $organization]);

        return Inertia::render('products/tasks/Index', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productPayload($product),
            'canManage' => request()->user()->canManageTasks($organization),
            'options' => $this->enumOptions(),
        ]);
    }

    public function create(Product $product): Response
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->authorize('create', [Task::class, $organization]);

        return Inertia::render('products/tasks/Create', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productPayload($product),
            'members' => $this->memberOptions($organization),
            'subjects' => $this->subjectOptions($product),
            'options' => $this->enumOptions(),
        ]);
    }

    public function store(StoreTaskRequest $request, Product $product): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);

        $task = $this->tasks->create(
            $product,
            $this->validatedAttributes($request),
            $request->user(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.tasks.created'),
        ]);

        return redirect()->route('products.tasks.edit', [$product, $task]);
    }

    public function edit(Product $product, Task $task): Response
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertTaskBelongsToProduct($task, $product);
        $this->authorize('view', [$task, $organization]);

        $task->load(['assignee', 'creator', 'approver', 'subject']);

        $user = request()->user();

        return Inertia::render('products/tasks/Edit', [
            'organization' => $this->organizationPayload($organization),
            'product' => $this->productPayload($product),
            'task' => $this->tasks->detailPayload($task),
            'members' => $this->memberOptions($organization),
            'subjects' => $this->subjectOptions($product),
            'options' => $this->enumOptions(),
            'canManage' => $user->canManageTasks($organization),
            'canApprove' => $user->canApproveTasks($organization),
        ]);
    }

    public function update(
        UpdateTaskRequest $request,
        Product $product,
        Task $task,
    ): RedirectResponse {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertTaskBelongsToProduct($task, $product);

        $this->tasks->update($task, $this->validatedAttributes($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.tasks.updated'),
        ]);

        return redirect()->route('products.tasks.edit', [$product, $task]);
    }

    public function destroy(Product $product, Task $task): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertTaskBelongsToProduct($task, $product);
        $this->authorize('delete', [$task, $organization]);

        $this->tasks->delete($task);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.tasks.deleted'),
        ]);

        return redirect()->route('products.tasks.index', $product);
    }

    public function submitApproval(Product $product, Task $task): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertTaskBelongsToProduct($task, $product);
        $this->authorize('update', [$task, $organization]);

        $this->tasks->submitForApproval($task);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.tasks.submitted_for_approval'),
        ]);

        return redirect()->route('products.tasks.edit', [$product, $task]);
    }

    public function approve(ApproveTaskRequest $request, Product $product, Task $task): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertTaskBelongsToProduct($task, $product);

        $this->tasks->approve(
            $task,
            $request->user(),
            $request->input('approval_comment'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.tasks.approved'),
        ]);

        return redirect()->route('products.tasks.edit', [$product, $task]);
    }

    public function reject(ApproveTaskRequest $request, Product $product, Task $task): RedirectResponse
    {
        $organization = $this->currentOrganization();
        $this->assertProductInOrganization($product, $organization);
        $this->assertTaskBelongsToProduct($task, $product);

        $this->tasks->reject(
            $task,
            $request->user(),
            $request->input('approval_comment'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Translations::get('products.tasks.rejected'),
        ]);

        return redirect()->route('products.tasks.edit', [$product, $task]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedAttributes(StoreTaskRequest|UpdateTaskRequest $request): array
    {
        $attributes = [
            'title' => $request->string('title')->toString(),
            'description' => $request->input('description'),
            'status' => TaskStatus::from($request->string('status')->toString()),
            'priority' => TaskPriority::from($request->string('priority')->toString()),
            'assignee_user_id' => $request->input('assignee_user_id')
                ? (int) $request->input('assignee_user_id')
                : null,
            'due_at' => $request->input('due_at'),
            'subject_type' => $request->input('subject_type'),
            'subject_id' => $request->input('subject_id')
                ? (int) $request->input('subject_id')
                : null,
        ];

        if ($request->filled('approval_status')) {
            $attributes['approval_status'] = TaskApprovalStatus::from(
                $request->string('approval_status')->toString(),
            );
        } elseif ($request instanceof StoreTaskRequest) {
            $attributes['approval_status'] = TaskApprovalStatus::NotRequired;
        }

        return $attributes;
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

    private function assertTaskBelongsToProduct(Task $task, Product $product): void
    {
        if ($task->product_id !== $product->id) {
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
     * @return array{
     *     risks: list<array{id: int, label: string}>,
     *     vulnerabilities: list<array{id: int, label: string}>,
     *     evidence: list<array{id: int, label: string}>,
     *     org_policies: list<array{id: int, label: string}>
     * }
     */
    private function subjectOptions(Product $product): array
    {
        return [
            'risks' => ProductRisk::query()
                ->where('product_id', $product->id)
                ->orderBy('title')
                ->get(['id', 'title'])
                ->map(fn(ProductRisk $risk) => [
                    'id' => $risk->id,
                    'label' => $risk->title,
                ])
                ->all(),
            'vulnerabilities' => ProductVulnerability::query()
                ->where('product_id', $product->id)
                ->orderBy('title')
                ->get(['id', 'title', 'cve_id'])
                ->map(fn(ProductVulnerability $vulnerability) => [
                    'id' => $vulnerability->id,
                    'label' => $vulnerability->cve_id
                        ? "{$vulnerability->title} ({$vulnerability->cve_id})"
                        : $vulnerability->title,
                ])
                ->all(),
            'evidence' => Evidence::query()
                ->where('product_id', $product->id)
                ->orderBy('title')
                ->get(['id', 'title'])
                ->map(fn(Evidence $evidence) => [
                    'id' => $evidence->id,
                    'label' => $evidence->title,
                ])
                ->all(),
            'org_policies' => OrgPolicy::query()
                ->where('organization_id', $product->organization_id)
                ->orderByDesc('id')
                ->limit(100)
                ->get(['id', 'title', 'version_label', 'policy_type'])
                ->map(fn(OrgPolicy $policy) => [
                    'id' => $policy->id,
                    'label' => "{$policy->title} ({$policy->version_label})",
                ])
                ->all(),
        ];
    }

    /**
     * @return array{
     *     statuses: list<string>,
     *     priorities: list<string>,
     *     approval_statuses: list<string>,
     *     subject_types: list<string>
     * }
     */
    private function enumOptions(): array
    {
        return [
            'statuses' => array_column(TaskStatus::cases(), 'value'),
            'priorities' => array_column(TaskPriority::cases(), 'value'),
            'approval_statuses' => [
                TaskApprovalStatus::NotRequired->value,
                TaskApprovalStatus::Pending->value,
            ],
            'subject_types' => array_keys(TaskService::subjectTypeMap()),
        ];
    }
}
