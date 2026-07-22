<?php

namespace App\Services\Ai;

use App\Enums\AiDraftType;

final class AiDraftPrompt
{
    public static function systemAddon(): string
    {
        return 'When asked to generate a communication draft, respond with a single JSON object only. No prose outside JSON. Do not claim the message was sent.';
    }

    public static function userPrompt(
        AiDraftType $draftType,
        string $campaignContext,
        ?string $note = null,
    ): string {
        $type = $draftType->value;
        $noteBlock = filled($note) ? "Uploader note:\n" . trim((string) $note) . "\n\n" : '';

        $tone = match ($draftType) {
            AiDraftType::SecurityAdvisory => 'Write a formal security advisory suitable for public or partner distribution.',
            AiDraftType::CustomerNotification => 'Write an email-style customer notification about a security/product update. Align with a short vendor email (greeting, what changed, versions, ask to confirm).',
        };

        return <<<PROMPT
Generate a CRA workspace communication draft and return ONLY valid JSON (no markdown fences) matching this schema:
{
  "draft_type": "{$type}",
  "subject": "string",
  "body_markdown": "string",
  "body_plain": "string",
  "highlights": ["string"],
  "affected_summary": "string|null",
  "recommended_actions": ["string"],
  "human_review_required": true,
  "disclaimer": "Draft only; no auto-send / no auto-close."
}

Rules:
- {$tone}
- Suggestions/draft only — human review is required before sending.
- Do not invent CVE IDs, severity scores, or legal compliance conclusions not present in context.
- Keep highlights and recommended_actions concise (max 6 items each).
- body_plain should be readable without markdown.

{$noteBlock}Campaign / product context:
{$campaignContext}
PROMPT;
    }
}
