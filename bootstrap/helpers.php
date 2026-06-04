<?php
function route_class()
{
    return str_replace('.', '-', Route::currentRouteName());
}

function site_mode()
{
    $normalize = function ($mode) {
        return strtoupper((string) $mode) === 'B' ? 'B' : 'A';
    };

    $fallback = $normalize(config('site.mode', env('SITE_MODE', 'A')));

    $appEnv = strtolower((string) config('app.env', env('APP_ENV', 'production')));
    $isProduction = $appEnv === 'production';

    $requestPort = null;
    try {
        if (function_exists('request') && app()->bound('request')) {
            $requestPort = (int) request()->getPort();
        }
    } catch (\Throwable $e) {
        $requestPort = null;
    }

    // In local dual-site mode, keep mode strictly bound to dev ports.
    if (!$isProduction) {
        if ($requestPort === 8001) {
            return 'B';
        }
        if ($requestPort === 8000) {
            return 'A';
        }
    }

    $isDevPort = in_array($requestPort, [8000, 8001], true);

    $envModeRaw = strtoupper((string) env('SITE_MODE', config('site.mode', '')));
    $envMode = in_array($envModeRaw, ['A', 'B'], true) ? $envModeRaw : null;

    // Dual-track strategy: local or dedicated dev ports can force mode via .env.A/.env.B.
    if ((!$isProduction || $isDevPort) && $envMode !== null) {
        return $envMode;
    }

    try {
        if (!class_exists('App\\Models\\SiteSetting')) {
            return $fallback;
        }

        if (!\Illuminate\Support\Facades\Schema::hasTable('site_settings')) {
            return $fallback;
        }

        $cache = app('cache');
        $mode = $cache->remember('site.active_mode', 60, function () {
            return optional(\App\Models\SiteSetting::query()->where('key', 'active_site_mode')->first())->value;
        });

        if ($mode !== null && $mode !== '') {
            return $normalize($mode);
        }
    } catch (\Throwable $e) {
        return $fallback;
    }

    return $fallback;
}

function is_site_mode_b()
{
    return site_mode() === 'B';
}

function is_site_mode_a()
{
    return site_mode() === 'A';
}

function site_a_url($path = '')
{
    $base = rtrim((string) config('site.a_url', env('SITE_A_URL', 'http://127.0.0.1:8000')), '/');

    if ($path === '' || $path === null) {
        return $base;
    }

    return $base.'/'.ltrim($path, '/');
}

function site_b_url($path = '')
{
    $base = rtrim((string) config('site.b_url', env('SITE_B_URL', 'http://127.0.0.1:8001')), '/');

    if ($path === '' || $path === null) {
        return $base;
    }

    return $base.'/'.ltrim($path, '/');
}

/**
 * B 站跨站支付落地页：应回到 A 站，而非 B 站首页。
 */
function is_cross_site_checkout_page()
{
    if (!is_site_mode_b()) {
        return false;
    }

    try {
        $routeName = optional(request()->route())->getName();

        if (in_array($routeName, [
            'payment.wechat',
            'payment.alipay',
            'payment.cross',
            'payment.alipay.launch',
            'payment.wechat.qr',
        ], true)) {
            return true;
        }

        $path = ltrim((string) request()->path(), '/');

        return $path === 'payment' || strpos($path, 'payment/') === 0;
    } catch (\Throwable $e) {
        return false;
    }
}

function site_home_url()
{
    if (is_cross_site_checkout_page()) {
        return site_a_url();
    }

    return url('/');
}

/**
 * 后台上传的站点 Logo 访问地址（文件不存在时返回 null）。
 */
function site_logo_url()
{
    static $resolved = false;
    static $url = null;

    if ($resolved) {
        return $url;
    }

    $resolved = true;

    try {
        if (!\Illuminate\Support\Facades\Schema::hasTable('site_settings')) {
            return $url;
        }

        $path = trim((string) \App\Models\SiteSetting::query()
            ->where('key', 'header_logo')
            ->value('value'));

        if ($path === '') {
            return $url;
        }

        if (preg_match('#^https?://#i', $path)) {
            $url = $path;

            return $url;
        }

        $relative = ltrim(str_replace('\\', '/', $path), '/');
        $fullPath = storage_path('app/public/'.$relative);

        if (!is_file($fullPath)) {
            return $url;
        }

        $url = asset('storage/'.$relative);
    } catch (\Throwable $e) {
        $url = null;
    }

    return $url;
}

/**
 * 前台中文品牌名：B 模式固定为「岚山集」，与 A 站烟草品牌隔离。
 */
function site_brand_zh()
{
    if (is_site_mode_b()) {
        return '岚山集';
    }

    try {
        $shared = view()->getShared();
        if (!empty($shared['siteBrandZh'])) {
            return (string) $shared['siteBrandZh'];
        }
    } catch (\Throwable $e) {
        // ignore
    }

    return '岚山烟务所';
}

/**
 * 页面标题后缀（浏览器 title）。
 */
function site_page_subtitle()
{
    if (is_site_mode_b()) {
        return '跨境代购与资金托管';
    }

    return trans('frontend.site.subtitle');
}

function b2b_fixed_category_name($seed)
{
    return ((int) $seed % 2 === 0) ? '数码配件' : '办公用品';
}
