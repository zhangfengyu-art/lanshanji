<?php

namespace App\Http\Middleware;

use App\Models\SiteSetting;
use Closure;
use Illuminate\Support\Facades\Schema;

class CheckIfEmailVerified
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
        if ($this->shouldSkipEmailVerificationForTesting()) {
            return $next($request);
        }

        // 测试阶段：购物车流程不要求邮箱验证
        if ($request->is('cart') || $request->is('cart/*')) {
            return $next($request);
        }

        if (!$request->user()->email_verified) {
            if ($request->expectsJson()) {
                return response()->json(['msg' => '请先验证邮箱'], 400);
            }
            return redirect(route('email_verify_notice'));
        }
        return $next($request);
    }

    protected function shouldSkipEmailVerificationForTesting()
    {
        if (!Schema::hasTable('site_settings')) {
            return false;
        }

        $value = SiteSetting::query()
            ->where('key', 'disable_email_verification_for_testing')
            ->value('value');

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'on', 'yes'], true);
    }
}
