<?php

namespace App\Services\Ai;

use App\Enums\ImportSuggestionKind;
use App\Enums\ImportSuggestionStatus;
use App\Enums\VcsImportSuggestionKind;
use App\Enums\VcsImportSuggestionStatus;
use App\Models\ImportSuggestion;
use App\Models\VcsImportSuggestion;

final class AiImportedFindingTriagePrompt
{
    public static function systemAddon(): string
    {
        return 'When asked to triage an imported vulnerability finding, respond with a single JSON object only. No prose outside JSON. Do not claim the finding was accepted, dismissed, saved, or that a vulnerability record was created.';
    }

    public static function userPrompt(
        string $locale,
        string $productContext,
        string $findingContext,
        ?string $note = null,
    ): string {
        $noteBlock = filled($note) ? "Reviewer note:\n" . trim((string) $note) . "\n\n" : '';

        return <<<PROMPT
Draft a concise triage summary for an imported vulnerability finding (still pending human Accept/Dismiss) and return ONLY valid JSON (no markdown fences) matching this schema:
{
  "summary_markdown": "string",
  "suggested_severity": "critical|high|medium|low|info|unknown",
  "human_review_required": true,
  "disclaimer": "Draft only; human review required before Accept. Nothing is auto-accepted."
}

Rules:
- Write in locale "{$locale}" (en or bg).
- Suggestions/draft only — never claim Accept ran, a ProductVulnerability was created, or the finding was dismissed.
- Do not invent CVE IDs, CVSS scores, or package facts not present in context.
- Prefer: what the finding is, package/component impact if known, severity rationale, and recommended next step (Accept vs investigate vs dismiss).
- Keep summary_markdown concise (aim under 200 words). Plain paragraphs or light Markdown lists are fine.
- suggested_severity must be one of the enum values above (use "unknown" if unclear).

{$noteBlock}Imported finding context:
{$findingContext}

Product / workspace context:
{$productContext}
PROMPT;
    }

    public static function findingContext(ImportSuggestion|VcsImportSuggestion $suggestion): string
    {
        $payload = is_array($suggestion->payload) ? $suggestion->payload : [];

        if ($suggestion instanceof ImportSuggestion) {
            $suggestion->loadMissing(['link.integration']);
            $title = $suggestion->title !== ''
                ? $suggestion->title
                : (string) ($payload['title'] ?? $suggestion->external_id);
            $kind = $suggestion->kind->value;
            $status = $suggestion->status->value;
            $source = 'integration_import';
            $providerEnum = $suggestion->link?->integration?->provider;
            $provider = is_object($providerEnum) && property_exists($providerEnum, 'value')
                ? $providerEnum->value
                : (is_string($providerEnum) ? $providerEnum : null);
        } else {
            $suggestion->loadMissing(['repository.connection']);
            $title = (string) ($payload['title'] ?? $suggestion->external_id);
            $kind = $suggestion->kind->value;
            $status = $suggestion->status->value;
            $source = 'vcs_import';
            $providerEnum = $suggestion->repository?->connection?->provider;
            $provider = is_object($providerEnum) && property_exists($providerEnum, 'value')
                ? $providerEnum->value
                : (is_string($providerEnum) ? $providerEnum : null);
        }

        $matched = [];
        if (isset($payload['matched_components']) && is_array($payload['matched_components'])) {
            foreach ($payload['matched_components'] as $component) {
                if (!is_array($component)) {
                    continue;
                }
                $name = (string) ($component['name'] ?? '');
                if ($name === '') {
                    continue;
                }
                $version = filled($component['version'] ?? null) ? '@' . $component['version'] : '';
                $matched[] = $name . $version;
            }
        }

        $lines = [
            'Source: ' . $source,
            'Provider: ' . (is_string($provider) && $provider !== '' ? $provider : 'n/a'),
            'Suggestion id: #' . $suggestion->id,
            'Kind: ' . $kind,
            'Status: ' . $status,
            'External id: ' . $suggestion->external_id,
            'Title: ' . $title,
            'Summary: ' . (string) ($payload['summary'] ?? 'n/a'),
            'CVE: ' . (string) ($payload['cve_id'] ?? 'n/a'),
            'Severity (scanner): ' . (string) ($payload['severity'] ?? 'n/a'),
            'CVSS: ' . (string) ($payload['cvss_score'] ?? 'n/a'),
            'Package: ' . (string) ($payload['package_name'] ?? 'n/a'),
            'Ecosystem: ' . (string) ($payload['package_ecosystem'] ?? 'n/a'),
            'PURL: ' . (string) ($payload['package_purl'] ?? 'n/a'),
            'HTML URL: ' . (string) ($payload['html_url'] ?? 'n/a'),
            'PR URL: ' . (string) ($payload['pr_url'] ?? 'n/a'),
            'Bot source: ' . (string) ($payload['bot_source'] ?? 'n/a'),
            'Matched SBOM components: ' . ($matched !== [] ? implode(', ', $matched) : 'n/a'),
        ];

        return implode("\n", $lines);
    }

    public static function assertPendingVulnerability(ImportSuggestion|VcsImportSuggestion $suggestion): void
    {
        if ($suggestion instanceof ImportSuggestion) {
            if ($suggestion->kind !== ImportSuggestionKind::Vulnerability) {
                abort(422, 'AI triage is only available for vulnerability findings.');
            }
            if ($suggestion->status !== ImportSuggestionStatus::Pending) {
                abort(422, 'AI triage is only available for pending suggestions.');
            }

            return;
        }

        if ($suggestion->kind !== VcsImportSuggestionKind::Vulnerability) {
            abort(422, 'AI triage is only available for vulnerability findings.');
        }
        if ($suggestion->status !== VcsImportSuggestionStatus::Pending) {
            abort(422, 'AI triage is only available for pending suggestions.');
        }
    }
}
