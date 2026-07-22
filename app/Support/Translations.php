<?php

namespace App\Support;

class Translations
{
    /** @var array<string, array<string, mixed>> */
    private static array $cache = [];

    /**
     * @return array<string, mixed>
     */
    public static function forLocale(?string $locale = null): array
    {
        $locale ??= app()->getLocale();

        if (isset(self::$cache[$locale])) {
            return self::$cache[$locale];
        }

        $path = lang_path("{$locale}.json");

        if (!is_file($path)) {
            return self::$cache[$locale] = [];
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return self::$cache[$locale] = [];
        }

        $decoded = json_decode($contents, true);

        return self::$cache[$locale] = is_array($decoded) ? $decoded : [];
    }

    public static function version(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $path = lang_path("{$locale}.json");

        if (!is_file($path)) {
            return '0';
        }

        return (string) filemtime($path);
    }

    public static function get(string $key, array $replace = [], ?string $locale = null): string
    {
        $value = self::resolve(self::forLocale($locale), $key);

        if (!is_string($value)) {
            return $key;
        }

        foreach ($replace as $placeholder => $replacement) {
            $value = str_replace(':' . $placeholder, (string) $replacement, $value);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $translations
     */
    private static function resolve(array $translations, string $key): mixed
    {
        $value = $translations;

        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $key;
            }

            $value = $value[$part];
        }

        return $value;
    }
}
