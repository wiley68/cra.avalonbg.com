<?php

namespace App\Http\Controllers;

use App\Support\Translations;
use Illuminate\Http\JsonResponse;

class TranslationController extends Controller
{
    /**
     * Serve locale JSON for the SPA (keeps Inertia HTML small).
     */
    public function __invoke(string $locale): JsonResponse
    {
        /** @var list<string> $available */
        $available = config('app.available_locales', ['en', 'bg']);

        if (!in_array($locale, $available, true)) {
            abort(404);
        }

        $path = lang_path("{$locale}.json");
        $mtime = is_file($path) ? (string) filemtime($path) : '0';

        return response()
            ->json(Translations::forLocale($locale))
            ->header('Cache-Control', 'public, max-age=300, must-revalidate')
            ->header('ETag', '"' . $mtime . '"');
    }
}
