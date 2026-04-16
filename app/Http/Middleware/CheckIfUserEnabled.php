<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class CheckIfUserEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (!Auth::check()) {
            return $next($request);
        }

        $user = $request->user();
        if ($user && Schema::hasColumn('users', 'is_enabled') && !$user->is_enabled) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => '账号已被停用，请联系管理员。']);
        }

        if ($user && Schema::hasColumn('users', 'session_version')) {
            $sessionVersion = (int) $request->session()->get('user_session_version', 0);
            $currentVersion = (int) $user->session_version;

            if ($sessionVersion !== $currentVersion) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->withErrors(['email' => '账号会话已失效，请重新登录。']);
            }
        }

        return $next($request);
    }
}
