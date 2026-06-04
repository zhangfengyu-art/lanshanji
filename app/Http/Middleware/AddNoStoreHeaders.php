<?php

namespace App\Http\Middleware;

use Closure;

class AddNoStoreHeaders
{
    /**
     * Prevent browsers from reusing stale HTML during refresh.
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if (isset($response->headers)) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }
}