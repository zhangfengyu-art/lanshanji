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

    $envModeRaw = strtoupper((string) env('SITE_MODE', ''));
    if ($envModeRaw === 'B') {
        return 'B';
    }

    try {
        if (function_exists('request') && app()->bound('request')) {
            $request = request();
            $host = strtolower((string) $request->getHost());
            $port = (int) $request->getPort();

            if ($port === 8001) {
                return 'B';
            }

            $bHosts = array_filter([
                strtolower((string) env('B_SITE_HOST', '')),
                strtolower((string) config('site.b_host', '')),
                strtolower((string) config('site.b_domain', '')),
            ]);

            foreach ($bHosts as $bHost) {
                if ($bHost !== '' && $host === $bHost) {
                    return 'B';
                }
            }
        }
    } catch (\Throwable $e) {
        // fall through to DB and default A
    }

    try {
        if (class_exists('App\\Models\\SiteSetting') && \Illuminate\Support\Facades\Schema::hasTable('site_settings')) {
            $cache = app('cache');
            $mode = $cache->remember('site.active_mode', 60, function () {
                return optional(\App\Models\SiteSetting::query()->where('key', 'active_site_mode')->first())->value;
            });

            if ($normalize($mode) === 'B') {
                return 'B';
            }
        }
    } catch (\Throwable $e) {
        // fall through to default A
    }

    return 'A';
}

function is_site_mode_b()
{
    return site_mode() === 'B';
}

function is_site_mode_a()
{
    return site_mode() === 'A';
}

function b2b_fixed_category_name($seed)
{
    return ((int) $seed % 2 === 0) ? '数码配件' : '办公用品';
}
