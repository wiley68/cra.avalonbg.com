<?php

namespace App\Services;

use App\Enums\SdlRunStatus;
use App\Enums\SdlStage;
use App\Enums\SdlStageStatus;
use App\Models\Product;
use App\Models\ProductVersion;
use App\Models\SdlRun;
use App\Models\SdlStageEntry;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductSdlService
{
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
        $query = SdlRun::query()
            ->where('product_id', $product->id)
            ->with(['owner', 'version:id,version_number', 'product:id,name']);

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('current_stage', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");

                if (ctype_digit($search)) {
                    $builder->orWhere('id', (int) $search);
                }
            });
        }

        $orderColumn = match ($sortBy) {
            'id' => 'id',
            'status' => 'status',
            'current_stage' => 'current_stage',
            'approved_at' => 'approved_at',
            'updated_at' => 'updated_at',
            default => 'title',
        };

        $query->orderBy($orderColumn, $sortOrder === 'desc' ? 'desc' : 'asc');

        return $query
            ->paginate($perPage, ['*'], 'page', $page)
            ->through(fn(SdlRun $run) => $this->listItemPayload($run));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Product $product, array $attributes, User $actor): SdlRun
    {
        $run = DB::transaction(function () use ($product, $attributes) {
            $this->assertVersionBelongsToProduct(
                $product,
                isset($attributes['product_version_id'])
                ? (int) $attributes['product_version_id']
                : null,
            );

            /** @var SdlRun $run */
            $run = SdlRun::query()->create([
                ...$attributes,
                'organization_id' => $product->organization_id,
                'product_id' => $product->id,
                'current_stage' => $attributes['current_stage'] ?? SdlStage::first(),
                'status' => $attributes['status'] ?? SdlRunStatus::Draft,
            ]);

            $run->ensureStageEntries();

            return $run->load(['owner', 'version', 'stageEntries']);
        });

        AuditLogger::logSdlRunCreated($run, $actor);

        return $run;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(SdlRun $run, array $attributes, User $actor): SdlRun
    {
        $run = DB::transaction(function () use ($run, $attributes) {
            $run->loadMissing('product');

            $this->assertVersionBelongsToProduct(
                $run->product,
                array_key_exists('product_version_id', $attributes)
                ? ($attributes['product_version_id'] !== null
                    ? (int) $attributes['product_version_id']
                    : null)
                : $run->product_version_id,
            );

            $run->update($attributes);

            return $run->fresh(['owner', 'version', 'stageEntries']) ?? $run;
        });

        AuditLogger::logSdlRunUpdated($run, $actor);

        return $run;
    }

    public function delete(SdlRun $run, User $actor): void
    {
        AuditLogger::logSdlRunDeleted($run, $actor);
        $run->delete();
    }

    /**
     * Update a single stage checklist entry (status + notes).
     *
     * @param  array{status: SdlStageStatus|string, notes?: string|null}  $attributes
     */
    public function updateStage(
        SdlRun $run,
        SdlStage $stage,
        array $attributes,
        User $actor,
    ): SdlStageEntry {
        $run->ensureStageEntries();

        /** @var SdlStageEntry $entry */
        $entry = $run->stageEntries()
            ->where('stage', $stage->value)
            ->firstOrFail();

        $previousStatus = $entry->status->value;
        $status = $attributes['status'] instanceof SdlStageStatus
            ? $attributes['status']
            : SdlStageStatus::from((string) $attributes['status']);

        $entry = DB::transaction(function () use ($run, $entry, $status, $attributes, $actor, $stage) {
            $wasComplete = $entry->status->isComplete();
            $isComplete = $status->isComplete();

            $entry->status = $status;
            $entry->notes = array_key_exists('notes', $attributes)
                ? $attributes['notes']
                : $entry->notes;

            if ($isComplete && (!$wasComplete || $entry->completed_at === null)) {
                $entry->completed_at = now();
                $entry->completed_by = $actor->id;
            }

            if (!$isComplete) {
                $entry->completed_at = null;
                $entry->completed_by = null;
            }

            $entry->save();

            $this->syncCurrentStageAfterChecklistChange($run, $stage, $status);

            return $entry->fresh(['completer']) ?? $entry;
        });

        AuditLogger::logSdlStageUpdated($run, $entry, $actor, $previousStatus);

        return $entry;
    }

    /**
     * Keep run.current_stage aligned with checklist progress.
     */
    private function syncCurrentStageAfterChecklistChange(
        SdlRun $run,
        SdlStage $updatedStage,
        SdlStageStatus $status,
    ): void {
        $run->refresh()->load('stageEntries');

        if (!$status->isComplete()) {
            if ($run->current_stage !== $updatedStage) {
                $run->update(['current_stage' => $updatedStage]);
            }

            if ($run->status === SdlRunStatus::Draft) {
                $run->update(['status' => SdlRunStatus::InProgress]);
            }

            return;
        }

        $nextPending = null;
        $entriesByStage = $run->stageEntries->keyBy(
            fn(SdlStageEntry $item) => $item->stage->value,
        );

        foreach (SdlStage::ordered() as $candidate) {
            $entry = $entriesByStage->get($candidate->value);

            if ($entry === null || !$entry->status->isComplete()) {
                $nextPending = $candidate;
                break;
            }
        }

        if ($nextPending !== null && $run->current_stage !== $nextPending) {
            $run->update(['current_stage' => $nextPending]);
        }

        if ($run->status === SdlRunStatus::Draft) {
            $run->update(['status' => SdlRunStatus::InProgress]);
        }
    }

    /**
     * @return array{
     *     id: int,
     *     title: string,
     *     status: string,
     *     current_stage: string,
     *     product_id: int,
     *     product_name: string,
     *     product_version_id: int|null,
     *     version_number: string|null,
     *     owner_name: string|null,
     *     approved_at: string|null,
     *     updated_at: string|null
     * }
     */
    public function listItemPayload(SdlRun $run): array
    {
        return [
            'id' => $run->id,
            'title' => $run->title,
            'status' => $run->status->value,
            'current_stage' => $run->current_stage->value,
            'product_id' => $run->product_id,
            'product_name' => $run->product?->name ?? '',
            'product_version_id' => $run->product_version_id,
            'version_number' => $run->version?->version_number,
            'owner_name' => $run->owner?->name,
            'approved_at' => $run->approved_at?->toIso8601String(),
            'updated_at' => $run->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detailPayload(SdlRun $run): array
    {
        if (!$run->relationLoaded('stageEntries')) {
            $run->load(['stageEntries.completer']);
        } elseif (
            $run->stageEntries->isNotEmpty()
            && !$run->stageEntries->first()?->relationLoaded('completer')
        ) {
            $run->load(['stageEntries.completer']);
        }

        if (!$run->relationLoaded('owner')) {
            $run->load('owner');
        }

        if (!$run->relationLoaded('version')) {
            $run->load('version');
        }

        if (!$run->relationLoaded('approver')) {
            $run->load('approver');
        }

        $orderedStages = SdlStage::ordered();
        $entriesByStage = $run->stageEntries->keyBy(
            fn(SdlStageEntry $entry) => $entry->stage->value,
        );

        return [
            'id' => $run->id,
            'title' => $run->title,
            'status' => $run->status->value,
            'current_stage' => $run->current_stage->value,
            'product_version_id' => $run->product_version_id,
            'owner_user_id' => $run->owner_user_id,
            'notes' => $run->notes,
            'approved_at' => $run->approved_at?->toIso8601String(),
            'approved_by' => $run->approved_by,
            'approved_by_name' => $run->approver?->name,
            'is_terminal' => $run->isTerminal(),
            'is_approved' => $run->isApproved(),
            'stage_entries' => collect($orderedStages)
                ->map(function (SdlStage $stage) use ($entriesByStage) {
                    $entry = $entriesByStage->get($stage->value);

                    return [
                        'id' => $entry?->id,
                        'stage' => $stage->value,
                        'status' => $entry?->status->value ?? 'pending',
                        'completed_at' => $entry?->completed_at?->toIso8601String(),
                        'completed_by' => $entry?->completed_by,
                        'completed_by_name' => $entry?->completer?->name,
                        'notes' => $entry?->notes,
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    private function assertVersionBelongsToProduct(Product $product, ?int $versionId): void
    {
        if ($versionId === null) {
            return;
        }

        $exists = ProductVersion::query()
            ->whereKey($versionId)
            ->where('product_id', $product->id)
            ->exists();

        if (!$exists) {
            throw ValidationException::withMessages([
                'product_version_id' => 'The selected version does not belong to this product.',
            ]);
        }
    }
}
