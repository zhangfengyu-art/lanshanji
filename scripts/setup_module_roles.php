<?php

/**
 * 初始化按模块拆分的后台角色（mod-xxx），供终极管控台勾选分配。
 * 用法: php scripts/setup_module_roles.php
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Admin\AdminPermissionCatalogService;
use Encore\Admin\Auth\Database\Permission;

$now = date('Y-m-d H:i:s');
Permission::query()->firstOrCreate(
    ['slug' => 'support_feedbacks'],
    [
        'name' => '客户反馈',
        'http_method' => '',
        'http_path' => '/support-feedbacks*',
        'created_at' => $now,
        'updated_at' => $now,
    ]
);

app(AdminPermissionCatalogService::class)->ensureModuleRoles();

echo "模块角色已就绪（mod-dashboard、mod-orders 等）。\n";
echo "请在 终极管控台 → 新建/编辑管理员 中使用岗位套餐与模块勾选分配权限。\n";
