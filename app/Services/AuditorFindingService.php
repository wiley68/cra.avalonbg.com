<?php

namespace App\Services;

use App\Enums\AuditorFindingSeverity;
use App\Enums\AuditorFindingStatus;
use App\Enums\AuditorReviewPackageStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\AuditorFinding;
use App\Models\AuditorReviewPackage;
use App\Models\Task;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Translations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuditorFindingService
{
    public function __construct(
        private readonly TaskService $tasks,
    ) {
    }

    /**
     * @param  array{
     *     title: string,
     *     body: string,
     *     severity: string
     * }  $attributes
     */
    public function create(
        AuditorReviewPackage $package,
        array $attributes,
        User $actor,
    ): AuditorFinding {
        $this->assertPackageAcceptsFindings($package);

        $package->loadMissing('product');

        return DB::transaction(function () use ($package, $attributes, $actor): AuditorFinding {
            $finding = AuditorFinding::query()->create([
                'package_id' => $package->id,
                'title' => trim($attributes['title']),
                'body' => trim($attributes['body']),
                'severity' => AuditorFindingSeverity::from($attributes['severity']),
                'status' => AuditorFindingStatus::Open,
                'created_by' => $actor->id,
                'remediated_at' => null,
            ]);

            $this->createRemediationTask($finding, $package, $actor);

            $finding->load(['creator:id,name', 'package.product']);
            AuditLogger::logAuditorFindingCreated($finding, $actor);

            return $finding;
        });
    }

    /**
     * @param  array{
     *     title: string,
     *     body: string,
     *     severity: string
     * }  $attributes
     */
    public function updateContent(
        AuditorFinding $finding,
        array $attributes,
        User $actor,
    ): AuditorFinding {
        $package = $finding->package;
        if ($package === null || $package->status !== AuditorReviewPackageStatus::Shared) {
            throw ValidationException::withMessages([
                'status' => Translations::get('auditor.findings.only_shared_editable'),
            ]);
        }

        $finding->update([
            'title' => trim($attributes['title']),
            'body' => trim($attributes['body']),
            'severity' => AuditorFindingSeverity::from($attributes['severity']),
        ]);

        $finding = $finding->fresh(['creator:id,name', 'package.product']);
        AuditLogger::logAuditorFindingUpdated($finding, $actor);

        return $finding;
    }

    public function updateStatus(
        AuditorFinding $finding,
        AuditorFindingStatus $status,
        User $actor,
    ): AuditorFinding {
        $package = $finding->package;
        if ($package === null || $package->status === AuditorReviewPackageStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => Translations::get('auditor.findings.only_shared_or_closed_status'),
            ]);
        }

        return DB::transaction(function () use ($finding, $status, $actor): AuditorFinding {
            $finding->update([
                'status' => $status,
                'remediated_at' => $status === AuditorFindingStatus::Remediated
                    ? ($finding->remediated_at ?? now())
                    : null,
            ]);

            if ($status === AuditorFindingStatus::Remediated) {
                $this->completeOpenRemediationTasks($finding);
            }

            $finding = $finding->fresh(['creator:id,name', 'package.product']);
            AuditLogger::logAuditorFindingStatusUpdated($finding, $actor);

            return $finding;
        });
    }

    public function delete(AuditorFinding $finding, User $actor): void
    {
        $package = $finding->package;
        if ($package === null || $package->status !== AuditorReviewPackageStatus::Shared) {
            throw ValidationException::withMessages([
                'status' => Translations::get('auditor.findings.only_shared_deletable'),
            ]);
        }

        if ($finding->status !== AuditorFindingStatus::Open) {
            throw ValidationException::withMessages([
                'status' => Translations::get('auditor.findings.only_open_deletable'),
            ]);
        }

        DB::transaction(function () use ($finding, $actor): void {
            $this->cancelOpenRemediationTasks($finding);
            AuditLogger::logAuditorFindingDeleted($finding, $actor);
            $finding->delete();
        });
    }

    /**
     * @return array{
     *     id: int,
     *     title: string,
     *     body: string,
     *     severity: string,
     *     status: string,
     *     created_by: int,
     *     created_by_name: string|null,
     *     remediated_at: string|null,
     *     created_at: string|null,
     *     updated_at: string|null,
     *     task: array{id: int, product_id: int, title: string, status: string}|null
     * }
     */
    public function payload(AuditorFinding $finding): array
    {
        $task = $this->openOrLatestRemediationTask($finding);

        return [
            'id' => $finding->id,
            'title' => $finding->title,
            'body' => $finding->body,
            'severity' => $finding->severity->value,
            'status' => $finding->status->value,
            'created_by' => $finding->created_by,
            'created_by_name' => $finding->creator?->name,
            'remediated_at' => $finding->remediated_at?->toIso8601String(),
            'created_at' => $finding->created_at?->toIso8601String(),
            'updated_at' => $finding->updated_at?->toIso8601String(),
            'task' => $task === null ? null : [
                'id' => $task->id,
                'product_id' => $task->product_id,
                'title' => $task->title,
                'status' => $task->status->value,
            ],
        ];
    }

    private function assertPackageAcceptsFindings(AuditorReviewPackage $package): void
    {
        if ($package->status !== AuditorReviewPackageStatus::Shared) {
            throw ValidationException::withMessages([
                'status' => Translations::get('auditor.findings.only_shared_creatable'),
            ]);
        }
    }

    private function createRemediationTask(
        AuditorFinding $finding,
        AuditorReviewPackage $package,
        User $actor,
    ): void {
        $product = $package->product;

        if ($product === null) {
            return;
        }

        $this->tasks->create($product, [
            'title' => Translations::get('auditor.findings.task_title', [
                'title' => $finding->title,
            ]),
            'description' => Translations::get('auditor.findings.task_description', [
                'package' => $package->title,
                'severity' => $finding->severity->value,
                'body' => $finding->body,
            ]),
            'status' => TaskStatus::Open,
            'priority' => $this->priorityForSeverity($finding->severity),
            'assignee_user_id' => $product->product_owner_user_id,
            'due_at' => now()->addDays(14),
            'subject_type' => 'auditor_finding',
            'subject_id' => $finding->id,
        ], $actor);
    }

    private function completeOpenRemediationTasks(AuditorFinding $finding): void
    {
        Task::query()
            ->where('subject_type', AuditorFinding::class)
            ->where('subject_id', $finding->id)
            ->whereIn('status', [
                TaskStatus::Open->value,
                TaskStatus::InProgress->value,
                TaskStatus::PendingApproval->value,
            ])
            ->update([
                'status' => TaskStatus::Completed->value,
            ]);
    }

    private function cancelOpenRemediationTasks(AuditorFinding $finding): void
    {
        Task::query()
            ->where('subject_type', AuditorFinding::class)
            ->where('subject_id', $finding->id)
            ->whereIn('status', [
                TaskStatus::Open->value,
                TaskStatus::InProgress->value,
                TaskStatus::PendingApproval->value,
            ])
            ->update([
                'status' => TaskStatus::Cancelled->value,
            ]);
    }

    private function openOrLatestRemediationTask(AuditorFinding $finding): ?Task
    {
        return Task::query()
            ->where('subject_type', AuditorFinding::class)
            ->where('subject_id', $finding->id)
            ->latest('id')
            ->first(['id', 'product_id', 'title', 'status']);
    }

    private function priorityForSeverity(AuditorFindingSeverity $severity): TaskPriority
    {
        return match ($severity) {
            AuditorFindingSeverity::Info => TaskPriority::Low,
            AuditorFindingSeverity::Minor => TaskPriority::Medium,
            AuditorFindingSeverity::Major,
            AuditorFindingSeverity::Critical => TaskPriority::High,
        };
    }
}
