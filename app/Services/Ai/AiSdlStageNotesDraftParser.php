<?php

namespace App\Services\Ai;

final class AiSdlStageNotesDraftParser
{
    /**
     * @return array{
     *     notes_markdown: string,
     *     human_review_required: bool,
     *     disclaimer: string
     * }|null
     */
    public static function parse(string $raw): ?array
    {
        $json = self::extractJsonObject($raw);
        if ($json === null) {
            return null;
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return null;
        }

        $notes = trim((string) ($decoded['notes_markdown'] ?? $decoded['notes'] ?? ''));
        if ($notes === '') {
            return null;
        }

        return [
            'notes_markdown' => $notes,
            'human_review_required' => true,
            'disclaimer' => (string) ($decoded['disclaimer']
                ?? 'Draft only; human review required before save.'),
        ];
    }

    private static function extractJsonObject(string $raw): ?string
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/i', $trimmed, $matches) === 1) {
            return $matches[1];
        }

        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        return substr($trimmed, $start, $end - $start + 1);
    }
}
