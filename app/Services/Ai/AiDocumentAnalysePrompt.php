<?php

namespace App\Services\Ai;

final class AiDocumentAnalysePrompt
{
    /**
     * @param  list<array{code: string, status: string}>  $requirementHints
     */
    public static function userPrompt(
        string $filename,
        string $documentText,
        ?string $note,
        array $requirementHints = [],
    ): string {
        $hints = $requirementHints === []
            ? '(none listed)'
            : implode("\n", array_map(
                fn(array $row): string => '- ' . $row['code'] . ' | ' . $row['status'],
                $requirementHints,
            ));

        $noteBlock = filled($note) ? "Uploader note:\n" . trim((string) $note) . "\n\n" : '';

        return <<<PROMPT
Analyse the uploaded compliance-related document and return ONLY valid JSON (no markdown fences) matching this schema:
{
  "document_summary": "string",
  "document_kind_guess": "security_policy|pen_test_report|technical_manual|architecture|other",
  "requirement_mappings": [
    {"requirement_code": "string|null", "confidence": 0.0, "rationale": "string", "excerpt": "string|null"}
  ],
  "evidence_mappings": [
    {"suggested_evidence_type": "policy|test_report|document|architecture_diagram|other", "title_suggestion": "string", "confidence": 0.0, "rationale": "string"}
  ],
  "gaps": [
    {"kind": "missing_field|stale_version|contradiction|coverage_gap|other", "severity": "info|minor|major", "description": "string", "suggested_action": "string"}
  ],
  "human_review_required": true,
  "disclaimer": "Suggestions only; no automated compliance decisions."
}

Rules:
- Suggestions only; human review is required.
- Do not claim final CRA applicability or legal compliance.
- Prefer mapping to known requirement codes when relevant.
- Keep arrays concise (max 8 items each).

Filename: {$filename}

Known product requirement codes (hints):
{$hints}

{$noteBlock}Document text:
{$documentText}
PROMPT;
    }

    public static function systemAddon(): string
    {
        return 'When asked for document analysis, respond with a single JSON object only. No prose outside JSON.';
    }
}
