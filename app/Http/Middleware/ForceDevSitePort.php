<?php

namespace App\Http\Middleware;

use Closure;

class ForceDevSitePort
{
    /**
     * Keep local dual-site ports stable: A on 8000, B on 8001.
     */
    public function handle($request, Closure $next)
    {
        if (!app()->environment(['local', 'testing'])) {
            return $next($request);
        }

        $port = (int) $request->getPort();
        if (!in_array($port, [8000, 8001], true)) {
            return $next($request);
        }

        $mode = function_exists('site_mode') ? site_mode() : 'A';
        $targetPort = null;

        if ($mode === 'B' && $port === 8000) {
            $targetPort = 8001;
        }

        if ($mode !== 'B' && $port === 8001) {
            $targetPort = 8000;
        }

        if ($targetPort === null) {
            return $next($request);
        }

        $uri = $request->getRequestUri();
        $host = $request->getHost();
        $scheme = $request->getScheme();
        $targetUrl = sprintf('%s://%s:%d%s', $scheme, $host, $targetPort, $uri);

        return redirect()->to($targetUrl, 302);
    }
}
