<?php

namespace App\Http\Middleware;

use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful as SanctumEnsureFrontendRequestsAreStateful;
use Laravel\Sanctum\Sanctum;

/**
 * Sanctum treats a request as "from the frontend" only when Referer or Origin is present.
 * Same-origin fetch() often omits both headers, so session middleware never runs and
 * cookie auth fails. We treat same-host requests with browser / XHR markers as first-party.
 *
 * Host must match Sanctum's stateful domains (not only APP_URL), so setups with
 * APP_URL=http://localhost but public access via Cloudflare tunnel still work when
 * SANCTUM_STATEFUL_DOMAINS includes the public hostname.
 */
class EnsureFrontendRequestsAreStateful extends SanctumEnsureFrontendRequestsAreStateful
{
    public static function fromFrontend($request): bool
    {
        if (parent::fromFrontend($request)) {
            return true;
        }

        return self::isTrustedFirstPartyApiRequest($request);
    }

    protected static function isTrustedFirstPartyApiRequest($request): bool
    {
        if (!self::requestHostIsSanctumStateful($request)) {
            return false;
        }

        if ($request->headers->get('Sec-Fetch-Site') === 'same-origin') {
            return true;
        }

        if ($request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
            return true;
        }

        return false;
    }

    protected static function requestHostIsSanctumStateful($request): bool
    {
        $requestHost = $request->getHttpHost();

        foreach (array_filter(config('sanctum.stateful', [])) as $entry) {
            $entry = trim((string) $entry);
            if ($entry === '') {
                continue;
            }

            if ($entry === Sanctum::$currentRequestHostPlaceholder) {
                return true;
            }

            $patternHost = $entry;
            if (str_contains($entry, '://')) {
                $patternHost = (string) (parse_url($entry, PHP_URL_HOST) ?? $entry);
                $port = parse_url($entry, PHP_URL_PORT);
                if ($port !== null && $port !== false) {
                    $patternHost .= ':' . $port;
                }
            }

            if (strcasecmp($patternHost, $requestHost) === 0) {
                return true;
            }
        }

        return false;
    }
}
