<?php
require __DIR__ . '/bootstrap/app.php';

$app = app();
$menu = \Encore\Admin\Auth\Database\Menu::where('uri', 'like', '%procurement-orders%')->first();
if ($menu) {
    $menu->update(['uri' => 'procurement-orders']);
    echo "✅ 菜单已修复！现在访问：http://127.0.0.1:8000/admin/procurement-orders\n";
} else {
    echo "❌ 菜单未找到\n";
}
