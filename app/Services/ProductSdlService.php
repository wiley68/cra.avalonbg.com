<?php

namespace App\Services;

use App\Enums\EvidenceConfidentiality;
use App\Enums\EvidenceFreshnessStatus;
use App\Enums\EvidenceType;
use App\Enums\SdlRunStatus;
use App\Enums\SdlStage;
use App\Enums\SdlStageStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Evidence;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductVersion;
use App\Models\SdlException;
use App\Models\SdlRun;
use App\Models\SdlStageEntry;
use App\Models\Task;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\SdlStageNoteTemplates;
use App\Support\Translations;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductSdlService
{
    public function __construct(
        private readonly TaskService $tasks,
    ) {
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
        ?int $productVersionId = null,
        bool $productWideOnly = false,
    ): LengthAwarePaginator {
        $query = SdlRun::query()
            ->where('product_id', $product->id)
            ->with(['owner', 'version:id,version_number', 'product:id,name']);

        if ($productWideOnly) {
            $query->whereNull('product_version_id');
        } elseif ($productVersionId !== null) {
            $query->where('product_version_id', $productVersionId);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('current_stage', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas(
                        'version',
                        fn($versionQuery) => $versionQuery->where(
                            'version_number',
                            'like',
                            "%{$search}%",
                        ),
                    );

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
            'version_number' => 'product_version_id',
            default => 'title',
        };

        $query->orderBy($orderColumn, $sortOrder === 'desc' ? 'desc' : 'asc');

        return $query
            ->paginate($perPage, ['*'], 'page', $page)
            ->through(fn(SdlRun $run) => $this->listItemPayload($run));
    }

    /**
     * Org-level cross-product SDL run listing.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateForOrganization(
        Organization $organization,
        int $perPage = 10,
        int $page = 1,
        string $sortBy = 'title',
        string $sortOrder = 'asc',
        string $search = '',
    ): LengthAwarePaginator {
        $query = SdlRun::query()
            ->where('organization_id', $organization->id)
            ->with(['owner', 'version:id,version_number', 'product:id,name']);

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('current_stage', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas(
                        'product',
                        fn($productQuery) => $productQuery->where('name', 'like', "%{$search}%"),
                    )
                    ->orWhereHas(
                        'version',
                        fn($versionQuery) => $versionQuery->where(
                            'version_number',
                            'like',
                            "%{$search}%",
                        ),
                    );

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
            'version_number' => 'product_version_id',
            'product_name' => 'product_id',
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
        bool $useTemplate = false,
        string $locale = 'en',
    ): SdlRun {
        $run = DB::transaction(function () use ($product, $attributes, $evidenceIds, $useTemplate, $locale) {
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

            if ($useTemplate) {
                $this->applyStageNoteTemplates($run, $locale);
            }

            return $run->load(['owner', 'version', 'stageEntries', 'evidence']);
        });

        AuditLogger::logSdlRunCreated($run, $actor);

        return $run;
    }

    /**
     * Prefill stage notes with secure coding / threat checklist templates.
     */
    public function applyStageNoteTemplates(SdlRun $run, string $locale = 'en'): void
    {
        $run->ensureStageEntries();
        $locale = SdlStageNoteTemplates::normalizeLocale($locale);

        foreach (SdlStageNoteTemplates::templatedStages() as $stage) {
            $notes = SdlStageNoteTemplates::notesFor($stage, $locale);

            if ($notes === null) {
                continue;
            }

            $run->stageEntries()
                ->where('stage', $stage->value)
                ->update(['notes' => $notes]);
        }

        $run->unsetRelation('stageEntries');
        $run->load('stageEntries');
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
     * Create Evidence from an external PR/CI URL and attach it to the SDL run (and optional stage).
     */
    public function linkExternalEvidence(
        SdlRun $run,
        string $url,
        ?string $title,
        ?SdlStage $stage,
        User $actor,
    ): Evidence {
        $this->assertRunEditable($run);
        $run->loadMissing('product');

        $normalizedUrl = $this->normalizeExternalEvidenceUrl($url);
        $resolvedTitle = trim((string) $title);
        if ($resolvedTitle === '') {
            $resolvedTitle = $this->titleFromExternalUrl($normalizedUrl);
        }

        $evidence = DB::transaction(function () use ($run, $normalizedUrl, $resolvedTitle, $stage, $actor) {
            /** @var Evidence $evidence */
            $evidence = Evidence::query()->create([
                'organization_id' => $run->organization_id,
                'product_id' => $run->product_id,
                'type' => EvidenceType::Other,
                'title' => $resolvedTitle,
                'source' => $normalizedUrl,
                'owner_user_id' => $actor->id,
                'confidentiality' => EvidenceConfidentiality::Internal,
                'collected_at' => now(),
                'freshness_status' => EvidenceFreshnessStatus::Current,
                'uploaded_by' => $actor->id,
                'notes' => Translations::get('products.sdl.git_link_notes'),
            ]);

            $run->evidence()->syncWithoutDetaching([$evidence->id]);

            if ($stage !== null) {
                $run->ensureStageEntries();
                /** @var SdlStageEntry $entry */
                $entry = $run->stageEntries()
                    ->where('stage', $stage->value)
                    ->firstOrFail();
                $entry->evidence()->syncWithoutDetaching([$evidence->id]);
            }

            return $evidence;
        });

        AuditLogger::logEvidenceCreated($evidence, $actor);

        return $evidence;
    }

    /**
     * Recent integration_snapshot evidence for Git/CI quick-link UI.
     *
     * @return list<array{
     *     id: int,
     *     title: string,
     *     source: string|null,
     *     collected_at: string|null,
     *     checksum_short: string|null
     * }>
     */
    public function gitEvidenceOptions(Product $product, int $limit = 8): array
    {
        return Evidence::query()
            ->where('product_id', $product->id)
            ->where('organization_id', $product->organization_id)
            ->where('type', EvidenceType::IntegrationSnapshot)
            ->orderByDesc('collected_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'title', 'source', 'collected_at', 'checksum_sha256'])
            ->map(fn(Evidence $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'source' => $item->source,
                'collected_at' => $item->collected_at?->toIso8601String(),
                'checksum_short' => $item->checksum_sha256 !== null
                    ? substr($item->checksum_sha256, 0, 12)
                    : null,
            ])
            ->all();
    }

    /**
     * Suggest evidence from the product's latest VCS sync (human review — no auto-attach).
     *
     * @return array{
     *     synced_at: string|null,
     *     has_error: bool,
     *     items: list<array{
     *         kind: string,
     *         evidence_id: int|null,
     *         title: string,
     *         url: string|null,
     *         source: string|null,
     *         checksum_short: string|null,
     *         collected_at: string|null,
     *         suggested_stages: list<string>,
     *         already_on_run: bool,
     *         ci_conclusion: string|null
     *     }>
     * }
     */
    public function gitSyncSuggestions(Product $product, ?SdlRun $run = null): array
    {
        $product->loadMissing('repository');
        $repository = $product->repository;
        $summary = is_array($repository?->last_sync_summary)
            ? $repository->last_sync_summary
            : [];

        $runEvidenceIds = [];
        $runEvidenceSources = [];

        if ($run !== null) {
            $run->loadMissing('evidence:id,source');
            $runEvidenceIds = $run->evidence->pluck('id')->all();
            $runEvidenceSources = $run->evidence
                ->pluck('source')
                ->filter()
                ->map(fn($source) => rtrim(strtolower((string) $source), '/'))
                ->values()
                ->all();
        }

        $items = [];

        $evidenceId = isset($summary['evidence_id']) ? (int) $summary['evidence_id'] : 0;
        if ($evidenceId > 0) {
            $snapshot = Evidence::query()
                ->whereKey($evidenceId)
                ->where('product_id', $product->id)
                ->where('organization_id', $product->organization_id)
                ->first(['id', 'title', 'source', 'collected_at', 'checksum_sha256']);

            if ($snapshot !== null) {
                $items[] = [
                    'kind' => 'snapshot',
                    'evidence_id' => $snapshot->id,
                    'title' => $snapshot->title,
                    'url' => null,
                    'source' => $snapshot->source,
                    'checksum_short' => $snapshot->checksum_sha256 !== null
                        ? substr($snapshot->checksum_sha256, 0, 12)
                        : null,
                    'collected_at' => $snapshot->collected_at?->toIso8601String(),
                    'suggested_stages' => [
                        SdlStage::DependencyScan->value,
                        SdlStage::SecurityTest->value,
                    ],
                    'already_on_run' => in_array($snapshot->id, $runEvidenceIds, true),
                    'ci_conclusion' => null,
                ];
            }
        }

        $ci = is_array($summary['ci'] ?? null) ? $summary['ci'] : [];
        $ciUrl = isset($ci['html_url']) ? trim((string) $ci['html_url']) : '';
        if (
            $ciUrl !== ''
            && filter_var($ciUrl, FILTER_VALIDATE_URL) !== false
            && preg_match('#^https?://#i', $ciUrl) === 1
        ) {
            $normalizedCi = rtrim(strtolower($ciUrl), '/');
            $workflow = isset($ci['workflow_name']) ? trim((string) $ci['workflow_name']) : '';
            $conclusion = isset($ci['conclusion']) ? trim((string) $ci['conclusion']) : '';
            $title = $workflow !== ''
                ? $workflow
                : Translations::get('products.sdl.git_suggest_ci_default_title');

            $items[] = [
                'kind' => 'ci_url',
                'evidence_id' => null,
                'title' => $title,
                'url' => $ciUrl,
                'source' => $ciUrl,
                'checksum_short' => null,
                'collected_at' => null,
                'suggested_stages' => $this->suggestedStagesForGitUrl($ciUrl),
                'already_on_run' => in_array($normalizedCi, $runEvidenceSources, true),
                'ci_conclusion' => $conclusion !== '' ? $conclusion : null,
            ];
        }

        return [
            'synced_at' => $repository?->last_synced_at?->toIso8601String(),
            'has_error' => filled($summary['error'] ?? null),
            'items' => $items,
        ];
    }

    /**
     * @return list<string>
     */
    private function suggestedStagesForGitUrl(string $url): array
    {
        $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?? ''));

        if (
            str_contains($path, '/pull/')
            || str_contains($path, '/pulls/')
            || str_contains($path, '/merge_requests/')
            || str_contains($path, '/-/merge_requests/')
        ) {
            return [SdlStage::CodeReview->value];
        }

        return [SdlStage::SecurityTest->value, SdlStage::CodeReview->value];
    }

    private function normalizeExternalEvidenceUrl(string $url): string
    {
        $normalized = trim($url);

        if (
            filter_var($normalized, FILTER_VALIDATE_URL) === false
            || !preg_match('#^https?://#i', $normalized)
        ) {
            throw ValidationException::withMessages([
                'url' => [Translations::get('products.sdl.invalid_git_url')],
            ]);
        }

        return $normalized;
    }

    private function titleFromExternalUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $host = parse_url($url, PHP_URL_HOST) ?: 'git';

        if (is_string($path) && $path !== '' && $path !== '/') {
            $segments = array_values(array_filter(explode('/', $path)));
            $tail = implode('/', array_slice($segments, -3));

            return $host . '/' . $tail;
        }

        return $host;
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

        $exceptionPayload = null;
        $exceptionCleared = false;
        $exceptionTaskId = null;

        $entry = DB::transaction(function () use ($run, $entry, $status, $attributes, $actor, $stage, $evidenceIds, &$exceptionPayload, &$exceptionCleared, &$exceptionTaskId, ) {
            $wasComplete = $entry->status->isComplete();
            $isComplete = $status->isComplete();
            $wasException = $entry->status === SdlStageStatus::Exception;

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

            if ($status === SdlStageStatus::Exception) {
                $exception = $this->syncStageException($run, $entry, $attributes, $actor);
                $exceptionTaskId = $this->syncExceptionFollowUpTask($run, $entry, $exception, $actor);
                $exceptionPayload = $exception;
            } elseif ($wasException) {
                $this->clearStageException($entry);
                $exceptionCleared = true;
            }

            $this->syncCurrentStageAfterChecklistChange($run, $stage, $status);

            return $entry->fresh(['completer', 'evidence', 'exception.owner']) ?? $entry;
        });

        AuditLogger::logSdlStageUpdated($run, $entry, $actor, $previousStatus);

        if ($exceptionPayload instanceof SdlException) {
            AuditLogger::logSdlExceptionRecorded(
                $run,
                $entry,
                $exceptionPayload,
                $actor,
                $exceptionTaskId,
            );
        } elseif ($exceptionCleared) {
            AuditLogger::logSdlExceptionCleared($run, $entry, $actor);
        }

        return $entry;
    }

    /**
     * @param  array{exception_owner_user_id?: mixed, exception_expires_at?: mixed, notes?: mixed}  $attributes
     */
    private function syncStageException(
        SdlRun $run,
        SdlStageEntry $entry,
        array $attributes,
        User $actor,
    ): SdlException {
        $ownerId = isset($attributes['exception_owner_user_id'])
            ? (int) $attributes['exception_owner_user_id']
            : null;
        $expiresAt = isset($attributes['exception_expires_at'])
            ? (string) $attributes['exception_expires_at']
            : null;
        $notes = trim((string) ($attributes['notes'] ?? $entry->notes ?? ''));

        if ($ownerId === null || $ownerId === 0 || $expiresAt === null || $expiresAt === '') {
            throw ValidationException::withMessages([
                'exception_owner_user_id' => [Translations::get('products.sdl.exception_required')],
            ]);
        }

        if ($notes === '') {
            throw ValidationException::withMessages([
                'notes' => [Translations::get('products.sdl.exception_notes_required')],
            ]);
        }

        $memberExists = DB::table('organization_user')
            ->where('organization_id', $run->organization_id)
            ->where('user_id', $ownerId)
            ->exists();

        if (!$memberExists) {
            throw ValidationException::withMessages([
                'exception_owner_user_id' => [Translations::get('products.sdl.exception_owner_invalid')],
            ]);
        }

        /** @var SdlException $exception */
        $existing = $entry->exception()->first();
        $exception = $entry->exception()->updateOrCreate(
            ['sdl_stage_entry_id' => $entry->id],
            [
                'owner_user_id' => $ownerId,
                'expires_at' => $expiresAt,
                'created_by' => $existing?->created_by ?? $actor->id,
            ],
        );

        return $exception->fresh(['owner']) ?? $exception;
    }

    private function syncExceptionFollowUpTask(
        SdlRun $run,
        SdlStageEntry $entry,
        SdlException $exception,
        User $actor,
    ): int {
        $run->loadMissing('product');
        $stageLabel = Translations::get('products.sdl.stages.' . $entry->stage->value);
        if ($stageLabel === 'products.sdl.stages.' . $entry->stage->value) {
            $stageLabel = $entry->stage->value;
        }

        $existing = Task::query()
            ->where('subject_type', SdlException::class)
            ->where('subject_id', $exception->id)
            ->whereIn('status', [
                TaskStatus::Open->value,
                TaskStatus::InProgress->value,
                TaskStatus::PendingApproval->value,
            ])
            ->latest('id')
            ->first();

        if ($existing !== null) {
            $existing->update([
                'assignee_user_id' => $exception->owner_user_id,
                'due_at' => $exception->expires_at->toDateString(),
                'title' => Translations::get('products.sdl.exception_task_title', [
                    'run' => $run->title,
                    'stage' => $stageLabel,
                ]),
                'description' => Translations::get('products.sdl.exception_task_description', [
                    'run' => $run->title,
                    'stage' => $stageLabel,
                    'notes' => (string) ($entry->notes ?? ''),
                ]),
            ]);

            return $existing->id;
        }

        $task = $this->tasks->create($run->product, [
            'title' => Translations::get('products.sdl.exception_task_title', [
                'run' => $run->title,
                'stage' => $stageLabel,
            ]),
            'description' => Translations::get('products.sdl.exception_task_description', [
                'run' => $run->title,
                'stage' => $stageLabel,
                'notes' => (string) ($entry->notes ?? ''),
            ]),
            'status' => TaskStatus::Open,
            'priority' => TaskPriority::Medium,
            'assignee_user_id' => $exception->owner_user_id,
            'due_at' => $exception->expires_at->toDateString(),
            'subject_type' => 'sdl_exception',
            'subject_id' => $exception->id,
        ], $actor);

        return $task->id;
    }

    private function clearStageException(SdlStageEntry $entry): void
    {
        $exception = $entry->exception()->first();

        if ($exception === null) {
            return;
        }

        Task::query()
            ->where('subject_type', SdlException::class)
            ->where('subject_id', $exception->id)
            ->whereIn('status', [
                TaskStatus::Open->value,
                TaskStatus::InProgress->value,
                TaskStatus::PendingApproval->value,
            ])
            ->update([
                'status' => TaskStatus::Cancelled->value,
            ]);

        $exception->delete();
    }

    /**
     * @return array{id: int, product_id: int, title: string, status: string}|null
     */
    public function openExceptionTaskPayload(SdlException $exception): ?array
    {
        $task = Task::query()
            ->where('subject_type', SdlException::class)
            ->where('subject_id', $exception->id)
            ->whereIn('status', [
                TaskStatus::Open->value,
                TaskStatus::InProgress->value,
                TaskStatus::PendingApproval->value,
            ])
            ->latest('id')
            ->first(['id', 'product_id', 'title', 'status']);

        if ($task === null) {
            return null;
        }

        return [
            'id' => $task->id,
            'product_id' => $task->product_id,
            'title' => $task->title,
            'status' => $task->status->value,
        ];
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
            $run->load([
                'stageEntries.completer',
                'stageEntries.evidence',
                'stageEntries.exception.owner',
            ]);
        } elseif (
            $run->stageEntries->isNotEmpty()
            && (
                !$run->stageEntries->first()?->relationLoaded('completer')
                || !$run->stageEntries->first()?->relationLoaded('evidence')
                || !$run->stageEntries->first()?->relationLoaded('exception')
            )
        ) {
            $run->load([
                'stageEntries.completer',
                'stageEntries.evidence',
                'stageEntries.exception.owner',
            ]);
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
            'version_number' => $run->version?->version_number,
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
                    $exception = $entry?->exception;

                    return [
                        'id' => $entry?->id,
                        'stage' => $stage->value,
                        'status' => $entry?->status->value ?? 'pending',
                        'completed_at' => $entry?->completed_at?->toIso8601String(),
                        'completed_by' => $entry?->completed_by,
                        'completed_by_name' => $entry?->completer?->name,
                        'notes' => $entry?->notes,
                        'evidence_ids' => $entry?->evidence->pluck('id')->all() ?? [],
                        'exception' => $exception === null
                            ? null
                            : [
                                'id' => $exception->id,
                                'owner_user_id' => $exception->owner_user_id,
                                'owner_name' => $exception->owner?->name,
                                'expires_at' => $exception->expires_at->toDateString(),
                                'is_expired' => $exception->isExpired(),
                                'task' => $this->openExceptionTaskPayload($exception),
                            ],
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
                'product_version_id' => [Translations::get('products.sdl.invalid_version')],
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
