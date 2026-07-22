<?php

namespace App\Services\Ai;

use App\Support\Translations;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class AiDocumentTextExtractor
{
    /**
     * @var list<string>
     */
    private const ALLOWED_EXTENSIONS = [
        'txt',
        'md',
        'markdown',
        'csv',
        'json',
        'xml',
        'html',
        'htm',
        'log',
    ];

    public function extract(UploadedFile $file): string
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'file' => Translations::get('assistant.analyse.unsupported_type'),
            ]);
        }

        $raw = @file_get_contents($file->getRealPath() ?: '');
        if ($raw === false || $raw === '') {
            throw ValidationException::withMessages([
                'file' => Translations::get('assistant.analyse.empty_file'),
            ]);
        }

        if (!mb_check_encoding($raw, 'UTF-8')) {
            $converted = @mb_convert_encoding($raw, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1251');
            $raw = is_string($converted) ? $converted : $raw;
        }

        $text = trim(preg_replace("/\r\n?/", "\n", $raw) ?? $raw);
        if ($text === '') {
            throw ValidationException::withMessages([
                'file' => Translations::get('assistant.analyse.empty_file'),
            ]);
        }

        $max = max(1000, (int) config('ai.analyse_max_chars', 20000));
        if (mb_strlen($text) > $max) {
            $text = rtrim(mb_substr($text, 0, $max - 14)) . "\n…[truncated]";
        }

        return $text;
    }
}
