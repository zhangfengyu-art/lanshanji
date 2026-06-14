<?php

/**
 * 轮换终极管理员：生成新账号、删除其它后台账号。
 * 用法: php scripts/rotate_super_admin.php
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

require __DIR__.'/../database/migrations/2026_05_26_000001_setup_super_admin_console.php';
$migration = new SetupSuperAdminConsole();
$migration->up();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function generate_admin_password($length = 20)
{
    $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $lower = 'abcdefghjkmnpqrstuvwxyz';
    $digits = '23456789';
    $all = $upper.$lower.$digits.'#$@';

  $chars = [
        $upper[random_int(0, strlen($upper) - 1)],
        $lower[random_int(0, strlen($lower) - 1)],
        $digits[random_int(0, strlen($digits) - 1)],
    ];

    for ($i = count($chars); $i < $length; $i++) {
        $chars[] = $all[random_int(0, strlen($all) - 1)];
    }

    shuffle($chars);

    return implode('', $chars);
}

$username = 'super-'.strtolower(Str::random(8));
$password = generate_admin_password(20);
$name = '终极管理员';
$now = date('Y-m-d H:i:s');

$superRoleId = DB::table('admin_roles')->where('slug', 'super-admin')->value('id');
if (!$superRoleId) {
    fwrite(STDERR, "缺少 super-admin 角色\n");
    exit(1);
}

$ruTable = config('admin.database.role_users_table', 'admin_role_users');
$upTable = config('admin.database.user_permissions_table', 'admin_user_permissions');
$usersTable = config('admin.database.users_table', 'admin_users');

$userId = DB::table($usersTable)->insertGetId([
    'username' => $username,
    'password' => bcrypt($password),
    'name' => $name,
    'remember_token' => Str::random(60),
    'password_changed_at' => $now,
    'created_at' => $now,
    'updated_at' => $now,
]);

DB::table($ruTable)->insert([
    'role_id' => $superRoleId,
    'user_id' => $userId,
    'created_at' => $now,
    'updated_at' => $now,
]);

$starId = DB::table('admin_permissions')->where('slug', '*')->value('id');
if ($starId) {
    DB::table($upTable)->insert([
        'user_id' => $userId,
        'permission_id' => $starId,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

foreach (DB::table($usersTable)->where('id', '!=', $userId)->pluck('id') as $oid) {
    DB::table($ruTable)->where('user_id', $oid)->delete();
    DB::table($upTable)->where('user_id', $oid)->delete();
    DB::table($usersTable)->where('id', $oid)->delete();
}

$prefix = trim((string) config('admin.route.prefix', 'admin'), '/');
$base = rtrim((string) env('APP_URL', ''), '/');

echo "========== 新超级管理员（请立即保存，勿提交到 Git）==========\n";
echo "登录地址: {$base}/{$prefix}/auth/login\n";
echo "用户名: {$username}\n";
echo "密码: {$password}\n";
echo "管控台: {$base}/{$prefix}/super-console\n";
echo "==========================================================\n";
