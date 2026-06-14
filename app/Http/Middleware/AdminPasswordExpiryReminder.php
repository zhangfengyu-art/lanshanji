<?php

namespace App\Http\Middleware;

use Closure;
use Encore\Admin\Facades\Admin;

class AdminPasswordExpiryReminder
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if (!Admin::user()) {
            return $response;
        }

        if ($request->session()->get('admin_password_reminder_shown')) {
            return $response;
        }

        $user = Admin::user();
        $changedAt = data_get($user, 'password_changed_at');
        $remindDays = (int) config('admin_security.password_remind_days', 90);

        if (!$changedAt) {
            return $response;
        }

        $days = now()->diffInDays($changedAt);
        if ($days < $remindDays) {
            return $response;
        }

        $request->session()->put('admin_password_reminder_shown', true);
        admin_toastr(
            '您的后台密码已使用 '.$days.' 天，建议尽快在「个人设置」中更换（至少 '
            .(int) config('admin_security.password_min_length', 16).' 位）。',
            'warning',
            ['timeOut' => 12000]
        );

        return $response;
    }
}
