<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Encore\Admin\Auth\Database\Menu;

class FixProcurementOrdersMenu extends Command
{
    protected $signature = 'admin:fix-procurement-menu';
    protected $description = 'Fix procurement orders menu URI';

    public function handle()
    {
        $menu = Menu::where('uri', 'like', '%procurement-orders%')->first();
        
        if (!$menu) {
            $this->error('菜单未找到');
            return;
        }

        $menu->update(['uri' => 'procurement-orders']);
        $this->info('✅ 菜单已修复！');
        $this->info('现在访问：http://127.0.0.1:8000/admin/procurement-orders');
    }
}
