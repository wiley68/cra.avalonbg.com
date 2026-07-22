<?php

namespace App\Services;

use App\Enums\UserSecurityInstructionSectionKey;
use App\Enums\UserSecurityInstructionStatus;
use App\Models\Product;
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
    ) {
    }

    /**
     * @return LengthAwarePaginator<int, array{
     *     id: int,
     *     title: string,
     *     status: string,
     *     version_label: string,
     *     locale: string,
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
    ): LengthAwarePaginator {
        $query = UserSecurityInstruction::query()
            ->where('product_id', $product->id);

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('version_label', 'like', "%{$search}%")
                    ->orWhere('locale', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");

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
     *     use_template?: bool
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
                'product_version_id' => null,
                'title' => $title,
                'status' => UserSecurityInstructionStatus::Draft,
                'version_label' => $versionLabel,
                'locale' => $locale,
                'notes' => $attributes['notes'] ?? null,
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
            $instruction->update([
                'title' => $attributes['title'],
                'version_label' => $attributes['version_label'],
                'locale' => $attributes['locale'],
                'notes' => $attributes['notes'] ?? null,
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

    public function submitForReview(UserSecurityInstruction $instruction, User $actor): UserSecurityInstruction
    {
        if ($instruction->status !== UserSecurityInstructionStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => [Translations::get('products.user_security_instructions.only_draft_submit')],
            ]);
        }

        $instruction->update(['status' => UserSecurityInstructionStatus::UnderReview]);
        $fresh = $instruction->fresh(['sections', 'publisher:id,name']);
        AuditLogger::logUserSecurityInstructionSubmitted($fresh, $actor);

        return $fresh;
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
            ]);

            $fresh = $instruction->fresh(['sections', 'publisher:id,name']);
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
        $instruction->loadMissing(['sections', 'publisher:id,name', 'evidence']);

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
     * @return array{
     *     id: int,
     *     title: string,
     *     status: string,
     *     version_label: string,
     *     locale: string,
     *     published_at: string|null,
     *     updated_at: string|null
     * }
     */
    public function listItemPayload(UserSecurityInstruction $instruction): array
    {
        return [
            'id' => $instruction->id,
            'title' => $instruction->title,
            'status' => $instruction->status->value,
            'version_label' => $instruction->version_label,
            'locale' => $instruction->locale,
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
