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

            $path = $this->toInternalPath($candidate);

            if ($path !== null) {
                return $path;
            }
        }

        return $fallback;
    }

    private function toInternalPath(string $url): ?string
    {
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return $url;
        }

        $appUrl = rtrim((string) config('app.url'), '/');

        if ($appUrl === '' || !str_starts_with($url, $appUrl . '/')) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $query = parse_url($url, PHP_URL_QUERY);

        return $query ? $path . '?' . $query : $path;
    }
}
