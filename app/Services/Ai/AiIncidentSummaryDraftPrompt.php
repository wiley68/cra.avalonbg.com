<?php

namespace App\Services\Ai;

use App\Models\ProductIncident;

final class AiIncidentSummaryDraftPrompt
{
    public static function systemAddon(): string
    {
        return 'When asked to draft an incident summary, respond with a single JSON object only. No prose outside JSON. Do not claim the draft was saved or that the incident was closed or reported.';
    }

    public static function userPrompt(
        string $locale,
        string $productContext,
        string $incidentContext,
        ?string $currentSummary = null,
        ?string $note = null,
    ): string {
        $noteBlock = filled($note) ? "Author note:\n" . trim((string) $note) . "\n\n" : '';
        $currentBlock = filled($currentSummary)
            ? "Current summary (improve or rewrite; keep accurate facts):\n" . trim((string) $currentSummary) . "\n\n"
            : "Current summary: (empty)\n\n";

        return <<<PROMPT
Draft a concise internal security incident summary and return ONLY valid JSON (no markdown fences) matching this schema:
{
  "summary_markdown": "string",
  "human_review_required": true,
  "disclaimer": "Draft only; human review required before save."
}

Rules:
- Write in locale "{$locale}" (en or bg).
- Suggestions/draft only — never claim the text was saved, the incident was closed, or a report was submitted.
- Do not invent CVE IDs, legal compliance conclusions, or authority submissions not present in context.
- Prefer a clear narrative: what happened, impact, status/containment, and next steps if known.
- Keep summary_markdown reasonably concise (aim under 250 words). Plain paragraphs or light Markdown lists are fine.

{$noteBlock}{$currentBlock}Incident record context:
{$incidentContext}

Product / workspace context:
{$productContext}
PROMPT;
    }

    public static function incidentContext(ProductIncident $incident): string
    {
        $incident->loadMissing([
            'versions:id,version_number',
            'customers:id,name',
            'timelineEvents',
            'vulnerability:id,title,cve_id,status',
        ]);

        $lines = [
            'Title: ' . $incident->title,
            'Status: ' . $incident->status->value,
            'Severity: ' . $incident->severity->value,
            'Confidentiality impact: ' . ($incident->confidentiality_impact?->value ?? 'n/a'),
            'Integrity impact: ' . ($incident->integrity_impact?->value ?? 'n/a'),
            'Availability impact: ' . ($incident->availability_impact?->value ?? 'n/a'),
            'Attack vector: ' . ($incident->attack_vector?->value ?? 'n/a'),
            'Actual started at: ' . ($incident->actual_started_at?->toIso8601String() ?? 'n/a'),
            'Detected at: ' . ($incident->detected_at?->toIso8601String() ?? 'n/a'),
            'Awareness at: ' . ($incident->awareness_at?->toIso8601String() ?? 'n/a'),
            'Classified at: ' . ($incident->classified_at?->toIso8601String() ?? 'n/a'),
            'Closed at: ' . ($incident->closed_at?->toIso8601String() ?? 'n/a'),
            'Root cause: ' . ($incident->root_cause ?: 'n/a'),
            'Corrective measures: ' . ($incident->corrective_measures ?: 'n/a'),
            'Lessons learned: ' . ($incident->lessons_learned ?: 'n/a'),
            'Notes: ' . ($incident->notes ?: 'n/a'),
        ];

        $versions = $incident->versions->pluck('version_number')->filter()->values()->all();
        $lines[] = 'Affected versions: ' . ($versions !== [] ? implode(', ', $versions) : 'n/a');

        $customers = $incident->customers->pluck('name')->filter()->values()->all();
        $lines[] = 'Affected customers: ' . ($customers !== [] ? implode(', ', $customers) : 'n/a');

        if ($incident->vulnerability) {
            $vuln = $incident->vulnerability;
            $lines[] = sprintf(
                'Linked vulnerability: %s%s (%s)',
                $vuln->title,
                filled($vuln->cve_id) ? ' [' . $vuln->cve_id . ']' : '',
                $vuln->status->value,
            );
        } else {
            $lines[] = 'Linked vulnerability: n/a';
        }

        $timeline = $incident->timelineEvents
            ->map(function ($event): string {
                $entry = $event->occurred_at->toIso8601String() . ' — ' . $event->label;
                if (filled($event->notes)) {
                    $entry .= ' (' . trim((string) $event->notes) . ')';
                }

                return $entry;
            })
            ->values()
            ->all();

        $lines[] = 'Timeline events:';
        if ($timeline === []) {
            $lines[] = '- (none)';
        } else {
            foreach (array_slice($timeline, 0, 20) as $entry) {
                $lines[] = '- ' . $entry;
            }
        }

        return implode("\n", $lines);
    }
}
