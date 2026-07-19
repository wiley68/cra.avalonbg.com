<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var list<string> $availableLocales */
        $availableLocales = config('app.available_locales', ['en']);

        $locale = $request->session()->get('locale', config('app.locale'));

        $user = $request->user();
        if ($user !== null) {
            $organization = $user->currentOrganization();

            if ($organization !== null) {
                $locale = $organization->resolvedLocale();
                $request->session()->put('locale', $locale);
            }
        }

        if (in_array($locale, $availableLocales, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
