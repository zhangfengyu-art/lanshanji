<?php

namespace App\Services\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AdminLoginGuardService
{
    const SESSION_CAPTCHA_KEY = 'admin_login_captcha_answer';

    public function attemptKey(Request $request, $username)
    {
        $username = strtolower(trim((string) $username));

        return sha1($request->ip().'|'.$username);
    }

    public function failureCount(Request $request, $username)
    {
        return (int) Cache::get($this->cacheKey($request, $username), 0);
    }

    public function requiresCaptcha(Request $request, $username = null)
    {
        $username = $username ?: (string) request()->old('username', '');

        return $this->failureCount($request, $username)
            >= (int) config('admin_security.login_captcha_after', 3);
    }

    public function isLocked(Request $request, $username)
    {
        return Cache::has($this->lockKey($request, $username));
    }

    public function lockoutSecondsRemaining(Request $request, $username)
    {
        $expiresAt = Cache::get($this->lockKey($request, $username));

        if (!$expiresAt) {
            return 0;
        }

        return max(0, (int) $expiresAt - time());
    }

    public function recordFailure(Request $request, $username)
    {
        $key = $this->cacheKey($request, $username);
        $count = (int) Cache::get($key, 0) + 1;
        $lockoutMinutes = (int) config('admin_security.login_lockout_minutes', 15);
        Cache::put($key, $count, now()->addMinutes($lockoutMinutes));

        $maxAttempts = (int) config('admin_security.login_max_attempts', 5);
        if ($count >= $maxAttempts) {
            Cache::put(
                $this->lockKey($request, $username),
                time() + ($lockoutMinutes * 60),
                now()->addMinutes($lockoutMinutes)
            );
        }
    }

    public function clearFailures(Request $request, $username)
    {
        Cache::forget($this->cacheKey($request, $username));
        Cache::forget($this->lockKey($request, $username));
    }

    public function refreshCaptchaQuestion()
    {
        $a = random_int(2, 9);
        $b = random_int(2, 9);
        session([
            self::SESSION_CAPTCHA_KEY => $a + $b,
            'admin_login_captcha_question' => $a.' + '.$b,
        ]);
    }

    public function captchaQuestion()
    {
        return (string) session('admin_login_captcha_question', '');
    }

    public function captchaValid($input)
    {
        $expected = session(self::SESSION_CAPTCHA_KEY);
        if ($expected === null) {
            return false;
        }

        return (string) $expected === trim((string) $input);
    }

    protected function cacheKey(Request $request, $username)
    {
        return 'admin_login_fail:'.$this->attemptKey($request, $username);
    }

    protected function lockKey(Request $request, $username)
    {
        return 'admin_login_lock:'.$this->attemptKey($request, $username);
    }
}
