<?php

namespace App\Services\Ai;

use App\Enums\TechnicalDocumentationSectionKey;

final class AiTechDocSectionDraftPrompt
{
    public static function systemAddon(): string
    {
        return 'When asked to draft a technical documentation section, respond with a single JSON object only. No prose outside JSON. Do not claim the draft was saved or published.';
    }

    public static function userPrompt(
        TechnicalDocumentationSectionKey $sectionKey,
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
Draft Markdown content for one CRA Technical Documentation (§5.12) authored section and return ONLY valid JSON (no markdown fences) matching this schema:
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
- Do not invent legal compliance conclusions, certifications, Notified Body decisions, or CVE IDs not present in context.
- Prefer clear manufacturer technical narrative (short paragraphs, lists where useful).
- Keep body_markdown reasonably concise (aim under 400 words).

{$noteBlock}{$currentBlock}Product / workspace context:
{$productContext}
PROMPT;
    }
}
