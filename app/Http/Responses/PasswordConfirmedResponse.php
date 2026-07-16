<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\PasswordConfirmedResponse as PasswordConfirmedResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class PasswordConfirmedResponse implements PasswordConfirmedResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     */
    public function toResponse($request): Response
    {
        $redirect = $this->resolveRedirect(
            $request,
            Fortify::redirects('password-confirmation', '/dashboard'),
        );

        return $request->wantsJson()
            ? new JsonResponse(['redirect' => $redirect], 201)
            : redirect()->to($redirect);
    }

    private function resolveRedirect(Request $request, string $fallback): string
    {
        foreach ([$request->input('redirect'), $request->session()->pull('url.intended')] as $candidate) {
            if (!is_string($candidate) || $candidate === '') {
                continue;
            }

            $path = $this->toInternalPath($candidate, $request);

            if ($path !== null) {
                return $path;
            }
        }

        return $fallback;
    }

    private function toInternalPath(string $url, Request $request): ?string
    {
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return $url;
        }

        $parts = parse_url($url);

        if ($parts === false || !isset($parts['host'], $parts['path'])) {
            return null;
        }

        $allowedHosts = array_values(array_unique(array_filter([
            parse_url((string) config('app.url'), PHP_URL_HOST),
            $request->getHost(),
            'localhost',
            'cra.avalonbg.com',
        ])));

        if (!in_array($parts['host'], $allowedHosts, true)) {
            return null;
        }

        $path = $parts['path'] !== '' ? $parts['path'] : '/';

        return isset($parts['query']) ? $path . '?' . $parts['query'] : $path;
    }
}
