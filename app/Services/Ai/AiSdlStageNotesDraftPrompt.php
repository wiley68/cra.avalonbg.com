<?php

namespace App\Services\Ai;

use App\Enums\SdlStage;
use App\Models\SdlRun;
use App\Support\SdlStageNoteTemplates;

final class AiSdlStageNotesDraftPrompt
{
    public static function systemAddon(): string
    {
        return 'When asked to draft SDL stage notes or a security checklist, respond with a single JSON object only. No prose outside JSON. Do not claim the draft was saved or that the SDL run was approved.';
    }

    public static function userPrompt(
        string $locale,
        string $productContext,
        string $runContext,
        string $stageKey,
        string $stageLabel,
        ?string $currentNotes = null,
        ?string $note = null,
        ?string $templateNotes = null,
    ): string {
        $noteBlock = filled($note) ? "Author note:\n" . trim((string) $note) . "\n\n" : '';
        $currentBlock = filled($currentNotes)
            ? "Current stage notes (improve or rewrite; keep accurate facts):\n" . trim((string) $currentNotes) . "\n\n"
            : "Current stage notes: (empty)\n\n";
        $templateBlock = filled($templateNotes)
            ? "Optional checklist template for this stage (use as structure hints, do not copy blindly):\n" . trim((string) $templateNotes) . "\n\n"
            : '';

        return <<<PROMPT
Draft internal secure-development stage notes / checklist outcomes and return ONLY valid JSON (no markdown fences) matching this schema:
{
  "notes_markdown": "string",
  "human_review_required": true,
  "disclaimer": "Draft only; human review required before save."
}

Rules:
- Write in locale "{$locale}" (en or bg).
- Focus on stage "{$stageKey}" ({$stageLabel}): threat considerations, secure coding / review checklist outcomes, evidence hints, and residual risks.
- Suggestions/draft only — never claim notes were saved or that release security approval was granted.
- Do not invent CVE IDs, scanner findings, PR URLs, or legal CRA conformity conclusions not present in context.
- Prefer concise Markdown (headings, short bullets). Aim under 300 words.

{$noteBlock}{$currentBlock}{$templateBlock}SDL run context:
{$runContext}

Product / workspace context:
{$productContext}
PROMPT;
    }

    public static function runContext(SdlRun $run, SdlStage $stage): string
    {
        $run->loadMissing([
            'version:id,version_number',
            'product:id,name',
            'stageEntries',
        ]);

        $entry = $run->stageEntries->firstWhere(
            fn($item) => $item->stage === $stage,
        );

        $lines = [
            'Run title: ' . $run->title,
            'Run status: ' . $run->status->value,
            'Current stage: ' . $run->current_stage->value,
            'Product version pin: ' . ($run->version?->version_number ?? 'product-wide'),
            'Run notes: ' . ($run->notes ?: 'n/a'),
            'Approved at: ' . ($run->approved_at?->toIso8601String() ?? 'n/a'),
            'Target stage: ' . $stage->value,
            'Target stage status: ' . ($entry?->status->value ?? 'pending'),
            'Target stage completed at: ' . ($entry?->completed_at?->toIso8601String() ?? 'n/a'),
        ];

        $other = $run->stageEntries
            ->filter(fn($item) => $item->stage !== $stage)
            ->map(function ($item): string {
                $notes = filled($item->notes)
                    ? ' — ' . mb_substr(trim((string) $item->notes), 0, 120)
                    : '';

                return sprintf('- %s: %s%s', $item->stage->value, $item->status->value, $notes);
            })
            ->values()
            ->all();

        $lines[] = 'Other stage statuses:';
        if ($other === []) {
            $lines[] = '- (none)';
        } else {
            foreach (array_slice($other, 0, 12) as $entryLine) {
                $lines[] = $entryLine;
            }
        }

        return implode("\n", $lines);
    }

    public static function templateHint(SdlStage $stage, string $locale): ?string
    {
        return SdlStageNoteTemplates::notesFor($stage, $locale);
    }
}
