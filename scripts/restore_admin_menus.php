<?php

/**
 * 恢复 Laravel-Admin 核心业务菜单（商品、订单、用户、优惠券、代购、客户反馈等）。
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Encore\Admin\Auth\Database\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$now = date('Y-m-d H:i:s');

$menus = [
    ['parent_id' => 0, 'order' => 0, 'title' => '后台首页', 'icon' => 'fa-bar-chart', 'uri' => '/'],
    ['parent_id' => 0, 'order' => 10, 'title' => '订单管理', 'icon' => 'fa-rmb', 'uri' => 'orders'],
    ['parent_id' => 0, 'order' => 20, 'title' => '商品管理', 'icon' => 'fa-cubes', 'uri' => 'products'],
    ['parent_id' => 0, 'order' => 30, 'title' => '用户管理', 'icon' => 'fa-users', 'uri' => 'users'],
    ['parent_id' => 0, 'order' => 40, 'title' => '客户反馈', 'icon' => 'fa-comments', 'uri' => 'support-feedbacks'],
    ['parent_id' => 0, 'order' => 50, 'title' => '优惠券管理', 'icon' => 'fa-tags', 'uri' => 'coupon_codes'],
    ['parent_id' => 0, 'order' => 60, 'title' => '分类管理', 'icon' => 'fa-sitemap', 'uri' => 'categories'],
    ['parent_id' => 0, 'order' => 70, 'title' => '代购需求', 'icon' => 'fa-shopping-bag', 'uri' => 'procurement-orders'],
    ['parent_id' => 0, 'order' => 80, 'title' => '参考商品库', 'icon' => 'fa-database', 'uri' => 'procurement-reference-items'],
    ['parent_id' => 0, 'order' => 90, 'title' => '代购资质审核', 'icon' => 'fa-shield', 'uri' => 'proxy-qualifications'],
    ['parent_id' => 0, 'order' => 100, 'title' => '站点设置', 'icon' => 'fa-cog', 'uri' => 'site-settings/logo'],
];

$menuIds = [];
foreach ($menus as $row) {
    $existing = DB::table('admin_menu')->where('uri', $row['uri'])->first();
    if ($existing) {
        DB::table('admin_menu')->where('id', $existing->id)->update([
            'parent_id' => $row['parent_id'],
            'order' => $row['order'],
            'title' => $row['title'],
            'icon' => $row['icon'],
            'updated_at' => $now,
        ]);
        $menuIds[] = (int) $existing->id;
    } else {
        $menuIds[] = (int) DB::table('admin_menu')->insertGetId(array_merge($row, [
            'created_at' => $now,
            'updated_at' => $now,
        ]));
    }
}

$extraPermissions = [
    ['name' => '用户管理', 'slug' => 'users', 'http_method' => '', 'http_path' => '/users*'],
    ['name' => '客户反馈', 'slug' => 'support_feedbacks', 'http_method' => '', 'http_path' => '/support-feedbacks*'],
    ['name' => '代购需求', 'slug' => 'procurement_orders', 'http_method' => '', 'http_path' => '/procurement-orders*'],
];

foreach ($extraPermissions as $row) {
    if (!DB::table('admin_permissions')->where('slug', $row['slug'])->exists()) {
        DB::table('admin_permissions')->insert(array_merge($row, [
            'created_at' => $now,
            'updated_at' => $now,
        ]));
    }
}

$role = Role::query()->where('slug', 'administrator')->first();
if ($role) {
    $permIds = DB::table('admin_permissions')->pluck('id')->all();
    $role->permissions()->sync($permIds);

    $pivot = config('admin.database.role_menu_table', 'admin_role_menu');
    DB::table($pivot)->where('role_id', $role->id)->delete();
    foreach ($menuIds as $menuId) {
        DB::table($pivot)->insert([
            'role_id' => $role->id,
            'menu_id' => $menuId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}

echo json_encode([
    'ok' => true,
    'menus' => count($menuIds),
    'support_feedbacks_table' => Schema::hasTable('support_feedbacks'),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
