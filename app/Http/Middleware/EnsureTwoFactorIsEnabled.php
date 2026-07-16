<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorIsEnabled
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->two_factor_confirmed_at) {
            return $next($request);
        }

        if ($request->routeIs('auth.two-factor.setup', 'two-factor.*', 'logout')) {
            return $next($request);
        }

        return redirect()->route('auth.two-factor.setup');
    }
}

