<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class ForceHttps
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $forwardedProto = $request->header('X-Forwarded-Proto');

        if ($request->secure() || $forwardedProto === 'https') {
            URL::forceScheme('https');

            return $next($request);
        }

        return redirect()->secure($request->getRequestUri(), 301);
    }
}
