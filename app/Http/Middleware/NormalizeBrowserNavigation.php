<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chrome address-bar / bookmark navigations send Sec-Fetch-Site: none and omit Referer.
 * F5 / in-app links send Sec-Fetch-Site: same-origin and keep a Referer.
 *
 * Missing Referer on authenticated document GETs currently correlates with PHP-FPM
 * "Premature end of script headers" (500) behind Cloudflare tunnel. Normalize early.
 */
class NormalizeBrowserNavigation
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->registerFatalLogger();

        if ($this->isUserInitiatedDocumentNavigation($request) && !$request->headers->has('Referer')) {
            $request->headers->set('Referer', $request->getSchemeAndHttpHost() . '/');
        }

        return $next($request);
    }

    private function isUserInitiatedDocumentNavigation(Request $request): bool
    {
        return $request->headers->get('Sec-Fetch-Site') === 'none'
            && $request->headers->get('Sec-Fetch-Mode') === 'navigate'
            && in_array($request->headers->get('Sec-Fetch-Dest'), ['document', 'iframe', null, ''], true);
    }

    private function registerFatalLogger(): void
    {
        static $registered = false;

        if ($registered) {
            return;
        }

        $registered = true;

        register_shutdown_function(static function (): void {
            $error = error_get_last();

            if ($error === null) {
                return;
            }

            if (!in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }

            $line = sprintf(
                "%s %s\n",
                now()->toIso8601String(),
                json_encode($error, JSON_UNESCAPED_SLASHES),
            );

            @file_put_contents(storage_path('logs/php-fatals.log'), $line, FILE_APPEND | LOCK_EX);
        });
    }
}
