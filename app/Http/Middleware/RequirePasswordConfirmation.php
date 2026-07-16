<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Symfony\Component\HttpFoundation\Response;

class RequirePasswordConfirmation
{
    /**
     * Redirect guests that still need password confirmation, storing a
     * relative intended path so post-confirm redirects survive APP_URL / host mismatches.
     */
    public function handle(Request $request, Closure $next, ?int $passwordTimeoutSeconds = null): Response
    {
        if (!$this->shouldConfirmPassword($request, $passwordTimeoutSeconds)) {
            return $next($request);
        }

        $request->session()->put('url.intended', $request->getRequestUri());

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Password confirmation required.',
            ], 423);
        }

        return redirect()->route('password.confirm');
    }

    protected function shouldConfirmPassword(Request $request, ?int $passwordTimeoutSeconds = null): bool
    {
        $confirmedAt = Date::now()->unix() - $request->session()->get('auth.password_confirmed_at', 0);

        return $confirmedAt > ($passwordTimeoutSeconds ?? (int) config('auth.password_timeout', 10800));
    }
}
