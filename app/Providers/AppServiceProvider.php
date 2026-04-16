<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\SiteSetting;
use Monolog\Logger;
use Yansongda\Pay\Pay;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191);

        // 设置中文本地化
        config([
            'app.locale' => 'zh-CN',
            'app.fallback_locale' => 'zh-CN',
            'admin.locale' => 'zh_CN',
        ]);
        app()->setLocale('zh-CN');

        // 同步 admin_menu 菜单标题为中文
        try {
            if (Schema::hasTable('admin_menu')) {
                $menuTitleMap = [
                    '/' => '后台首页',
                    'users' => '用户管理',
                    'products' => '商品管理',
                    'orders' => '订单调度',
                    'coupon_codes' => '优惠券管理',
                    'payment_settings' => '支付设置',
                    'categories' => '分类管理',
                    'site-settings/logo' => '站点设置',
                    'support-feedbacks' => '客服反馈',
                    'auth/logs' => '操作日志',
                    'auth/users' => '管理员',
                    'auth/roles' => '角色管理',
                    'auth/permissions' => '权限管理',
                    'auth/menu' => '菜单管理',
                ];

                foreach ($menuTitleMap as $uri => $title) {
                    \Illuminate\Support\Facades\DB::table('admin_menu')
                        ->where('uri', $uri)
                        ->update(['title' => $title]);
                }
            }
        } catch (\Throwable $e) {
            // Keep admin pages available even when table is not ready.
        }

        $categories = collect();
        $siteLogoUrl = null;
        $siteBrandZh = '岚山烟务所';
        $siteBrandEn = 'ARASHIYAMA TOBACCO SHOP';

        if (Schema::hasTable('categories')) {
            $categoriesQuery = Category::query()
                ->with(['children' => function ($query) {
                    if (Schema::hasColumn('categories', 'site_mode')) {
                        $query->where('site_mode', site_mode());
                    }
                }])
                ->where(function ($query) {
                    $query->whereNull('parent_id')
                        ->orWhere('parent_id', 0);
                })
                ->orderBy('id');

            if (Schema::hasColumn('categories', 'site_mode')) {
                $categoriesQuery->where('site_mode', site_mode());
            }

            $categories = $categoriesQuery->get();
        }

        if (Schema::hasTable('site_settings')) {
            $logoPath = SiteSetting::query()
                ->where('key', 'header_logo')
                ->value('value');

            $siteBrandZh = SiteSetting::query()
                ->where('key', 'header_brand_text_zh')
                ->value('value') ?: $siteBrandZh;
            $siteBrandEn = SiteSetting::query()
                ->where('key', 'header_brand_text_en')
                ->value('value') ?: $siteBrandEn;

            if ($logoPath) {
                $siteLogoUrl = Storage::disk('public')->url($logoPath);
            }
        }

        View::share('categories', $categories);
        View::share('siteLogoUrl', $siteLogoUrl);
        View::share('siteBrandZh', $siteBrandZh);
        View::share('siteBrandEn', $siteBrandEn);
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // 往服务容器中注入一个名为 alipay 的单例对象
        $this->app->singleton('alipay', function () {
            $config = config('pay.alipay');
            $config['notify_url'] = route('payment.alipay.notify');
            $config['return_url'] = route('payment.alipay.return');
            // 判断当前项目运行环境是否为线上环境
            if (app()->environment() !== 'production') {
                $config['mode']         = 'dev';
                $config['log']['level'] = Logger::DEBUG;
            } else {
                $config['log']['level'] = Logger::WARNING;
            }
            // 调用 Yansongda\Pay 来创建一个支付宝支付对象
            return Pay::alipay($config);
        });

        $this->app->singleton('wechat_pay', function () {
            $config = config('pay.wechat');
            $config['notify_url'] = route('payment.wechat.notify');
            $config['http'] = isset($config['http']) && is_array($config['http']) ? $config['http'] : [];
            if (array_key_exists('verify', $config['http']) && is_string($config['http']['verify'])) {
                $normalized = filter_var($config['http']['verify'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($normalized !== null) {
                    $config['http']['verify'] = $normalized;
                }
            }
            $verifyConfigured = array_key_exists('verify', $config['http'])
                && $config['http']['verify'] !== null
                && $config['http']['verify'] !== '';
            if (app()->environment() !== 'production') {
                $config['log']['level'] = Logger::DEBUG;
                // 本地开发机常见 CA 链缺失，默认关闭证书校验避免阻断调试流程。
                if (!$verifyConfigured) {
                    $config['http']['verify'] = false;
                }
            } else {
                $config['log']['level'] = Logger::WARNING;
                if (!$verifyConfigured) {
                    $config['http']['verify'] = true;
                }
            }
            // 调用 Yansongda\Pay 来创建一个微信支付对象
            return Pay::wechat($config);
        });
    }
}
