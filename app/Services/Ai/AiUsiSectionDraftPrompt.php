<?php

namespace App\Services\Ai;

use App\Enums\UserSecurityInstructionSectionKey;

final class AiUsiSectionDraftPrompt
{
    public static function systemAddon(): string
    {
        return 'When asked to draft a user security instructions section, respond with a single JSON object only. No prose outside JSON. Do not claim the draft was saved or published.';
    }

    public static function userPrompt(
        UserSecurityInstructionSectionKey $sectionKey,
        string $sectionTitle,
        string $locale,
        string $productContext,
        ?string $currentBody = null,
        ?string $note = null,
    ): string {
        $key = $sectionKey->value;
        $noteBlock = filled($note) ? "Author note:\n" . trim((string) $note) . "\n\n" : '';
        $currentBlock = filled($currentBody)
            ? "Current section body (improve or rewrite; keep accurate facts):\n" . trim((string) $currentBody) . "\n\n"
            : "Current section body: (empty)\n\n";

        return <<<PROMPT
Draft Markdown content for one CRA User Security Instructions section and return ONLY valid JSON (no markdown fences) matching this schema:
{
  "section_key": "{$key}",
  "body_markdown": "string",
  "human_review_required": true,
  "disclaimer": "Draft only; human review required before save/publish."
}

Rules:
- Write in locale "{$locale}" (en or bg).
- Section title for reference: {$sectionTitle}
- Suggestions/draft only — never claim the text was saved, published, or applied.
- Do not invent legal compliance conclusions, certifications, or CVE IDs not present in context.
- Prefer actionable installer/operator guidance (lists, short paragraphs).
- Keep body_markdown reasonably concise (aim under 400 words).

{$noteBlock}{$currentBlock}Product / workspace context:
{$productContext}
PROMPT;
    }
}
