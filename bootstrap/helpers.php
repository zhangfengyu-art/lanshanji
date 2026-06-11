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

    // Dual-server production: trust .env SITE_MODE (skip remote schema checks).
    if ($envMode !== null && ($isProduction || $isDevPort)) {
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
 * 将 site_settings 中的相对路径转为可访问 URL。
 */
function site_setting_image_url($storageRelativePath)
{
    $path = trim((string) $storageRelativePath);
    if ($path === '') {
        return null;
    }

    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    $relative = ltrim(str_replace('\\', '/', $path), '/');

    return asset('storage/'.$relative);
}

/**
 * A 站首页左上角 Logo（header_logo）。
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

        $url = site_setting_image_url($path);
    } catch (\Throwable $e) {
        $url = null;
    }

    return $url;
}

/**
 * 当前站点模式对应的 favicon 存储相对路径。
 */
function site_favicon_storage_path()
{
    try {
        if (!\Illuminate\Support\Facades\Schema::hasTable('site_settings')) {
            return '';
        }

        return app(\App\Services\SiteFaviconService::class)->storagePathForMode();
    } catch (\Throwable $e) {
        return '';
    }
}

/**
 * 当前站点模式 favicon 的本地绝对路径（供 /favicon.ico 路由使用）。
 */
function site_favicon_absolute_path()
{
    try {
        return app(\App\Services\SiteFaviconService::class)->absolutePathForMode();
    } catch (\Throwable $e) {
        return null;
    }
}

/**
 * 浏览器标签页图标：A 站用 favicon_a，B 站用 favicon_b。
 */
function site_favicon_url()
{
    $relative = site_favicon_storage_path();
    if ($relative === '') {
        return null;
    }

    $url = site_setting_image_url($relative);
    if (!$url) {
        return null;
    }

    return $url.'?v='.site_favicon_version();
}

/**
 * favicon 缓存破坏参数。
 */
function site_favicon_version()
{
    static $version = null;

    if ($version !== null) {
        return $version;
    }

    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('site_settings')) {
            $service = app(\App\Services\SiteFaviconService::class);
            $key = $service->faviconKeyForMode();
            $updatedAt = \App\Models\SiteSetting::query()
                ->where('key', $key)
                ->value('updated_at');

            if ($updatedAt) {
                $version = (string) strtotime((string) $updatedAt);

                return $version;
            }

            if ($key === \App\Services\SiteFaviconService::KEY_FAVICON_A) {
                $logoUpdatedAt = \App\Models\SiteSetting::query()
                    ->where('key', \App\Services\SiteFaviconService::KEY_HEADER_LOGO)
                    ->value('updated_at');

                if ($logoUpdatedAt) {
                    $version = (string) strtotime((string) $logoUpdatedAt);

                    return $version;
                }
            }
        }
    } catch (\Throwable $e) {
        // ignore
    }

    $version = (string) time();

    return $version;
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
