<?php

namespace App\Http\Middleware;

use Closure;

class DebugSiteMode
{
    /**
     * Debug site_mode detection
     */
    public function handle($request, Closure $next)
    {
        $port = (int) $request->getPort();
        $mode = function_exists('site_mode') ? site_mode() : 'UNKNOWN';
        
        $response = $next($request);
        $response->headers->set('X-Debug-Port', $port);
        $response->headers->set('X-Debug-Mode', $mode);
        
        return $response;
    }
}
