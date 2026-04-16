<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class EnsureFrontendUserIsActive
{
    public function handle($request, Closure $next)
    {
        if (!Auth::check()) {
            return $next($request);
        }

        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        if (!$user->is_enabled) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => '账号已被封禁，请联系管理员。',
            ]);
        }

        $currentSessionVersion = (int) data_get($user, 'session_version', 0);
        $storedSessionVersion = $request->session()->get('frontend_user_session_version');

        if ($storedSessionVersion === null) {
            $request->session()->put('frontend_user_session_version', $currentSessionVersion);
            return $next($request);
        }

        if ((int) $storedSessionVersion !== $currentSessionVersion) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => '登录状态已失效，请重新登录。',
            ]);
        }

        return $next($request);
    }
}