<?php

namespace App\Services\Ai;

final class AiSuggestionsParser
{
    /**
     * @return array<string, mixed>|null
     */
    public static function parse(?string $raw): ?array
    {
        $text = trim((string) $raw);
        if ($text === '') {
            return null;
        }

        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $text, $matches) === 1) {
            $text = $matches[1];
        } elseif (preg_match('/\{.*\}/s', $text, $matches) === 1) {
            $text = $matches[0];
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($decoded)) {
            return null;
        }

        return self::normalize($decoded);
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array{
     *     document_summary: string,
     *     document_kind_guess: string,
     *     requirement_mappings: list<array<string, mixed>>,
     *     evidence_mappings: list<array<string, mixed>>,
     *     gaps: list<array<string, mixed>>,
     *     human_review_required: bool,
     *     disclaimer: string
     * }
     */
    public static function normalize(array $decoded): array
    {
        return [
            'document_summary' => (string) ($decoded['document_summary'] ?? ''),
            'document_kind_guess' => (string) ($decoded['document_kind_guess'] ?? 'other'),
            'requirement_mappings' => array_values(array_slice(
                is_array($decoded['requirement_mappings'] ?? null) ? $decoded['requirement_mappings'] : [],
                0,
                8,
            )),
            'evidence_mappings' => array_values(array_slice(
                is_array($decoded['evidence_mappings'] ?? null) ? $decoded['evidence_mappings'] : [],
                0,
                8,
            )),
            'gaps' => array_values(array_slice(
                is_array($decoded['gaps'] ?? null) ? $decoded['gaps'] : [],
                0,
                8,
            )),
            'human_review_required' => (bool) ($decoded['human_review_required'] ?? true),
            'disclaimer' => (string) ($decoded['disclaimer'] ?? 'Suggestions only; no automated compliance decisions.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $suggestions
     */
    public static function toReadableSummary(array $suggestions, string $filename): string
    {
        $kind = (string) ($suggestions['document_kind_guess'] ?? 'other');
        $summary = trim((string) ($suggestions['document_summary'] ?? ''));
        $reqCount = count($suggestions['requirement_mappings'] ?? []);
        $evCount = count($suggestions['evidence_mappings'] ?? []);
        $gapCount = count($suggestions['gaps'] ?? []);

        $lines = [
            'Document analysis suggestions (human review required)',
            "File: {$filename}",
            "Kind guess: {$kind}",
        ];

        if ($summary !== '') {
            $lines[] = "Summary: {$summary}";
        }

        $lines[] = "Requirement mapping suggestions: {$reqCount}";
        $lines[] = "Evidence mapping suggestions: {$evCount}";
        $lines[] = "Gaps / issues flagged: {$gapCount}";
        $lines[] = (string) ($suggestions['disclaimer'] ?? 'Suggestions only; no automated compliance decisions.');

        return implode("\n", $lines);
    }
}
