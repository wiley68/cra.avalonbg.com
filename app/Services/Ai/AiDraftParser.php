<?php

namespace App\Services\Ai;

use App\Enums\AiDraftType;

final class AiDraftParser
{
    /**
     * @return array<string, mixed>|null
     */
    public static function parse(?string $raw, ?AiDraftType $expectedType = null): ?array
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

        return self::normalize($decoded, $expectedType);
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array{
     *     draft_type: string,
     *     subject: string,
     *     body_markdown: string,
     *     body_plain: string,
     *     highlights: list<string>,
     *     affected_summary: string|null,
     *     recommended_actions: list<string>,
     *     human_review_required: bool,
     *     disclaimer: string
     * }
     */
    public static function normalize(array $decoded, ?AiDraftType $expectedType = null): array
    {
        $type = (string) ($decoded['draft_type'] ?? ($expectedType?->value ?? AiDraftType::CustomerNotification->value));
        if (AiDraftType::tryFrom($type) === null && $expectedType !== null) {
            $type = $expectedType->value;
        }

        $highlights = is_array($decoded['highlights'] ?? null) ? $decoded['highlights'] : [];
        $actions = is_array($decoded['recommended_actions'] ?? null) ? $decoded['recommended_actions'] : [];

        return [
            'draft_type' => $type,
            'subject' => trim((string) ($decoded['subject'] ?? '')),
            'body_markdown' => trim((string) ($decoded['body_markdown'] ?? '')),
            'body_plain' => trim((string) ($decoded['body_plain'] ?? '')),
            'highlights' => array_values(array_slice(array_map('strval', $highlights), 0, 6)),
            'affected_summary' => isset($decoded['affected_summary']) && $decoded['affected_summary'] !== null
                ? trim((string) $decoded['affected_summary'])
                : null,
            'recommended_actions' => array_values(array_slice(array_map('strval', $actions), 0, 6)),
            'human_review_required' => (bool) ($decoded['human_review_required'] ?? true),
            'disclaimer' => (string) ($decoded['disclaimer'] ?? 'Draft only; no auto-send / no auto-close.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    public static function toReadableSummary(array $draft): string
    {
        $subject = trim((string) ($draft['subject'] ?? ''));
        $body = trim((string) ($draft['body_plain'] ?? ''));
        if ($body === '') {
            $body = trim((string) ($draft['body_markdown'] ?? ''));
        }

        $lines = [
            'Communication draft (human review required — not sent)',
            'Type: ' . (string) ($draft['draft_type'] ?? 'unknown'),
        ];

        if ($subject !== '') {
            $lines[] = "Subject: {$subject}";
        }

        if ($body !== '') {
            $lines[] = '';
            $lines[] = $body;
        }

        $lines[] = '';
        $lines[] = (string) ($draft['disclaimer'] ?? 'Draft only; no auto-send / no auto-close.');

        return implode("\n", $lines);
    }
}
