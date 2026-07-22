<?php

namespace App\Services\Ai;

final class AiTextChunker
{
    /**
     * @return list<string>
     */
    public static function chunk(string $text, ?int $maxChars = null): array
    {
        $max = max(200, $maxChars ?? (int) config('ai.rag.chunk_chars', 800));
        $normalized = trim(preg_replace("/\r\n?/", "\n", $text) ?? $text);

        if ($normalized === '') {
            return [];
        }

        if (mb_strlen($normalized) <= $max) {
            return [$normalized];
        }

        $paragraphs = preg_split("/\n{2,}/u", $normalized) ?: [$normalized];
        $chunks = [];
        $buffer = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }

            if (mb_strlen($paragraph) > $max) {
                if ($buffer !== '') {
                    $chunks[] = $buffer;
                    $buffer = '';
                }
                foreach (self::splitLong($paragraph, $max) as $part) {
                    $chunks[] = $part;
                }

                continue;
            }

            $candidate = $buffer === '' ? $paragraph : $buffer . "\n\n" . $paragraph;
            if (mb_strlen($candidate) <= $max) {
                $buffer = $candidate;
            } else {
                $chunks[] = $buffer;
                $buffer = $paragraph;
            }
        }

        if ($buffer !== '') {
            $chunks[] = $buffer;
        }

        return $chunks;
    }

    /**
     * @return list<string>
     */
    private static function splitLong(string $text, int $max): array
    {
        $parts = [];
        $length = mb_strlen($text);
        $offset = 0;

        while ($offset < $length) {
            $parts[] = mb_substr($text, $offset, $max);
            $offset += $max;
        }

        return $parts;
    }
}
