<?php

namespace App\Services;

use App\Enums\SdlStage;
use App\Models\Organization;
use App\Models\Product;
use App\Models\SdlRun;
use App\Models\SdlStageEntry;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Translations;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductSdlExportService
{
    public const FORMATS = ['markdown', 'pdf'];

    public function export(
        SdlRun $run,
        Product $product,
        Organization $organization,
        string $format,
        User $actor,
    ): Response {
        $format = strtolower($format);

        if (!in_array($format, self::FORMATS, true)) {
            throw ValidationException::withMessages([
                'format' => Translations::get('products.sdl.export.invalid_format'),
            ]);
        }

        $viewPayload = $this->viewPayload($run, $product, $organization);
        $filenameBase = $this->filenameBase($run, $product);

        AuditLogger::logSdlRunExported($run, $actor, $format);

        return match ($format) {
            'pdf' => Pdf::loadView('pdf.product-sdl', $viewPayload)
                ->setPaper('a4')
                ->stream($filenameBase . '.pdf'),
            'markdown' => response($this->toMarkdown($viewPayload), 200, [
                'Content-Type' => 'text/markdown; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filenameBase . '.md"',
            ]),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function viewPayload(
        SdlRun $run,
        Product $product,
        Organization $organization,
    ): array {
        $run->loadMissing([
            'owner:id,name',
            'approver:id,name',
            'version:id,version_number',
            'evidence:id,title',
            'userSecurityInstruction.productVersion:id,version_number',
            'stageEntries.completer:id,name',
            'stageEntries.evidence:id,title',
            'stageEntries.exception.owner:id,name',
        ]);

        $entriesByStage = $run->stageEntries->keyBy(
            fn(SdlStageEntry $entry) => $entry->stage->value,
        );

        $stages = [];
        foreach (SdlStage::ordered() as $stage) {
            /** @var SdlStageEntry|null $entry */
            $entry = $entriesByStage->get($stage->value);
            $exception = $entry?->exception;

            $stages[] = [
                'stage' => $stage->value,
                'stage_label' => $this->stageLabel($stage->value),
                'status' => $entry?->status->value ?? 'pending',
                'status_label' => $this->stageStatusLabel(
                    $entry?->status->value ?? 'pending',
                ),
                'notes' => $entry?->notes,
                'completed_at' => $entry?->completed_at?->toIso8601String(),
                'completed_by_name' => $entry?->completer?->name,
                'evidence' => $entry?->evidence
                    ->pluck('title')
                    ->filter()
                    ->values()
                    ->all() ?? [],
                'exception' => $exception === null
                    ? null
                    : [
                        'owner_name' => $exception->owner?->name,
                        'expires_at' => $exception->expires_at->toDateString(),
                        'is_expired' => $exception->isExpired(),
                    ],
            ];
        }

        $usi = $run->userSecurityInstruction;

        return [
            'organization' => [
                'name' => $organization->name,
            ],
            'product' => [
                'name' => $product->name,
                'slug' => $product->slug,
            ],
            'run' => [
                'id' => $run->id,
                'title' => $run->title,
                'status' => $run->status->value,
                'status_label' => $this->runStatusLabel($run->status->value),
                'current_stage' => $run->current_stage->value,
                'current_stage_label' => $this->stageLabel($run->current_stage->value),
                'version_number' => $run->version?->version_number,
                'owner_name' => $run->owner?->name,
                'notes' => $run->notes,
                'approved_at' => $run->approved_at?->toIso8601String(),
                'approved_by_name' => $run->approver?->name,
                'is_approved' => $run->isApproved(),
                'tech_doc_delta_reviewed' => (bool) $run->tech_doc_delta_reviewed,
                'linked_usi' => $usi === null
                    ? null
                    : [
                        'title' => $usi->title,
                        'version_label' => $usi->version_label,
                        'locale' => $usi->locale,
                        'version_number' => $usi->productVersion?->version_number,
                    ],
                'evidence' => $run->evidence
                    ->pluck('title')
                    ->filter()
                    ->values()
                    ->all(),
                'stages' => $stages,
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function toMarkdown(array $payload): string
    {
        /** @var array<string, mixed> $run */
        $run = $payload['run'];
        $lines = [];

        $lines[] = '# ' . $run['title'];
        $lines[] = '';
        $lines[] = '> ' . Translations::get('products.sdl.export.disclaimer');
        $lines[] = '';
        $lines[] = '- **' . Translations::get('products.sdl.export.meta_organization') . ':** '
            . $payload['organization']['name'];
        $lines[] = '- **' . Translations::get('products.sdl.export.meta_product') . ':** '
            . $payload['product']['name'];
        $lines[] = '- **' . Translations::get('products.sdl.fields.status') . ':** '
            . $run['status_label'];
        $lines[] = '- **' . Translations::get('products.sdl.fields.current_stage') . ':** '
            . $run['current_stage_label'];
        $lines[] = '- **' . Translations::get('products.sdl.fields.product_version') . ':** '
            . ($run['version_number'] ?: Translations::get('products.sdl.version_none'));

        if ($run['owner_name']) {
            $lines[] = '- **' . Translations::get('products.sdl.fields.owner') . ':** '
                . $run['owner_name'];
        }

        if ($run['approved_at']) {
            $approved = $run['approved_at'];
            if ($run['approved_by_name']) {
                $approved .= ' (' . $run['approved_by_name'] . ')';
            }
            $lines[] = '- **' . Translations::get('products.sdl.export.approved_at') . ':** '
                . $approved;
        }

        $lines[] = '- **' . Translations::get('products.sdl.export.generated_at') . ':** '
            . $payload['generated_at'];

        $lines[] = '';
        $lines[] = '## ' . Translations::get('products.sdl.export.section_documentation');
        $lines[] = '';
        $lines[] = '- **' . Translations::get('products.sdl.fields.linked_usi') . ':** '
            . ($run['linked_usi']
                ? $run['linked_usi']['title']
                . ' (' . $run['linked_usi']['version_label']
                . ', ' . $run['linked_usi']['locale'] . ')'
                : Translations::get('products.sdl.export.empty'));
        $lines[] = '- **' . Translations::get('products.sdl.fields.tech_doc_delta_reviewed') . ':** '
            . ($run['tech_doc_delta_reviewed']
                ? Translations::get('common.yes')
                : Translations::get('common.no'));

        if (filled($run['notes'])) {
            $lines[] = '';
            $lines[] = '## ' . Translations::get('products.sdl.fields.notes');
            $lines[] = '';
            $lines[] = $run['notes'];
        }

        $lines[] = '';
        $lines[] = '## ' . Translations::get('products.sdl.export.section_stages');
        $lines[] = '';

        foreach ($run['stages'] as $stage) {
            $lines[] = '### ' . $stage['stage_label'];
            $lines[] = '';
            $lines[] = '- **' . Translations::get('products.sdl.fields.stage_status') . ':** '
                . $stage['status_label'];

            if ($stage['completed_at']) {
                $completed = $stage['completed_at'];
                if ($stage['completed_by_name']) {
                    $completed .= ' (' . $stage['completed_by_name'] . ')';
                }
                $lines[] = '- **' . Translations::get('products.sdl.export.completed_at') . ':** '
                    . $completed;
            }

            $lines[] = '';
            $lines[] = '#### ' . Translations::get('products.sdl.fields.stage_notes');
            $lines[] = '';
            $lines[] = $this->blockOrEmpty($stage['notes']);

            $lines[] = '';
            $lines[] = '#### ' . Translations::get('products.sdl.export.stage_evidence');
            $lines[] = '';
            $lines = [...$lines, ...$this->bulletList($stage['evidence'] ?? [])];

            if ($stage['exception'] !== null) {
                $lines[] = '';
                $lines[] = '#### ' . Translations::get('products.sdl.export.section_exception');
                $lines[] = '';
                $lines[] = '- **' . Translations::get('products.sdl.fields.exception_owner') . ':** '
                    . ($stage['exception']['owner_name'] ?: Translations::get('products.sdl.export.empty'));
                $lines[] = '- **' . Translations::get('products.sdl.fields.exception_expires_at') . ':** '
                    . $stage['exception']['expires_at'];
                if ($stage['exception']['is_expired']) {
                    $lines[] = '- **' . Translations::get('products.sdl.exception_expired') . '**';
                }
            }

            $lines[] = '';
        }

        $lines[] = '## ' . Translations::get('products.sdl.export.section_run_evidence');
        $lines[] = '';
        $lines = [...$lines, ...$this->bulletList($run['evidence'] ?? [])];
        $lines[] = '';

        return implode("\n", $lines);
    }

    private function filenameBase(SdlRun $run, Product $product): string
    {
        $slug = Str::slug($product->slug !== '' ? $product->slug : $product->name);

        return sprintf(
            'sdl-%s-%d-%s',
            $slug !== '' ? $slug : 'product',
            $run->id,
            now()->format('Y-m-d'),
        );
    }

    private function runStatusLabel(string $value): string
    {
        return $this->translateOrFallback("products.sdl.statuses.{$value}", $value);
    }

    private function stageLabel(string $value): string
    {
        return $this->translateOrFallback("products.sdl.stages.{$value}", $value);
    }

    private function stageStatusLabel(string $value): string
    {
        return $this->translateOrFallback("products.sdl.stage_statuses.{$value}", $value);
    }

    private function translateOrFallback(string $key, string $fallback): string
    {
        $translated = Translations::get($key);

        return $translated === $key ? $fallback : $translated;
    }

    private function blockOrEmpty(?string $value): string
    {
        return filled($value)
            ? $value
            : Translations::get('products.sdl.export.empty');
    }

    /**
     * @param  list<string>  $items
     * @return list<string>
     */
    private function bulletList(array $items): array
    {
        if ($items === []) {
            return [Translations::get('products.sdl.export.empty')];
        }

        return array_map(fn(string $item): string => '- ' . $item, $items);
    }
}
