<?php

namespace App\Services;

use App\Enums\TaskApprovalStatus;
use App\Enums\TaskStatus;
use App\Models\AuditorFinding;
use App\Models\Evidence;
use App\Models\OrgPolicy;
use App\Models\Product;
use App\Models\ProductIncident;
use App\Models\ProductRisk;
use App\Models\ProductVulnerability;
use App\Models\Task;
use App\Models\User;
use App\Models\UserSecurityInstruction;
use App\Support\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class TaskService
{
    /**
     * @return array<string, class-string>
     */
    public static function subjectTypeMap(): array
    {
        return [
            'risk' => ProductRisk::class,
            'vulnerability' => ProductVulnerability::class,
            'evidence' => Evidence::class,
            'org_policy' => OrgPolicy::class,
            'auditor_finding' => AuditorFinding::class,
            'user_security_instruction' => UserSecurityInstruction::class,
            'incident' => ProductIncident::class,
        ];
    }

    public static function subjectAlias(?string $class): ?string
    {
        if ($class === null) {
            return null;
        }

        foreach (self::subjectTypeMap() as $alias => $mapped) {
            if ($mapped === $class) {
                return $alias;
            }
        }

        return null;
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(
        Product $product,
        int $perPage = 10,
        int $page = 1,
        string $sortBy = 'title',
        string $sortOrder = 'asc',
        string $search = '',
    ): LengthAwarePaginator {
        $query = Task::query()
            ->where('product_id', $product->id)
            ->with(['assignee', 'approver']);

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('priority', 'like', "%{$search}%")
                    ->orWhere('approval_status', 'like', "%{$search}%");

                if (ctype_digit($search)) {
                    $builder->orWhere('id', (int) $search);
                }
            });
        }

        $orderColumn = match ($sortBy) {
            'id' => 'id',
            'status' => 'status',
            'priority' => 'priority',
            'due_at' => 'due_at',
            'approval_status' => 'approval_status',
            'approved_at' => 'approved_at',
            default => 'title',
        };

        $query->orderBy($orderColumn, $sortOrder === 'desc' ? 'desc' : 'asc');

        return $query
            ->paginate($perPage, ['*'], 'page', $page)
            ->through(fn(Task $task) => $this->listItemPayload($task));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Product $product, array $attributes, User $creator): Task
    {
        $task = DB::transaction(function () use ($product, $attributes, $creator) {
            $subject = $this->resolveSubject(
                $product,
                $attributes['subject_type'] ?? null,
                $attributes['subject_id'] ?? null,
            );

            /** @var Task $task */
            $task = Task::query()->create([
                'organization_id' => $product->organization_id,
                'product_id' => $product->id,
                'title' => $attributes['title'],
                'description' => $attributes['description'] ?? null,
                'status' => $attributes['status'],
                'priority' => $attributes['priority'],
                'assignee_user_id' => $attributes['assignee_user_id'] ?? null,
                'due_at' => $attributes['due_at'] ?? null,
                'created_by' => $creator->id,
                'subject_type' => $subject['type'],
                'subject_id' => $subject['id'],
                'approval_status' => $attributes['approval_status'] ?? TaskApprovalStatus::NotRequired,
            ]);

            if (
                $task->approval_status === TaskApprovalStatus::Pending
                && $task->status !== TaskStatus::PendingApproval
            ) {
                $task->update(['status' => TaskStatus::PendingApproval]);
            }

            return $task->load(['assignee', 'creator', 'approver', 'subject']);
        });

        AuditLogger::logTaskCreated($task, $creator);

        return $task;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Task $task, array $attributes): Task
    {
        $task = DB::transaction(function () use ($task, $attributes) {
            $subject = $this->resolveSubject(
                $task->product,
                $attributes['subject_type'] ?? null,
                $attributes['subject_id'] ?? null,
            );

            $payload = [
                'title' => $attributes['title'],
                'description' => $attributes['description'] ?? null,
                'status' => $attributes['status'],
                'priority' => $attributes['priority'],
                'assignee_user_id' => $attributes['assignee_user_id'] ?? null,
                'due_at' => $attributes['due_at'] ?? null,
                'subject_type' => $subject['type'],
                'subject_id' => $subject['id'],
            ];

            if (array_key_exists('approval_status', $attributes) && $attributes['approval_status'] !== null) {
                $payload['approval_status'] = $attributes['approval_status'];
            }

            $task->update($payload);

            return $task->fresh(['assignee', 'creator', 'approver', 'subject']);
        });

        $actor = Auth::user();
        if ($actor instanceof User) {
            AuditLogger::logTaskUpdated($task, $actor);
        }

        return $task;
    }

    public function delete(Task $task): void
    {
        $actor = Auth::user();
        if ($actor instanceof User) {
            AuditLogger::logTaskDeleted($task, $actor);
        }

        $task->delete();
    }

    public function submitForApproval(Task $task): Task
    {
        if ($task->approval_status === TaskApprovalStatus::Pending) {
            throw ValidationException::withMessages([
                'approval_status' => 'Task is already pending approval.',
            ]);
        }

        if ($task->approval_status === TaskApprovalStatus::Approved) {
            throw ValidationException::withMessages([
                'approval_status' => 'Task is already approved.',
            ]);
        }

        $task->update([
            'approval_status' => TaskApprovalStatus::Pending,
            'status' => TaskStatus::PendingApproval,
            'approved_by' => null,
            'approved_at' => null,
            'approval_comment' => null,
        ]);

        return $task->fresh(['assignee', 'creator', 'approver', 'subject']);
    }

    public function approve(Task $task, User $approver, ?string $comment = null): Task
    {
        if ($task->approval_status !== TaskApprovalStatus::Pending) {
            throw ValidationException::withMessages([
                'approval_status' => 'Only pending tasks can be approved.',
            ]);
        }

        $task->update([
            'approval_status' => TaskApprovalStatus::Approved,
            'status' => TaskStatus::Completed,
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'approval_comment' => $comment,
        ]);

        AuditLogger::logTaskApproved($task, $approver, $comment);

        return $task->fresh(['assignee', 'creator', 'approver', 'subject']);
    }

    public function reject(Task $task, User $approver, ?string $comment = null): Task
    {
        if ($task->approval_status !== TaskApprovalStatus::Pending) {
            throw ValidationException::withMessages([
                'approval_status' => 'Only pending tasks can be rejected.',
            ]);
        }

        $task->update([
            'approval_status' => TaskApprovalStatus::Rejected,
            'status' => TaskStatus::InProgress,
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'approval_comment' => $comment,
        ]);

        AuditLogger::logTaskRejected($task, $approver, $comment);

        return $task->fresh(['assignee', 'creator', 'approver', 'subject']);
    }

    /**
     * @return array<string, mixed>
     */
    public function listItemPayload(Task $task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'status' => $task->status->value,
            'priority' => $task->priority->value,
            'approval_status' => $task->approval_status->value,
            'assignee_name' => $task->assignee?->name,
            'due_at' => $task->due_at?->toIso8601String(),
            'subject_type' => self::subjectAlias($task->subject_type),
            'approved_at' => $task->approved_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detailPayload(Task $task): array
    {
        $subjectLabel = null;

        if ($task->subject !== null) {
            $subjectLabel = match (true) {
                $task->subject instanceof ProductRisk => $task->subject->title,
                $task->subject instanceof ProductVulnerability => $task->subject->title
                ?? $task->subject->cve_id
                ?? ('#' . $task->subject->id),
                $task->subject instanceof Evidence => $task->subject->title,
                $task->subject instanceof OrgPolicy => $task->subject->title
                . ' (' . $task->subject->version_label . ')',
                $task->subject instanceof AuditorFinding => $task->subject->title,
                $task->subject instanceof UserSecurityInstruction => $task->subject->title
                . ' (' . $task->subject->version_label . ' · ' . $task->subject->locale . ')',
                $task->subject instanceof ProductIncident => $task->subject->title,
                default => '#' . $task->subject_id,
            };
        }

        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status->value,
            'priority' => $task->priority->value,
            'assignee_user_id' => $task->assignee_user_id,
            'due_at' => $task->due_at?->toDateString(),
            'subject_type' => self::subjectAlias($task->subject_type),
            'subject_id' => $task->subject_id,
            'subject_label' => $subjectLabel,
            'approval_status' => $task->approval_status->value,
            'approved_by' => $task->approved_by,
            'approver_name' => $task->approver?->name,
            'approved_at' => $task->approved_at?->toIso8601String(),
            'approval_comment' => $task->approval_comment,
            'created_by' => $task->created_by,
            'creator_name' => $task->creator?->name,
        ];
    }

    /**
     * @return array{type: string|null, id: int|null}
     */
    private function resolveSubject(Product $product, mixed $subjectType, mixed $subjectId): array
    {
        if ($subjectType === null || $subjectType === '' || $subjectId === null || $subjectId === '') {
            return ['type' => null, 'id' => null];
        }

        $alias = (string) $subjectType;
        $map = self::subjectTypeMap();

        if (!isset($map[$alias])) {
            throw ValidationException::withMessages([
                'subject_type' => 'Invalid subject type.',
            ]);
        }

        $class = $map[$alias];
        $id = (int) $subjectId;

        $exists = match ($class) {
            ProductRisk::class => ProductRisk::query()
                ->where('id', $id)
                ->where('product_id', $product->id)
                ->exists(),
            ProductVulnerability::class => ProductVulnerability::query()
                ->where('id', $id)
                ->where('product_id', $product->id)
                ->exists(),
            Evidence::class => Evidence::query()
                ->where('id', $id)
                ->where('product_id', $product->id)
                ->exists(),
            OrgPolicy::class => OrgPolicy::query()
                ->where('id', $id)
                ->where('organization_id', $product->organization_id)
                ->exists(),
            AuditorFinding::class => AuditorFinding::query()
                ->where('id', $id)
                ->whereHas(
                    'package',
                    fn($query) => $query->where('product_id', $product->id),
                )
                ->exists(),
            UserSecurityInstruction::class => UserSecurityInstruction::query()
                ->where('id', $id)
                ->where('product_id', $product->id)
                ->exists(),
            ProductIncident::class => ProductIncident::query()
                ->where('id', $id)
                ->where('product_id', $product->id)
                ->exists(),
            default => throw new InvalidArgumentException('Unsupported subject class.'),
        };

        if (!$exists) {
            throw ValidationException::withMessages([
                'subject_id' => 'Subject does not belong to this product.',
            ]);
        }

        return ['type' => $class, 'id' => $id];
    }
}
