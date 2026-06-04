<?php

/**
 * 初始化终极管理员角色、菜单与 admin 账号绑定。
 * 用法: php scripts/setup_super_admin.php
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

require __DIR__.'/../database/migrations/2026_05_26_000001_setup_super_admin_console.php';

$migration = new SetupSuperAdminConsole();
$migration->up();

echo "终极管理员已配置。请使用带「终极管理员」角色的账号登录，访问：\n";
echo rtrim((string) env('APP_URL', 'http://127.0.0.1:8000'), '/').'/admin/super-console'.PHP_EOL;
