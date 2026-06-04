<?php

namespace App\Admin\Middleware;

use App\Services\Admin\SuperAdminGuard;
use Closure;
use Encore\Admin\Facades\Admin;

class SuperAdminOnly
{
    public function handle($request, Closure $next)
    {
        if (!Admin::user()) {
            return redirect(admin_base_path('auth/login'));
        }

        if (!SuperAdminGuard::isSuperAdmin()) {
            abort(403, '仅终极管理员可访问此模块。');
        }

        return $next($request);
    }
}
