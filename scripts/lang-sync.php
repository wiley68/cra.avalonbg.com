<?php

declare(strict_types=1);

/**
 * Safely run `php artisan lang:update` without merging flat Laravel Lang
 * keys into the nested app JSON files used by the Inertia frontend.
 */

$root = dirname(__DIR__);
$tmp = sys_get_temp_dir();
$locales = ['en', 'bg'];

foreach ($locales as $locale) {
    $source = "{$root}/lang/{$locale}.json";
    $backup = "{$tmp}/cra-{$locale}.json.bak";

    if (!is_file($source)) {
        fwrite(STDERR, "Missing translation file: {$source}\n");
        exit(1);
    }

    if (!copy($source, $backup)) {
        fwrite(STDERR, "Failed to back up {$source}\n");
        exit(1);
    }
}

passthru('php artisan lang:update --ansi', $exitCode);

if ($exitCode !== 0) {
    exit($exitCode);
}

foreach ($locales as $locale) {
    $source = "{$root}/lang/{$locale}.json";
    $backup = "{$tmp}/cra-{$locale}.json.bak";

    if (!copy($backup, $source)) {
        fwrite(STDERR, "Failed to restore {$source}\n");
        exit(1);
    }
}

fwrite(STDOUT, "Restored nested lang/{en,bg}.json after laravel-lang update.\n");
