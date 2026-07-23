<?php

namespace App\Services\Ai;

use App\Enums\UserSecurityInstructionSectionKey;

final class AiUsiSectionDraftParser
{
    /**
     * @return array{
     *     section_key: string,
     *     body_markdown: string,
     *     human_review_required: bool,
     *     disclaimer: string
     * }|null
     */
    public static function parse(string $raw, UserSecurityInstructionSectionKey $expectedKey): ?array
    {
        $json = self::extractJsonObject($raw);
        if ($json === null) {
            return null;
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return null;
        }

        $body = trim((string) ($decoded['body_markdown'] ?? ''));
        if ($body === '') {
            return null;
        }

        $sectionKey = (string) ($decoded['section_key'] ?? $expectedKey->value);
        if ($sectionKey !== $expectedKey->value) {
            $sectionKey = $expectedKey->value;
        }

        return [
            'section_key' => $sectionKey,
            'body_markdown' => $body,
            'human_review_required' => true,
            'disclaimer' => (string) ($decoded['disclaimer']
                ?? 'Draft only; human review required before save/publish.'),
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
