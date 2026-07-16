<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        /** @var list<string> $availableLocales */
        $availableLocales = config('app.available_locales', ['en']);

        abort_unless(in_array($locale, $availableLocales, true), 404);

        $request->session()->put('locale', $locale);

        return back();
    }
}
