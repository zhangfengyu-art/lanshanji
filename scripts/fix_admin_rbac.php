<?php

/**
 * 修复 Laravel-Admin 角色/权限为空导致的「无权访问」。
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Encore\Admin\Auth\Database\Administrator;
use Encore\Admin\Auth\Database\Menu;
use Encore\Admin\Auth\Database\Permission;
use Encore\Admin\Auth\Database\Role;
use Illuminate\Support\Facades\DB;

$now = date('Y-m-d H:i:s');

// ── 1. 超级管理员角色 ──────────────────────────────────────
$role = Role::query()->where('slug', 'administrator')->first();
if (!$role) {
    $role = Role::query()->create([
        'name' => 'Administrator',
        'slug' => 'administrator',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    echo "Created role: administrator\n";
} else {
    echo "Role exists: administrator (id={$role->id})\n";
}

// ── 2. 权限项（与 database/admin.sql 及自定义路由对齐）────
$permissionRows = [
    ['name' => 'All permission', 'slug' => '*', 'http_method' => '', 'http_path' => '*'],
    ['name' => 'Dashboard', 'slug' => 'dashboard', 'http_method' => 'GET', 'http_path' => '/'],
    ['name' => 'Login', 'slug' => 'auth.login', 'http_method' => '', 'http_path' => "/auth/login\r\n/auth/logout"],
    ['name' => 'User setting', 'slug' => 'auth.setting', 'http_method' => 'GET,PUT', 'http_path' => '/auth/setting'],
    ['name' => 'Auth management', 'slug' => 'auth.management', 'http_method' => '', 'http_path' => "/auth/roles\r\n/auth/permissions\r\n/auth/menu\r\n/auth/logs"],
    ['name' => '用户管理', 'slug' => 'users', 'http_method' => '', 'http_path' => '/users*'],
    ['name' => '商品管理', 'slug' => 'products', 'http_method' => '', 'http_path' => '/products*'],
    ['name' => '订单管理', 'slug' => 'orders', 'http_method' => '', 'http_path' => '/orders*'],
    ['name' => '优惠券管理', 'slug' => 'coupon_codes', 'http_method' => '', 'http_path' => '/coupon_codes*'],
    ['name' => '分类管理', 'slug' => 'categories', 'http_method' => '', 'http_path' => '/categories*'],
    ['name' => '站点设置', 'slug' => 'site_settings', 'http_method' => '', 'http_path' => '/site-settings*'],
    ['name' => '代购订单', 'slug' => 'procurement_orders', 'http_method' => '', 'http_path' => '/procurement-orders*'],
    ['name' => '参考商品库', 'slug' => 'procurement_reference_items', 'http_method' => '', 'http_path' => '/procurement-reference-items*'],
    ['name' => '代购资质审核', 'slug' => 'proxy_qualifications', 'http_method' => '', 'http_path' => '/proxy-qualifications*'],
];

$permissionIds = [];
foreach ($permissionRows as $row) {
    $perm = Permission::query()->firstOrCreate(
        ['slug' => $row['slug']],
        array_merge($row, ['created_at' => $now, 'updated_at' => $now])
    );
    $permissionIds[] = $perm->id;
}
echo 'Permissions synced: ' . count($permissionIds) . "\n";

// 超级管理员角色拥有全部权限
$role->permissions()->sync($permissionIds);

// ── 3. 绑定 admin 用户 ───────────────────────────────────
$user = Administrator::query()->where('username', 'admin')->first();
if (!$user) {
    fwrite(STDERR, "User admin not found. Run: php scripts/create_admin_user.php\n");
    exit(1);
}

$user->roles()->sync([$role->id]);
// 双保险：直接赋予「全部权限」
$allPermId = Permission::query()->where('slug', '*')->value('id');
if ($allPermId) {
    $user->permissions()->sync([$allPermId]);
}

echo "User {$user->username} (id={$user->id}) -> role administrator\n";

// ── 4. 确保有后台首页菜单 ─────────────────────────────────
if (!Menu::query()->where('uri', '/')->exists()) {
    Menu::query()->create([
        'parent_id' => 0,
        'order' => 0,
        'title' => '首页',
        'icon' => 'fa-bar-chart',
        'uri' => '/',
    ]);
    echo "Created home menu\n";
}

// ── 5. 菜单对超级管理员可见（admin_role_menu）────────────
$menuIds = Menu::query()->pluck('id')->all();
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
echo 'Menus linked to administrator: ' . count($menuIds) . "\n";

echo json_encode([
    'ok' => true,
    'username' => $user->username,
    'role' => $role->slug,
    'permissions_count' => count($permissionIds),
    'login' => 'http://127.0.0.1:8000/admin/auth/login',
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
