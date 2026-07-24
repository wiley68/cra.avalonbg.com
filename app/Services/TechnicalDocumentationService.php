<?php

namespace App\Services;

use App\Enums\TechnicalDocumentationSectionKey;
use App\Enums\TechnicalDocumentationSectionSource;
use App\Enums\TechnicalDocumentationStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Task;
use App\Models\TechnicalDocumentationPackage;
use App\Models\TechnicalDocumentationSection;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Translations;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TechnicalDocumentationService
{
    public function __construct(
        private readonly TechnicalDocumentationGeneratorService $generator,
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
     *     updated_at: string|null,
     *     sections_count: int
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
        $query = TechnicalDocumentationPackage::query()
            ->with(['productVersion:id,version_number'])
            ->withCount('sections')
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
            ->through(fn(TechnicalDocumentationPackage $package) => $this->listItemPayload($package));
    }

    /**
     * @param  array{
     *     title: string,
     *     version_label: string,
     *     locale: string,
     *     notes?: string|null,
     *     product_version_id?: int|null,
     *     inherit_from_previous?: bool
     * }  $attributes
     */
    public function create(Product $product, array $attributes, User $actor): TechnicalDocumentationPackage
    {
        return DB::transaction(function () use ($product, $attributes, $actor): TechnicalDocumentationPackage {
            $locale = $attributes['locale'];
            $productVersionId = $attributes['product_version_id'] ?? null;
            $inherit = (bool) ($attributes['inherit_from_previous'] ?? true);

            $parent = $this->findPreviousPublished(
                $product,
                $locale,
                $productVersionId,
            );

            $package = TechnicalDocumentationPackage::query()->create([
                'organization_id' => $product->organization_id,
                'product_id' => $product->id,
                'product_version_id' => $productVersionId,
                'title' => trim($attributes['title']),
                'status' => TechnicalDocumentationStatus::Draft,
                'version_label' => trim($attributes['version_label']),
                'locale' => $locale,
                'notes' => $attributes['notes'] ?? null,
                'supersedes_id' => $parent?->id,
            ]);

            $parentSections = collect();
            if ($inherit && $parent !== null) {
                $parent->loadMissing('sections');
                $parentSections = $parent->sections
                    ->keyBy(fn(TechnicalDocumentationSection $section) => $section->section_key->value);
            }

            foreach (TechnicalDocumentationSectionKey::ordered() as $key) {
                /** @var TechnicalDocumentationSection|null $from */
                $from = $inherit ? $parentSections->get($key->value) : null;

                TechnicalDocumentationSection::query()->create([
                    'package_id' => $package->id,
                    'section_key' => $key,
                    'source' => $from?->source ?? $key->defaultSource(),
                    'body_markdown' => $from?->body_markdown,
                    'generated_payload' => $from?->generated_payload,
                    'sort_order' => $from?->sort_order ?? $key->defaultSortOrder(),
                    'is_applicable' => $from?->is_applicable ?? true,
                    'override_reason' => $from?->override_reason,
                    'changed_since_parent' => false,
                ]);
            }

            $package->load(['sections', 'product.productOwner:id,name', 'product.securityContact:id,name']);
            $this->generator->refreshPackage($package);

            if ($parent !== null) {
                $this->syncChangedSinceParentFlags($package->fresh(['sections']), $parent);
            }

            AuditLogger::logTechnicalDocumentationCreated($package, $actor);

            return $package->fresh(['sections', 'supersedes']);
        });
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
     *         body_markdown?: string|null,
     *         is_applicable?: bool,
     *         override_reason?: string|null,
     *         sort_order?: int
     *     }>
     * }  $attributes
     */
    public function update(
        TechnicalDocumentationPackage $package,
        array $attributes,
        User $actor,
    ): TechnicalDocumentationPackage {
        $this->assertEditable($package);

        return DB::transaction(function () use ($package, $attributes, $actor): TechnicalDocumentationPackage {
            $locale = $attributes['locale'];
            $productVersionId = array_key_exists('product_version_id', $attributes)
                ? $attributes['product_version_id']
                : $package->product_version_id;

            $package->loadMissing('product');

            $previous = $this->findPreviousPublished(
                $package->product,
                $locale,
                $productVersionId,
                $package->id,
            );

            $package->update([
                'title' => trim($attributes['title']),
                'version_label' => trim($attributes['version_label']),
                'locale' => $locale,
                'notes' => $attributes['notes'] ?? null,
                'product_version_id' => $productVersionId,
                'supersedes_id' => $previous?->id,
            ]);

            $previous?->loadMissing('sections');
            $parentSections = $previous?->sections
                ->keyBy(fn(TechnicalDocumentationSection $section) => $section->section_key->value)
                ?? collect();

            $sectionsByKey = $package->sections()
                ->get()
                ->keyBy(fn(TechnicalDocumentationSection $section) => $section->section_key->value);

            foreach ($attributes['sections'] as $sectionData) {
                $key = $sectionData['section_key'];
                $section = $sectionsByKey->get($key);

                if ($section === null) {
                    continue;
                }

                $isApplicable = (bool) ($sectionData['is_applicable'] ?? true);
                $overrideReason = $isApplicable
                    ? null
                    : (trim((string) ($sectionData['override_reason'] ?? '')) ?: null);

                $payload = [
                    'is_applicable' => $isApplicable,
                    'override_reason' => $overrideReason,
                    'sort_order' => $sectionData['sort_order'] ?? $section->sort_order,
                ];

                // Authored sections own body_markdown. Generated/linked keep optional
                // supplemental notes without touching generated_payload (Must 4).
                if (array_key_exists('body_markdown', $sectionData)) {
                    $body = $sectionData['body_markdown'];
                    $payload['body_markdown'] = filled($body) ? (string) $body : null;
                }

                /** @var TechnicalDocumentationSection|null $parentSection */
                $parentSection = $parentSections->get($key);
                $section->fill($payload);
                $payload['changed_since_parent'] = $this->sectionChangedSinceParent($section, $parentSection);
                $section->update($payload);
            }

            $fresh = $package->fresh(['sections', 'productVersion:id,version_number', 'publisher:id,name', 'supersedes']);

            AuditLogger::logTechnicalDocumentationUpdated($fresh, $actor);

            return $fresh;
        });
    }

    public function delete(TechnicalDocumentationPackage $package, User $actor): void
    {
        if (!$package->isEditable()) {
            throw ValidationException::withMessages([
                'status' => [Translations::get('products.technical_documentation.cannot_delete_locked')],
            ]);
        }

        AuditLogger::logTechnicalDocumentationDeleted($package, $actor);
        $package->delete();
    }

    /**
     * Refresh generated section snapshots from product modules.
     *
     * Does not overwrite authored/linked sections or supplemental body_markdown notes.
     *
     * @param  list<string>|null  $sectionKeys
     */
    public function refreshGenerated(
        TechnicalDocumentationPackage $package,
        User $actor,
        ?array $sectionKeys = null,
    ): TechnicalDocumentationPackage {
        $this->assertEditable($package);

        $keys = null;
        if ($sectionKeys !== null) {
            $keys = [];
            foreach ($sectionKeys as $value) {
                $keys[] = TechnicalDocumentationSectionKey::from($value);
            }
        }

        return DB::transaction(function () use ($package, $actor, $keys): TechnicalDocumentationPackage {
            $package->loadMissing([
                'product.productOwner:id,name',
                'product.securityContact:id,name',
                'sections',
                'supersedes.sections',
            ]);
            $result = $this->generator->refreshPackage($package, $keys);

            if ($package->supersedes !== null) {
                $this->syncChangedSinceParentFlags($package->fresh(['sections']), $package->supersedes);
            }

            $fresh = $package->fresh([
                'sections',
                'productVersion:id,version_number',
                'publisher:id,name',
                'supersedes',
            ]);

            AuditLogger::logTechnicalDocumentationGeneratedRefreshed(
                $fresh,
                $actor,
                $result['refreshed'],
                $result['skipped'],
            );

            return $fresh;
        });
    }

    public function submitForReview(
        TechnicalDocumentationPackage $package,
        User $actor,
        ?int $assigneeUserId = null,
    ): TechnicalDocumentationPackage {
        if ($package->status !== TechnicalDocumentationStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => [Translations::get('products.technical_documentation.only_draft_submit')],
            ]);
        }

        $package->loadMissing('product');

        if ($assigneeUserId !== null) {
            $assigneeBelongs = Organization::query()
                ->whereKey($package->organization_id)
                ->whereHas(
                    'users',
                    fn($query) => $query->where('users.id', $assigneeUserId),
                )
                ->exists();

            if (!$assigneeBelongs) {
                throw ValidationException::withMessages([
                    'assignee_user_id' => [Translations::get('products.technical_documentation.submit_assignee_invalid')],
                ]);
            }
        }

        return DB::transaction(function () use ($package, $actor, $assigneeUserId): TechnicalDocumentationPackage {
            $package->update(['status' => TechnicalDocumentationStatus::UnderReview]);
            $fresh = $package->fresh([
                'sections',
                'productVersion:id,version_number',
                'publisher:id,name',
                'product',
            ]);

            $this->tasks->create($fresh->product, [
                'title' => Translations::get('products.technical_documentation.review_task_title', [
                    'title' => $fresh->title,
                    'version' => $fresh->version_label,
                ]),
                'description' => Translations::get('products.technical_documentation.review_task_description', [
                    'title' => $fresh->title,
                    'version' => $fresh->version_label,
                    'locale' => $fresh->locale,
                ]),
                'status' => TaskStatus::Open,
                'priority' => TaskPriority::Medium,
                'assignee_user_id' => $assigneeUserId ?? $actor->id,
                'due_at' => now()->addDays(7),
                'subject_type' => 'technical_documentation_package',
                'subject_id' => $fresh->id,
            ], $actor);

            AuditLogger::logTechnicalDocumentationSubmitted($fresh, $actor);

            return $fresh;
        });
    }

    /**
     * @return array{id: int, product_id: int, title: string, status: string}|null
     */
    public function openReviewTaskPayload(TechnicalDocumentationPackage $package): ?array
    {
        $task = Task::query()
            ->where('subject_type', TechnicalDocumentationPackage::class)
            ->where('subject_id', $package->id)
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

    public function publish(
        TechnicalDocumentationPackage $package,
        User $actor,
    ): TechnicalDocumentationPackage {
        if (
            !in_array($package->status, [
                TechnicalDocumentationStatus::Draft,
                TechnicalDocumentationStatus::UnderReview,
            ], true)
        ) {
            throw ValidationException::withMessages([
                'status' => [Translations::get('products.technical_documentation.only_editable_publish')],
            ]);
        }

        $this->assertPublishableSections($package);

        return DB::transaction(function () use ($package, $actor): TechnicalDocumentationPackage {
            $package->loadMissing('product');

            $previous = $this->findPreviousPublished(
                $package->product,
                $package->locale,
                $package->product_version_id,
                $package->id,
            );

            $siblings = TechnicalDocumentationPackage::query()
                ->where('product_id', $package->product_id)
                ->where('locale', $package->locale)
                ->where('status', TechnicalDocumentationStatus::Published->value)
                ->whereKeyNot($package->id);

            if ($package->product_version_id === null) {
                $siblings->whereNull('product_version_id');
            } else {
                $siblings->where('product_version_id', $package->product_version_id);
            }

            $siblings->update([
                'status' => TechnicalDocumentationStatus::Retired->value,
            ]);

            $package->update([
                'status' => TechnicalDocumentationStatus::Published,
                'published_at' => now(),
                'published_by' => $actor->id,
                'supersedes_id' => $previous?->id ?? $package->supersedes_id,
            ]);

            $this->completeOpenReviewTasks($package);

            $fresh = $package->fresh([
                'sections',
                'productVersion:id,version_number',
                'publisher:id,name',
                'supersedes',
            ]);
            AuditLogger::logTechnicalDocumentationPublished($fresh, $actor);

            return $fresh;
        });
    }

    public function retire(
        TechnicalDocumentationPackage $package,
        User $actor,
    ): TechnicalDocumentationPackage {
        if ($package->status !== TechnicalDocumentationStatus::Published) {
            throw ValidationException::withMessages([
                'status' => [Translations::get('products.technical_documentation.only_published_retire')],
            ]);
        }

        $package->update(['status' => TechnicalDocumentationStatus::Retired]);
        $fresh = $package->fresh([
            'sections',
            'productVersion:id,version_number',
            'publisher:id,name',
            'supersedes',
        ]);
        AuditLogger::logTechnicalDocumentationRetired($fresh, $actor);

        return $fresh;
    }

    private function completeOpenReviewTasks(TechnicalDocumentationPackage $package): void
    {
        Task::query()
            ->where('subject_type', TechnicalDocumentationPackage::class)
            ->where('subject_id', $package->id)
            ->whereIn('status', [
                TaskStatus::Open->value,
                TaskStatus::InProgress->value,
                TaskStatus::PendingApproval->value,
            ])
            ->update([
                'status' => TaskStatus::Completed->value,
            ]);
    }

    private function assertPublishableSections(TechnicalDocumentationPackage $package): void
    {
        $package->loadMissing('sections');

        $incomplete = $package->sections
            ->filter(function (TechnicalDocumentationSection $section): bool {
                if (!$section->is_applicable) {
                    return false;
                }

                return match ($section->source) {
                    TechnicalDocumentationSectionSource::Authored => trim((string) $section->body_markdown) === '',
                    TechnicalDocumentationSectionSource::Generated => $section->generated_payload === null,
                    TechnicalDocumentationSectionSource::Linked => false,
                };
            })
            ->map(fn(TechnicalDocumentationSection $section) => $section->section_key->value)
            ->values()
            ->all();

        if ($incomplete !== []) {
            throw ValidationException::withMessages([
                'sections' => [
                    Translations::get('products.technical_documentation.publish_sections_incomplete', [
                        'sections' => implode(', ', $incomplete),
                    ]),
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
     *     product_version_id: int|null,
     *     product_version_number: string|null,
     *     supersedes_id: int|null,
     *     supersedes_title: string|null,
     *     sections: list<array{
     *         id: int,
     *         section_key: string,
     *         source: string,
     *         body_markdown: string|null,
     *         generated_payload: array<string, mixed>|list<mixed>|null,
     *         sort_order: int,
     *         is_applicable: bool,
     *         override_reason: string|null,
     *         changed_since_parent: bool
     *     }>
     * }
     */
    public function detailPayload(TechnicalDocumentationPackage $package): array
    {
        $package->loadMissing([
            'sections',
            'publisher:id,name',
            'productVersion:id,version_number',
            'supersedes.sections',
        ]);

        $previous = $package->supersedes;

        return [
            'id' => $package->id,
            'title' => $package->title,
            'status' => $package->status->value,
            'version_label' => $package->version_label,
            'locale' => $package->locale,
            'notes' => $package->notes,
            'is_editable' => $package->isEditable(),
            'published_at' => $package->published_at?->toIso8601String(),
            'published_by_name' => $package->publisher?->name,
            'product_version_id' => $package->product_version_id,
            'product_version_number' => $package->productVersion?->version_number,
            'supersedes_id' => $package->supersedes_id,
            'supersedes_title' => $previous
                ? $previous->title . ' (' . $previous->version_label . ')'
                : null,
            'supersedes_sections' => $previous
                ? $previous->sections
                    ->keyBy(fn(TechnicalDocumentationSection $section) => $section->section_key->value)
                    ->map(fn(TechnicalDocumentationSection $section) => [
                        'source' => $section->source->value,
                        'body_markdown' => $section->body_markdown,
                        'generated_payload' => $section->generated_payload,
                        'is_applicable' => $section->is_applicable,
                        'override_reason' => $section->override_reason,
                    ])
                    ->all()
                : [],
            'sections' => $package->sections
                ->sortBy('sort_order')
                ->values()
                ->map(fn(TechnicalDocumentationSection $section) => [
                    'id' => $section->id,
                    'section_key' => $section->section_key->value,
                    'source' => $section->source->value,
                    'body_markdown' => $section->body_markdown,
                    'generated_payload' => $section->generated_payload,
                    'sort_order' => $section->sort_order,
                    'is_applicable' => $section->is_applicable,
                    'override_reason' => $section->override_reason,
                    'changed_since_parent' => $section->changed_since_parent,
                ])
                ->all(),
        ];
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
     *     updated_at: string|null,
     *     sections_count: int
     * }
     */
    public function listItemPayload(TechnicalDocumentationPackage $package): array
    {
        $package->loadMissing(['productVersion:id,version_number']);

        return [
            'id' => $package->id,
            'title' => $package->title,
            'status' => $package->status->value,
            'version_label' => $package->version_label,
            'locale' => $package->locale,
            'product_version_id' => $package->product_version_id,
            'product_version_number' => $package->productVersion?->version_number,
            'published_at' => $package->published_at?->toIso8601String(),
            'updated_at' => $package->updated_at?->toIso8601String(),
            'sections_count' => (int) ($package->sections_count ?? $package->sections()->count()),
        ];
    }

    /**
     * Whether the product has any published package that could be inherited from.
     */
    public function hasPublishedPrevious(Product $product, ?string $locale = null): bool
    {
        $query = TechnicalDocumentationPackage::query()
            ->where('product_id', $product->id)
            ->where('status', TechnicalDocumentationStatus::Published->value);

        if ($locale !== null) {
            $query->where('locale', $locale);
        }

        return $query->exists();
    }

    private function findPublishedSibling(
        Product $product,
        string $locale,
        ?int $productVersionId,
        ?int $exceptId = null,
    ): ?TechnicalDocumentationPackage {
        $query = TechnicalDocumentationPackage::query()
            ->where('product_id', $product->id)
            ->where('locale', $locale)
            ->where('status', TechnicalDocumentationStatus::Published->value)
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
     * Previous published package for supersedes / inherit.
     *
     * Prefer exact version-scope sibling; for version-pinned creates, fall back to
     * latest published in the same locale (e.g. product-wide or older version).
     */
    private function findPreviousPublished(
        Product $product,
        string $locale,
        ?int $productVersionId,
        ?int $exceptId = null,
    ): ?TechnicalDocumentationPackage {
        $exact = $this->findPublishedSibling(
            $product,
            $locale,
            $productVersionId,
            $exceptId,
        );

        if ($exact !== null) {
            return $exact;
        }

        if ($productVersionId === null) {
            return null;
        }

        $query = TechnicalDocumentationPackage::query()
            ->where('product_id', $product->id)
            ->where('locale', $locale)
            ->where('status', TechnicalDocumentationStatus::Published->value)
            ->orderByDesc('id');

        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }

        return $query->first();
    }

    private function syncChangedSinceParentFlags(
        TechnicalDocumentationPackage $package,
        TechnicalDocumentationPackage $parent,
    ): void {
        $parent->loadMissing('sections');
        $parentSections = $parent->sections
            ->keyBy(fn(TechnicalDocumentationSection $section) => $section->section_key->value);

        foreach ($package->sections as $section) {
            /** @var TechnicalDocumentationSection|null $parentSection */
            $parentSection = $parentSections->get($section->section_key->value);
            $changed = $this->sectionChangedSinceParent($section, $parentSection);

            if ($section->changed_since_parent !== $changed) {
                $section->update(['changed_since_parent' => $changed]);
            }
        }
    }

    private function sectionChangedSinceParent(
        TechnicalDocumentationSection $section,
        ?TechnicalDocumentationSection $parent,
    ): bool {
        if ($parent === null) {
            return false;
        }

        if ($section->source !== $parent->source) {
            return true;
        }

        if ($section->is_applicable !== $parent->is_applicable) {
            return true;
        }

        if ((string) ($section->override_reason ?? '') !== (string) ($parent->override_reason ?? '')) {
            return true;
        }

        if ((string) ($section->body_markdown ?? '') !== (string) ($parent->body_markdown ?? '')) {
            return true;
        }

        return json_encode($section->generated_payload) !== json_encode($parent->generated_payload);
    }

    private function assertEditable(TechnicalDocumentationPackage $package): void
    {
        if (!$package->isEditable()) {
            throw ValidationException::withMessages([
                'status' => [Translations::get('products.technical_documentation.cannot_edit_locked')],
            ]);
        }
    }
}
