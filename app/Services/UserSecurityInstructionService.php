<?php

namespace App\Services;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\UserSecurityInstructionSectionKey;
use App\Enums\UserSecurityInstructionStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Task;
use App\Models\User;
use App\Models\UserSecurityInstruction;
use App\Models\UserSecurityInstructionSection;
use App\Support\AuditLogger;
use App\Support\Translations;
use App\Support\UserSecurityInstructionTemplates;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserSecurityInstructionService
{
    public function __construct(
        private readonly EvidenceService $evidence,
        private readonly TaskService $tasks,
    ) {
    }

    /**
     * @return LengthAwarePaginator<int, array{
     *     id: int,
     *     title: string,
     *     status: string,
     *     version_label: string,
     *     locale: string,
     *     product_version_id: int|null,
     *     product_version_number: string|null,
     *     published_at: string|null,
     *     updated_at: string|null
     * }>
     */
    public function paginate(
        Product $product,
        int $perPage = 10,
        int $page = 1,
        string $sortBy = 'updated_at',
        string $sortOrder = 'desc',
        string $search = '',
        ?int $productVersionId = null,
        bool $productWideOnly = false,
    ): LengthAwarePaginator {
        $query = UserSecurityInstruction::query()
            ->with(['productVersion:id,version_number', 'pairedInstruction:id,locale,title,status'])
            ->where('product_id', $product->id);

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
                    ->orWhere('version_label', 'like', "%{$search}%")
                    ->orWhere('locale', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas(
                        'productVersion',
                        fn($versionQuery) => $versionQuery->where('version_number', 'like', "%{$search}%"),
                    );

                if (ctype_digit($search)) {
                    $builder->orWhere('id', (int) $search);
                }
            });
        }

        $orderColumn = match ($sortBy) {
            'id' => 'id',
            'title' => 'title',
            'status' => 'status',
            'version_label' => 'version_label',
            'locale' => 'locale',
            'published_at' => 'published_at',
            'product_version_number' => 'product_version_id',
            default => 'updated_at',
        };

        $query->orderBy($orderColumn, $sortOrder === 'desc' ? 'desc' : 'asc');

        return $query
            ->paginate($perPage, ['*'], 'page', $page)
            ->through(fn(UserSecurityInstruction $instruction) => $this->listItemPayload($instruction));
    }

    /**
     * @param  array{
     *     title?: string|null,
     *     version_label?: string|null,
     *     locale: string,
     *     notes?: string|null,
     *     use_template?: bool,
     *     product_version_id?: int|null
     * }  $attributes
     */
    public function create(Product $product, array $attributes, User $actor): UserSecurityInstruction
    {
        return DB::transaction(function () use ($product, $attributes, $actor): UserSecurityInstruction {
            $locale = $attributes['locale'];
            $useTemplate = (bool) ($attributes['use_template'] ?? false);
            $template = $useTemplate ? UserSecurityInstructionTemplates::for($locale) : null;

            $title = trim((string) ($attributes['title'] ?? ''));
            $versionLabel = trim((string) ($attributes['version_label'] ?? ''));

            if ($template !== null) {
                $title = $title !== '' ? $title : $template['title'];
                $versionLabel = $versionLabel !== '' ? $versionLabel : $template['version_label'];
            }

            $instruction = UserSecurityInstruction::query()->create([
                'organization_id' => $product->organization_id,
                'product_id' => $product->id,
                'product_version_id' => $attributes['product_version_id'] ?? null,
                'title' => $title,
                'status' => UserSecurityInstructionStatus::Draft,
                'version_label' => $versionLabel,
                'locale' => $locale,
                'notes' => $attributes['notes'] ?? null,
                'supersedes_id' => $this->findPublishedSibling(
                    $product,
                    $locale,
                    $attributes['product_version_id'] ?? null,
                )?->id,
            ]);

            $bodiesByKey = [];
            if ($template !== null) {
                foreach ($template['sections'] as $section) {
                    $bodiesByKey[$section['section_key']] = $section['body'];
                }
            }

            foreach (UserSecurityInstructionSectionKey::ordered() as $key) {
                UserSecurityInstructionSection::query()->create([
                    'instruction_id' => $instruction->id,
                    'section_key' => $key,
                    'title_override' => null,
                    'body' => $bodiesByKey[$key->value] ?? '',
                    'sort_order' => $key->defaultSortOrder(),
                    'is_applicable' => true,
                ]);
            }

            AuditLogger::logUserSecurityInstructionCreated($instruction, $actor);

            return $instruction->load('sections');
        });
    }

    /**
     * Create or link the opposite-locale document for the same product / version pin.
     */
    public function createPairedTranslation(
        UserSecurityInstruction $instruction,
        User $actor,
    ): UserSecurityInstruction {
        $instruction->loadMissing('product');

        if ($instruction->paired_instruction_id !== null) {
            throw ValidationException::withMessages([
                'paired_instruction_id' => [Translations::get('products.user_security_instructions.already_paired')],
            ]);
        }

        $oppositeLocale = $this->oppositeLocale($instruction->locale);
        if ($oppositeLocale === null) {
            throw ValidationException::withMessages([
                'locale' => [Translations::get('products.user_security_instructions.pair_locale_unsupported')],
            ]);
        }

        return DB::transaction(function () use ($instruction, $actor, $oppositeLocale): UserSecurityInstruction {
            $existing = $this->findUnpairedOppositeLocaleSibling($instruction, $oppositeLocale);

            if ($existing !== null) {
                $this->linkPair($instruction, $existing);

                return $existing->fresh(['sections', 'pairedInstruction']);
            }

            $paired = $this->create(
                $instruction->product,
                [
                    'title' => '',
                    'version_label' => $instruction->version_label,
                    'locale' => $oppositeLocale,
                    'notes' => $instruction->notes,
                    'use_template' => true,
                    'product_version_id' => $instruction->product_version_id,
                ],
                $actor,
            );

            $this->linkPair($instruction, $paired);

            return $paired->fresh(['sections', 'pairedInstruction']);
        });
    }

    private function linkPair(
        UserSecurityInstruction $left,
        UserSecurityInstruction $right,
    ): void {
        if ($left->product_id !== $right->product_id) {
            throw ValidationException::withMessages([
                'paired_instruction_id' => [Translations::get('products.user_security_instructions.pair_product_mismatch')],
            ]);
        }

        if ($left->product_version_id !== $right->product_version_id) {
            throw ValidationException::withMessages([
                'paired_instruction_id' => [Translations::get('products.user_security_instructions.pair_version_mismatch')],
            ]);
        }

        if ($left->locale === $right->locale) {
            throw ValidationException::withMessages([
                'paired_instruction_id' => [Translations::get('products.user_security_instructions.pair_same_locale')],
            ]);
        }

        if (
            ($left->paired_instruction_id !== null && $left->paired_instruction_id !== $right->id)
            || ($right->paired_instruction_id !== null && $right->paired_instruction_id !== $left->id)
        ) {
            throw ValidationException::withMessages([
                'paired_instruction_id' => [Translations::get('products.user_security_instructions.already_paired')],
            ]);
        }

        $left->update(['paired_instruction_id' => $right->id]);
        $right->update(['paired_instruction_id' => $left->id]);
    }

    private function oppositeLocale(string $locale): ?string
    {
        return match ($locale) {
            'en' => 'bg',
            'bg' => 'en',
            default => null,
        };
    }

    private function findUnpairedOppositeLocaleSibling(
        UserSecurityInstruction $instruction,
        string $oppositeLocale,
    ): ?UserSecurityInstruction {
        $query = UserSecurityInstruction::query()
            ->where('product_id', $instruction->product_id)
            ->where('locale', $oppositeLocale)
            ->whereNull('paired_instruction_id')
            ->whereKeyNot($instruction->id)
            ->whereIn('status', [
                UserSecurityInstructionStatus::Draft->value,
                UserSecurityInstructionStatus::UnderReview->value,
                UserSecurityInstructionStatus::Published->value,
            ])
            ->orderByDesc('id');

        if ($instruction->product_version_id === null) {
            $query->whereNull('product_version_id');
        } else {
            $query->where('product_version_id', $instruction->product_version_id);
        }

        return $query->first();
    }

    /**
     * @return array{
     *     title: string,
     *     version_label: string,
     *     sections: list<array{section_key: string, body: string}>
     * }
     */
    public function templatePayload(string $locale = 'en'): array
    {
        return UserSecurityInstructionTemplates::for($locale);
    }

    /**
     * @param  array{
     *     title: string,
     *     version_label: string,
     *     locale: string,
     *     notes?: string|null,
     *     product_version_id?: int|null,
     *     sections: list<array{
     *         section_key: string,
     *         body?: string|null,
     *         title_override?: string|null,
     *         is_applicable?: bool,
     *         sort_order?: int
     *     }>
     * }  $attributes
     */
    public function update(
        UserSecurityInstruction $instruction,
        array $attributes,
        User $actor,
    ): UserSecurityInstruction {
        $this->assertEditable($instruction);

        return DB::transaction(function () use ($instruction, $attributes, $actor): UserSecurityInstruction {
            $locale = $attributes['locale'];
            $productVersionId = array_key_exists('product_version_id', $attributes)
                ? $attributes['product_version_id']
                : $instruction->product_version_id;

            $instruction->loadMissing('pairedInstruction');
            $paired = $instruction->pairedInstruction;
            if ($paired !== null) {
                if ($locale === $paired->locale) {
                    throw ValidationException::withMessages([
                        'locale' => [Translations::get('products.user_security_instructions.pair_same_locale')],
                    ]);
                }

                if ($productVersionId !== $paired->product_version_id) {
                    throw ValidationException::withMessages([
                        'product_version_id' => [Translations::get('products.user_security_instructions.pair_version_mismatch')],
                    ]);
                }
            }

            $scopeChanged = $locale !== $instruction->locale
                || $productVersionId !== $instruction->product_version_id;

            $publishedSibling = $this->findPublishedSibling(
                $instruction->product,
                $locale,
                $productVersionId,
                $instruction->id,
            );

            $supersedesId = $publishedSibling?->id;
            if ($supersedesId === null && !$scopeChanged) {
                $supersedesId = $instruction->supersedes_id;
            }

            $instruction->update([
                'title' => $attributes['title'],
                'version_label' => $attributes['version_label'],
                'locale' => $locale,
                'notes' => $attributes['notes'] ?? null,
                'product_version_id' => $productVersionId,
                'supersedes_id' => $supersedesId,
            ]);

            $sectionsByKey = $instruction->sections()
                ->get()
                ->keyBy(fn(UserSecurityInstructionSection $section) => $section->section_key->value);

            foreach ($attributes['sections'] as $sectionData) {
                $key = $sectionData['section_key'];
                $section = $sectionsByKey->get($key);

                if ($section === null) {
                    $enumKey = UserSecurityInstructionSectionKey::from($key);
                    $section = UserSecurityInstructionSection::query()->create([
                        'instruction_id' => $instruction->id,
                        'section_key' => $enumKey,
                        'title_override' => $sectionData['title_override'] ?? null,
                        'body' => (string) ($sectionData['body'] ?? ''),
                        'sort_order' => $sectionData['sort_order'] ?? $enumKey->defaultSortOrder(),
                        'is_applicable' => (bool) ($sectionData['is_applicable'] ?? true),
                    ]);
                    $sectionsByKey->put($key, $section);

                    continue;
                }

                $section->update([
                    'title_override' => $sectionData['title_override'] ?? null,
                    'body' => (string) ($sectionData['body'] ?? ''),
                    'sort_order' => $sectionData['sort_order'] ?? $section->sort_order,
                    'is_applicable' => (bool) ($sectionData['is_applicable'] ?? true),
                ]);
            }

            $fresh = $instruction->fresh(['sections']);
            AuditLogger::logUserSecurityInstructionUpdated($fresh, $actor);

            return $fresh;
        });
    }

    public function delete(UserSecurityInstruction $instruction, User $actor): void
    {
        if (!$instruction->isEditable()) {
            throw ValidationException::withMessages([
                'status' => [Translations::get('products.user_security_instructions.cannot_delete_locked')],
            ]);
        }

        AuditLogger::logUserSecurityInstructionDeleted($instruction, $actor);
        $instruction->delete();
    }

    public function submitForReview(
        UserSecurityInstruction $instruction,
        User $actor,
        ?int $assigneeUserId = null,
    ): UserSecurityInstruction {
        if ($instruction->status !== UserSecurityInstructionStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => [Translations::get('products.user_security_instructions.only_draft_submit')],
            ]);
        }

        $instruction->loadMissing('product');

        if ($assigneeUserId !== null) {
            $assigneeBelongs = Organization::query()
                ->whereKey($instruction->organization_id)
                ->whereHas(
                    'users',
                    fn($query) => $query->where('users.id', $assigneeUserId),
                )
                ->exists();

            if (!$assigneeBelongs) {
                throw ValidationException::withMessages([
                    'assignee_user_id' => [Translations::get('products.user_security_instructions.submit_assignee_invalid')],
                ]);
            }
        }

        return DB::transaction(function () use ($instruction, $actor, $assigneeUserId): UserSecurityInstruction {
            $instruction->update(['status' => UserSecurityInstructionStatus::UnderReview]);
            $fresh = $instruction->fresh(['sections', 'publisher:id,name', 'product']);

            $this->tasks->create($fresh->product, [
                'title' => Translations::get('products.user_security_instructions.review_task_title', [
                    'title' => $fresh->title,
                    'version' => $fresh->version_label,
                ]),
                'description' => Translations::get('products.user_security_instructions.review_task_description', [
                    'title' => $fresh->title,
                    'version' => $fresh->version_label,
                    'locale' => $fresh->locale,
                ]),
                'status' => TaskStatus::Open,
                'priority' => TaskPriority::Medium,
                'assignee_user_id' => $assigneeUserId ?? $actor->id,
                'due_at' => now()->addDays(7),
                'subject_type' => 'user_security_instruction',
                'subject_id' => $fresh->id,
            ], $actor);

            AuditLogger::logUserSecurityInstructionSubmitted($fresh, $actor);

            return $fresh;
        });
    }

    /**
     * @return array{id: int, product_id: int, title: string, status: string}|null
     */
    public function openReviewTaskPayload(UserSecurityInstruction $instruction): ?array
    {
        $task = Task::query()
            ->where('subject_type', UserSecurityInstruction::class)
            ->where('subject_id', $instruction->id)
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

    private function completeOpenReviewTasks(UserSecurityInstruction $instruction): void
    {
        Task::query()
            ->where('subject_type', UserSecurityInstruction::class)
            ->where('subject_id', $instruction->id)
            ->whereIn('status', [
                TaskStatus::Open->value,
                TaskStatus::InProgress->value,
                TaskStatus::PendingApproval->value,
            ])
            ->update([
                'status' => TaskStatus::Completed->value,
            ]);
    }

    public function publish(UserSecurityInstruction $instruction, User $actor): UserSecurityInstruction
    {
        if (
            !in_array($instruction->status, [
                UserSecurityInstructionStatus::Draft,
                UserSecurityInstructionStatus::UnderReview,
            ], true)
        ) {
            throw ValidationException::withMessages([
                'status' => [Translations::get('products.user_security_instructions.only_editable_publish')],
            ]);
        }

        $this->assertPublishableSections($instruction);

        return DB::transaction(function () use ($instruction, $actor): UserSecurityInstruction {
            $previous = $this->findPublishedSibling(
                $instruction->product,
                $instruction->locale,
                $instruction->product_version_id,
                $instruction->id,
            );

            $siblings = UserSecurityInstruction::query()
                ->where('product_id', $instruction->product_id)
                ->where('locale', $instruction->locale)
                ->where('status', UserSecurityInstructionStatus::Published->value)
                ->whereKeyNot($instruction->id);

            if ($instruction->product_version_id === null) {
                $siblings->whereNull('product_version_id');
            } else {
                $siblings->where('product_version_id', $instruction->product_version_id);
            }

            $siblings->update([
                'status' => UserSecurityInstructionStatus::Retired->value,
            ]);

            $instruction->update([
                'status' => UserSecurityInstructionStatus::Published,
                'published_at' => now(),
                'published_by' => $actor->id,
                'supersedes_id' => $previous?->id ?? $instruction->supersedes_id,
            ]);

            $this->completeOpenReviewTasks($instruction);

            $fresh = $instruction->fresh(['sections', 'publisher:id,name', 'supersedes.sections']);
            AuditLogger::logUserSecurityInstructionPublished($fresh, $actor);

            return $fresh;
        });
    }

    public function retire(UserSecurityInstruction $instruction, User $actor): UserSecurityInstruction
    {
        if ($instruction->status !== UserSecurityInstructionStatus::Published) {
            throw ValidationException::withMessages([
                'status' => [Translations::get('products.user_security_instructions.only_published_retire')],
            ]);
        }

        $instruction->update(['status' => UserSecurityInstructionStatus::Retired]);
        $fresh = $instruction->fresh(['sections', 'publisher:id,name', 'evidence']);
        AuditLogger::logUserSecurityInstructionRetired($fresh, $actor);

        return $fresh;
    }

    public function publishEvidence(
        UserSecurityInstruction $instruction,
        Product $product,
        User $actor,
    ): UserSecurityInstruction {
        if ($instruction->status !== UserSecurityInstructionStatus::Published) {
            throw ValidationException::withMessages([
                'status' => [Translations::get('products.user_security_instructions.only_published_evidence')],
            ]);
        }

        if ($instruction->evidence_id !== null) {
            throw ValidationException::withMessages([
                'evidence_id' => [Translations::get('products.user_security_instructions.already_published_evidence')],
            ]);
        }

        if ($product->id !== $instruction->product_id) {
            throw ValidationException::withMessages([
                'product_id' => [Translations::get('products.user_security_instructions.publish_product_invalid')],
            ]);
        }

        return DB::transaction(function () use ($instruction, $product, $actor): UserSecurityInstruction {
            $evidence = $this->evidence->createFromUserSecurityInstruction(
                $product,
                $instruction,
                $actor,
            );

            $instruction->update(['evidence_id' => $evidence->id]);

            $fresh = $instruction->fresh(['sections', 'publisher:id,name', 'evidence']);
            AuditLogger::logUserSecurityInstructionPublishedEvidence($fresh, $evidence, $actor);

            return $fresh;
        });
    }

    private function assertPublishableSections(UserSecurityInstruction $instruction): void
    {
        $instruction->loadMissing('sections');

        $incomplete = $instruction->sections
            ->filter(fn(UserSecurityInstructionSection $section) => $section->is_applicable
                && trim($section->body) === '')
            ->map(fn(UserSecurityInstructionSection $section) => $section->section_key->value)
            ->values()
            ->all();

        if ($incomplete !== []) {
            throw ValidationException::withMessages([
                'sections' => [
                    Translations::get('products.user_security_instructions.publish_sections_incomplete', [
                        'sections' => implode(', ', $incomplete),
                    ])
                ],
            ]);
        }
    }

    /**
     * @return array{
     *     id: int,
     *     title: string,
     *     status: string,
     *     version_label: string,
     *     locale: string,
     *     notes: string|null,
     *     is_editable: bool,
     *     published_at: string|null,
     *     published_by_name: string|null,
     *     evidence_id: int|null,
     *     evidence_title: string|null,
     *     product_version_id: int|null,
     *     product_version_number: string|null,
     *     supersedes_id: int|null,
     *     supersedes_title: string|null,
     *     supersedes_sections: array<string, array{
     *         body: string,
     *         title_override: string|null,
     *         is_applicable: bool
     *     }>,
     *     sections: list<array{
     *         id: int,
     *         section_key: string,
     *         title_override: string|null,
     *         body: string,
     *         sort_order: int,
     *         is_applicable: bool
     *     }>
     * }
     */
    public function detailPayload(UserSecurityInstruction $instruction): array
    {
        $instruction->loadMissing([
            'sections',
            'publisher:id,name',
            'evidence',
            'productVersion:id,version_number',
            'supersedes.sections',
            'pairedInstruction',
        ]);

        $previous = $instruction->supersedes;
        $paired = $instruction->pairedInstruction;

        return [
            'id' => $instruction->id,
            'title' => $instruction->title,
            'status' => $instruction->status->value,
            'version_label' => $instruction->version_label,
            'locale' => $instruction->locale,
            'notes' => $instruction->notes,
            'is_editable' => $instruction->isEditable(),
            'published_at' => $instruction->published_at?->toIso8601String(),
            'published_by_name' => $instruction->publisher?->name,
            'evidence_id' => $instruction->evidence_id,
            'evidence_title' => $instruction->evidence?->title,
            'product_version_id' => $instruction->product_version_id,
            'product_version_number' => $instruction->productVersion?->version_number,
            'supersedes_id' => $instruction->supersedes_id,
            'supersedes_title' => $previous
                ? $previous->title . ' (' . $previous->version_label . ')'
                : null,
            'supersedes_sections' => $previous
                ? $previous->sections
                    ->keyBy(fn(UserSecurityInstructionSection $section) => $section->section_key->value)
                    ->map(fn(UserSecurityInstructionSection $section) => [
                        'body' => $section->body,
                        'title_override' => $section->title_override,
                        'is_applicable' => $section->is_applicable,
                    ])
                    ->all()
                : [],
            'paired_instruction_id' => $paired?->id,
            'paired_locale' => $paired?->locale,
            'paired_title' => $paired?->title,
            'paired_status' => $paired?->status->value,
            'paired_version_label' => $paired?->version_label,
            'sections' => $instruction->sections
                ->sortBy('sort_order')
                ->values()
                ->map(fn(UserSecurityInstructionSection $section) => [
                    'id' => $section->id,
                    'section_key' => $section->section_key->value,
                    'title_override' => $section->title_override,
                    'body' => $section->body,
                    'sort_order' => $section->sort_order,
                    'is_applicable' => $section->is_applicable,
                ])
                ->all(),
        ];
    }

    /**
     * Published instruction in the same product / locale / version-pin scope.
     */
    private function findPublishedSibling(
        Product $product,
        string $locale,
        ?int $productVersionId,
        ?int $exceptId = null,
    ): ?UserSecurityInstruction {
        $query = UserSecurityInstruction::query()
            ->where('product_id', $product->id)
            ->where('locale', $locale)
            ->where('status', UserSecurityInstructionStatus::Published->value)
            ->orderByDesc('id');

        if ($productVersionId === null) {
            $query->whereNull('product_version_id');
        } else {
            $query->where('product_version_id', $productVersionId);
        }

        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }

        return $query->first();
    }

    /**
     * @return array{
     *     id: int,
     *     title: string,
     *     status: string,
     *     version_label: string,
     *     locale: string,
     *     product_version_id: int|null,
     *     product_version_number: string|null,
     *     published_at: string|null,
     *     updated_at: string|null
     * }
     */
    public function listItemPayload(UserSecurityInstruction $instruction): array
    {
        $instruction->loadMissing(['productVersion:id,version_number', 'pairedInstruction:id,locale,title,status']);

        return [
            'id' => $instruction->id,
            'title' => $instruction->title,
            'status' => $instruction->status->value,
            'version_label' => $instruction->version_label,
            'locale' => $instruction->locale,
            'product_version_id' => $instruction->product_version_id,
            'product_version_number' => $instruction->productVersion?->version_number,
            'paired_instruction_id' => $instruction->paired_instruction_id,
            'paired_locale' => $instruction->pairedInstruction?->locale,
            'published_at' => $instruction->published_at?->toIso8601String(),
            'updated_at' => $instruction->updated_at?->toIso8601String(),
        ];
    }

    private function assertEditable(UserSecurityInstruction $instruction): void
    {
        if (!$instruction->isEditable()) {
            throw ValidationException::withMessages([
                'status' => [Translations::get('products.user_security_instructions.cannot_edit_locked')],
            ]);
        }
    }
}
