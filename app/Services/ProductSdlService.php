<?php

namespace App\Services;

use App\Enums\SdlRunStatus;
use App\Enums\SdlStage;
use App\Enums\SdlStageStatus;
use App\Models\Evidence;
use App\Models\Product;
use App\Models\ProductVersion;
use App\Models\SdlRun;
use App\Models\SdlStageEntry;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Translations;
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
     * @param  list<int>  $evidenceIds
     */
    public function create(
        Product $product,
        array $attributes,
        array $evidenceIds,
        User $actor,
    ): SdlRun {
        $run = DB::transaction(function () use ($product, $attributes, $evidenceIds) {
            $this->assertVersionBelongsToProduct(
                $product,
                isset($attributes['product_version_id'])
                ? (int) $attributes['product_version_id']
                : null,
            );
            $this->assertEvidenceBelongToProduct($product, $evidenceIds);
            $this->assertStatusNotApprovedViaForm($attributes['status'] ?? null);

            /** @var SdlRun $run */
            $run = SdlRun::query()->create([
                ...$attributes,
                'organization_id' => $product->organization_id,
                'product_id' => $product->id,
                'current_stage' => $attributes['current_stage'] ?? SdlStage::first(),
                'status' => $attributes['status'] ?? SdlRunStatus::Draft,
            ]);

            $run->ensureStageEntries();
            $run->evidence()->sync($evidenceIds);

            return $run->load(['owner', 'version', 'stageEntries', 'evidence']);
        });

        AuditLogger::logSdlRunCreated($run, $actor);

        return $run;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $evidenceIds
     */
    public function update(
        SdlRun $run,
        array $attributes,
        array $evidenceIds,
        User $actor,
    ): SdlRun {
        $this->assertRunEditable($run);
        $this->assertStatusNotApprovedViaForm($attributes['status'] ?? null);

        $run = DB::transaction(function () use ($run, $attributes, $evidenceIds) {
            $run->loadMissing('product');

            $this->assertVersionBelongsToProduct(
                $run->product,
                array_key_exists('product_version_id', $attributes)
                ? ($attributes['product_version_id'] !== null
                    ? (int) $attributes['product_version_id']
                    : null)
                : $run->product_version_id,
            );
            $this->assertEvidenceBelongToProduct($run->product, $evidenceIds);

            $run->update($attributes);
            $run->evidence()->sync($evidenceIds);

            return $run->fresh(['owner', 'version', 'stageEntries.evidence', 'evidence']) ?? $run;
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
     * Release security approval gate — sets status to approved with audit trail.
     */
    public function approve(SdlRun $run, User $actor): SdlRun
    {
        $run->ensureStageEntries();
        $run->load(['stageEntries', 'approver']);
        $this->assertReadyForApproval($run);

        $run = DB::transaction(function () use ($run, $actor) {
            $run->update([
                'status' => SdlRunStatus::Approved,
                'approved_at' => now(),
                'approved_by' => $actor->id,
                'current_stage' => SdlStage::Publication,
            ]);

            return $run->fresh([
                'owner',
                'version',
                'approver',
                'evidence',
                'stageEntries.completer',
                'stageEntries.evidence',
            ]) ?? $run;
        });

        AuditLogger::logSdlRunApproved($run, $actor);

        return $run;
    }

    /**
     * Revoke release security approval and reopen the run for editing.
     */
    public function revokeApproval(SdlRun $run, User $actor): SdlRun
    {
        if ($run->status !== SdlRunStatus::Approved) {
            throw ValidationException::withMessages([
                'status' => [Translations::get('products.sdl.only_approved_revocable')],
            ]);
        }

        $run = DB::transaction(function () use ($run) {
            $run->update([
                'status' => SdlRunStatus::InProgress,
                'approved_at' => null,
                'approved_by' => null,
                'current_stage' => SdlStage::ReleaseApproval,
            ]);

            return $run->fresh([
                'owner',
                'version',
                'approver',
                'evidence',
                'stageEntries.completer',
                'stageEntries.evidence',
            ]) ?? $run;
        });

        AuditLogger::logSdlRunApprovalRevoked($run, $actor);

        return $run;
    }

    /**
     * Update a single stage checklist entry (status + notes + evidence links).
     *
     * @param  array{status: SdlStageStatus|string, notes?: string|null, evidence_ids?: list<int>}  $attributes
     */
    public function updateStage(
        SdlRun $run,
        SdlStage $stage,
        array $attributes,
        User $actor,
    ): SdlStageEntry {
        $this->assertRunEditable($run);
        $run->ensureStageEntries();
        $run->loadMissing('product');

        /** @var SdlStageEntry $entry */
        $entry = $run->stageEntries()
            ->where('stage', $stage->value)
            ->firstOrFail();

        $previousStatus = $entry->status->value;
        $status = $attributes['status'] instanceof SdlStageStatus
            ? $attributes['status']
            : SdlStageStatus::from((string) $attributes['status']);

        $evidenceIds = array_key_exists('evidence_ids', $attributes)
            ? array_values(array_map('intval', $attributes['evidence_ids'] ?? []))
            : null;

        $entry = DB::transaction(function () use ($run, $entry, $status, $attributes, $actor, $stage, $evidenceIds) {
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

            if ($evidenceIds !== null) {
                $this->assertEvidenceBelongToProduct($run->product, $evidenceIds);
                $entry->evidence()->sync($evidenceIds);
            }

            $this->syncCurrentStageAfterChecklistChange($run, $stage, $status);

            return $entry->fresh(['completer', 'evidence']) ?? $entry;
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
            $run->load(['stageEntries.completer', 'stageEntries.evidence']);
        } elseif (
            $run->stageEntries->isNotEmpty()
            && (
                !$run->stageEntries->first()?->relationLoaded('completer')
                || !$run->stageEntries->first()?->relationLoaded('evidence')
            )
        ) {
            $run->load(['stageEntries.completer', 'stageEntries.evidence']);
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

        if (!$run->relationLoaded('evidence')) {
            $run->load('evidence');
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
            'can_approve' => $this->isReadyForApproval($run),
            'evidence_ids' => $run->evidence->pluck('id')->all(),
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
                        'evidence_ids' => $entry?->evidence->pluck('id')->all() ?? [],
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

    /**
     * @param  list<int>  $evidenceIds
     */
    private function assertEvidenceBelongToProduct(Product $product, array $evidenceIds): void
    {
        if ($evidenceIds === []) {
            return;
        }

        $uniqueIds = array_values(array_unique(array_map('intval', $evidenceIds)));

        $count = Evidence::query()
            ->where('organization_id', $product->organization_id)
            ->where('product_id', $product->id)
            ->whereIn('id', $uniqueIds)
            ->count();

        if ($count !== count($uniqueIds)) {
            throw ValidationException::withMessages([
                'evidence_ids' => ['One or more evidence records do not belong to this product.'],
            ]);
        }
    }

    private function assertRunEditable(SdlRun $run): void
    {
        if ($run->status === SdlRunStatus::Approved) {
            throw ValidationException::withMessages([
                'status' => [Translations::get('products.sdl.approved_locked')],
            ]);
        }
    }

    private function assertStatusNotApprovedViaForm(mixed $status): void
    {
        $value = $status instanceof SdlRunStatus ? $status : (
            is_string($status) ? SdlRunStatus::tryFrom($status) : null
        );

        if ($value === SdlRunStatus::Approved) {
            throw ValidationException::withMessages([
                'status' => [Translations::get('products.sdl.approve_via_gate')],
            ]);
        }
    }

    private function assertReadyForApproval(SdlRun $run): void
    {
        $errors = $this->approvalGateErrors($run);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function isReadyForApproval(SdlRun $run): bool
    {
        $run->ensureStageEntries();
        $run->loadMissing('stageEntries');

        return $this->approvalGateErrors($run) === [];
    }

    /**
     * @return array<string, list<string>>
     */
    private function approvalGateErrors(SdlRun $run): array
    {
        $errors = [];

        if ($run->status === SdlRunStatus::Cancelled) {
            $errors['status'] = [Translations::get('products.sdl.approve_cancelled')];
        }

        if ($run->status === SdlRunStatus::Approved) {
            $errors['status'] = [Translations::get('products.sdl.already_approved')];
        }

        $entriesByStage = $run->stageEntries->keyBy(
            fn(SdlStageEntry $entry) => $entry->stage->value,
        );

        foreach (SdlStage::ordered() as $stage) {
            $entry = $entriesByStage->get($stage->value);

            if ($entry === null || !$entry->status->isComplete()) {
                $errors['stages'] = [Translations::get('products.sdl.approve_requires_stages')];
            }

            if ($stage === SdlStage::ReleaseApproval) {
                break;
            }
        }

        $releaseEntry = $entriesByStage->get(SdlStage::ReleaseApproval->value);

        if ($releaseEntry === null || $releaseEntry->status !== SdlStageStatus::Done) {
            $errors['release_approval'] = [
                Translations::get('products.sdl.approve_requires_release_stage_done'),
            ];
        }

        return $errors;
    }
}
