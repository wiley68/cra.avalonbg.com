<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use SplFileObject;

final class CsvReader
{
    /**
     * @return array{
     *     headers: list<string>,
     *     rows: list<array<string, string|null>>
     * }
     */
    public static function read(UploadedFile $file): array
    {
        $path = $file->getRealPath();

        if ($path === false) {
            throw ValidationException::withMessages([
                'file' => [Translations::get('common.import.invalid_file')],
            ]);
        }

        $csv = new SplFileObject($path, 'r');
        $csv->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);

        $headerRow = $csv->fgetcsv();

        if (!is_array($headerRow) || self::rowIsEmpty($headerRow)) {
            throw ValidationException::withMessages([
                'file' => [Translations::get('common.import.missing_header')],
            ]);
        }

        if (isset($headerRow[0]) && is_string($headerRow[0])) {
            $headerRow[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headerRow[0]) ?? $headerRow[0];
        }

        $headers = [];
        foreach ($headerRow as $index => $header) {
            $normalized = self::normalizeHeader(is_string($header) ? $header : '');

            if ($normalized === '') {
                continue;
            }

            $headers[$index] = $normalized;
        }

        if ($headers === []) {
            throw ValidationException::withMessages([
                'file' => [Translations::get('common.import.missing_header')],
            ]);
        }

        $rows = [];
        $lineNumber = 1;

        while (!$csv->eof()) {
            $lineNumber++;
            $raw = $csv->fgetcsv();

            if (!is_array($raw) || self::rowIsEmpty($raw)) {
                continue;
            }

            $row = [];

            foreach ($headers as $index => $header) {
                $value = $raw[$index] ?? null;

                if (!is_string($value)) {
                    $row[$header] = null;

                    continue;
                }

                $trimmed = trim($value);

                $row[$header] = $trimmed === '' ? null : $trimmed;
            }

            $rows[] = $row;
        }

        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => [Translations::get('common.import.empty_file')],
            ]);
        }

        return [
            'headers' => array_values($headers),
            'rows' => $rows,
        ];
    }

    /**
     * @param  list<string|null>  $row
     */
    private static function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (is_string($value) && trim($value) !== '') {
                return false;
            }
        }

        return true;
    }

    private static function normalizeHeader(string $header): string
    {
        $normalized = strtolower(trim($header));
        $normalized = preg_replace('/[\s\-]+/', '_', $normalized) ?? $normalized;
        $normalized = preg_replace('/[^a-z0-9_]/', '', $normalized) ?? $normalized;

        return $normalized;
    }
}
