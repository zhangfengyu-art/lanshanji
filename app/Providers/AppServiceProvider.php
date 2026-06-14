<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\SiteSetting;
use Monolog\Logger;
use Yansongda\Pay\Pay;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
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
        $this->app['router']->aliasMiddleware('super.admin', \App\Admin\Middleware\SuperAdminOnly::class);

        // 设置中文本地化
        config([
            'app.locale' => 'zh-CN',
            'app.fallback_locale' => 'zh-CN',
            'admin.locale' => 'zh_CN',
        ]);
        app()->setLocale('zh-CN');

        // 同步后台菜单与权限中文命名
        try {
            if (Schema::hasTable('admin_menu')) {
                $menuTitleMap = [
                    '/' => '后台首页',
                    'users' => '用户管理',
                    'products' => '商品管理',
                    'orders' => '订单调度',
                    'coupon_codes' => '优惠券管理',
                    'procurement-orders' => '代购需求管理',
                    'procurement-reference-items' => '参考商品库',
                    'categories' => '分类管理',
                    'site-settings/logo' => '站点设置',
                    'support-feedbacks' => '客户反馈',
                    'auth/logs' => '操作日志',
                    'auth/users' => '管理员账号',
                    'auth/roles' => '角色管理',
                    'auth/permissions' => '权限管理',
                    'auth/menu' => '菜单管理',
                    'super-console' => '终极管控台',
                ];

                foreach ($menuTitleMap as $uri => $title) {
                    DB::table('admin_menu')
                        ->where('uri', $uri)
                        ->update(['title' => $title]);
                }
            }

            if (Schema::hasTable('admin_permissions')) {
                $permissionMap = [
                    ['paths' => ['/', '/'], 'name' => '后台首页访问'],
                    ['paths' => ['users*', '/users*'], 'name' => '用户管理权限'],
                    ['paths' => ['products*', '/products*'], 'name' => '商品管理权限'],
                    ['paths' => ['orders*', '/orders*'], 'name' => '订单管理权限'],
                    ['paths' => ['coupon_codes*', '/coupon_codes*'], 'name' => '优惠券管理权限'],
                    ['paths' => ['categories*', '/categories*'], 'name' => '分类管理权限'],
                    ['paths' => ['site-settings/logo*', '/site-settings/logo*'], 'name' => '站点设置权限'],
                    ['paths' => ['procurement-orders*', '/procurement-orders*'], 'name' => '代购需求管理权限'],
                    ['paths' => ['procurement-reference-items*', '/procurement-reference-items*'], 'name' => '参考商品库管理权限'],
                    ['paths' => ['auth/logs*', '/auth/logs*'], 'name' => '操作日志查看权限'],
                    ['paths' => ['auth/users*', '/auth/users*'], 'name' => '管理员账号管理权限'],
                    ['paths' => ['auth/roles*', '/auth/roles*'], 'name' => '角色管理权限'],
                    ['paths' => ['auth/permissions*', '/auth/permissions*'], 'name' => '权限管理权限'],
                    ['paths' => ['auth/menu*', '/auth/menu*'], 'name' => '菜单管理权限'],
                ];

                foreach ($permissionMap as $item) {
                    DB::table('admin_permissions')
                        ->where(function ($query) use ($item) {
                            foreach ((array) $item['paths'] as $pathPattern) {
                                $pathPattern = trim((string) $pathPattern);
                                if ($pathPattern === '') {
                                    continue;
                                }

                                $like = str_replace('*', '%', ltrim($pathPattern, '/'));

                                // admin_permissions.http_path 可能是多行规则，也可能带 / 前缀。
                                $query->orWhere('http_path', 'like', '%'.$like.'%');
                                $query->orWhere('http_path', 'like', '%/'.$like.'%');
                            }
                        })
                        ->update(['name' => $item['name']]);
                }

                $permissionSlugMap = [
                    '*' => '系统全量权限',
                    'dashboard' => '后台首页访问',
                    'auth.login' => '后台登录权限',
                    'auth.setting' => '个人设置权限',
                    'auth.users' => '管理员账号管理权限',
                    'auth.roles' => '角色管理权限',
                    'auth.permissions' => '权限管理权限',
                    'auth.menu' => '菜单管理权限',
                    'auth.logs' => '操作日志查看权限',
                ];

                foreach ($permissionSlugMap as $slug => $name) {
                    DB::table('admin_permissions')
                        ->where('slug', $slug)
                        ->update(['name' => $name]);
                }
            }

            if (Schema::hasTable('admin_roles')) {
                DB::table('admin_roles')->where('slug', 'administrator')->update(['name' => '普通管理员（兼容）']);
                DB::table('admin_roles')->where('slug', 'super-admin')->update(['name' => '终极管理员']);
            }
        } catch (\Throwable $e) {
            // Keep admin pages available even when table is not ready.
        }

        $categories = collect();
        $siteLogoUrl = null;
        $siteBrandZh = '岚山烟务所';
        $siteBrandEn = 'ARASHIYAMA TOBACCO SHOP';

        if (Schema::hasTable('categories')) {
            $categories = Category::query()
                ->with('children')
                ->where(function ($query) {
                    $query->whereNull('parent_id')
                        ->orWhere('parent_id', 0);
                })
                ->when(db_has_column('categories', 'sort_order'), function ($query) {
                    $query->orderBy('sort_order');
                })
                ->orderBy('id')
                ->get();
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

        }

        if (function_exists('site_logo_url')) {
            $siteLogoUrl = site_logo_url();
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
            if (app()->environment() !== 'production') {
                $config['log']['level'] = Logger::DEBUG;
            } else {
                $config['log']['level'] = Logger::WARNING;
            }
            // 调用 Yansongda\Pay 来创建一个微信支付对象
            return Pay::wechat($config);
        });
    }
}
