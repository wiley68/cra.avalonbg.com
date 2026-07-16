<?php

namespace App\Http\Middleware;

use App\Enums\Appearance;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            $appearance = $user->appearance ?? Appearance::System->value;
        } else {
            $cookieAppearance = $request->cookie('appearance');
            $appearance = Appearance::tryFrom((string) $cookieAppearance) !== null
                ? (string) $cookieAppearance
                : Appearance::System->value;
        }

        View::share('appearance', $appearance);

        return $next($request);
    }
}
