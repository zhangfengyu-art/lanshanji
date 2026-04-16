<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Encore\Admin\Auth\Database\Menu;

class AddProcurementOrdersMenu extends Command
{
    protected $signature = 'admin:add-procurement-menu';
    protected $description = 'Add procurement orders menu to admin panel';

    public function handle()
    {
        // 检查菜单是否已存在
        $exists = Menu::where('uri', 'like', '%procurement-orders%')->exists();
        
        if ($exists) {
            $this->info('菜单项已存在！');
            return;
        }

        // 创建菜单项
        Menu::create([
            'title' => '原生求购审核',
            'icon' => 'fa-check-square',
            'uri' => 'admin/procurement-orders',
            'parent_id' => 0,
            'order' => 99,
        ]);

        $this->info('✅ 菜单项已添加！访问 http://127.0.0.1:8000/admin/procurement-orders');
    }
}
