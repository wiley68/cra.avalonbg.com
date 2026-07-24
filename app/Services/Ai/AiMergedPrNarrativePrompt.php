<?php

namespace App\Services\Ai;

use App\Models\Product;
use App\Models\ProductVersion;

final class AiMergedPrNarrativePrompt
{
    public static function systemAddon(): string
    {
        return 'When asked to draft a merged pull-request narrative, respond with a single JSON object only. No prose outside JSON. Do not claim the draft was saved as evidence or that entities were created.';
    }

    /**
     * @param  array{
     *     provider: string|null,
     *     repository_full_name: string|null,
     *     window: array{from: string, to: string, mode: string, anchor_date: string|null},
     *     count: int,
     *     truncated: bool,
     *     prs: list<array{number: int, title: string, html_url: string, merged_at: string|null, user_login: string|null}>
     * }  $summary
     */
    public static function userPrompt(
        string $locale,
        string $productContext,
        Product $product,
        ProductVersion $version,
        array $summary,
        string $mergedPrMarkdown,
        ?string $note = null,
    ): string {
        $noteBlock = filled($note) ? "Author note:\n" . trim((string) $note) . "\n\n" : '';
        $provider = $summary['provider'] ?? 'unknown';
        $repo = $summary['repository_full_name'] ?? 'n/a';
        $from = $summary['window']['from'];
        $to = $summary['window']['to'];
        $count = $summary['count'];
        $truncated = !empty($summary['truncated']) ? 'yes' : 'no';

        return <<<PROMPT
Draft a concise release-oriented narrative of merged pull/merge requests and return ONLY valid JSON (no markdown fences) matching this schema:
{
  "summary_markdown": "string",
  "human_review_required": true,
  "disclaimer": "Draft only; human review required. Nothing is saved automatically."
}

Rules:
- Write in locale "{$locale}" (en or bg).
- Suggestions/draft only — never claim evidence was saved, tasks were created, or PRs were merged by CRA.
- Do not invent CVE IDs, compliance conclusions, or PR titles not present in the merged-PR list.
- Prefer a short narrative: themes across merges, notable changes, and residual review notes.
- Keep summary_markdown reasonably concise (aim under 200 words). Plain paragraphs or light Markdown lists are fine.
- If the list is empty, say so clearly and suggest verifying the release window / repository link.

{$noteBlock}Product: {$product->name}
Version: {$version->version_number}
Provider: {$provider}
Repository: {$repo}
Window: {$from} – {$to}
Count: {$count} (truncated: {$truncated})

Merged PR / MR list (Markdown snapshot):
{$mergedPrMarkdown}

Product / workspace context:
{$productContext}
PROMPT;
    }
}
