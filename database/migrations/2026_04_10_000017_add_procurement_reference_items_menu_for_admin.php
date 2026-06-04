<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddProcurementReferenceItemsMenuForAdmin extends Migration
{
    public function up()
    {
        $connection = config('admin.database.connection') ?: config('database.default');
        $menuTable = config('admin.database.menu_table');
        $rolesTable = config('admin.database.roles_table');
        $roleMenuTable = config('admin.database.role_menu_table');
        $now = date('Y-m-d H:i:s');

        $menu = DB::connection($connection)->table($menuTable)
            ->where('uri', 'procurement-reference-items')
            ->first();

        if (!$menu) {
            $maxOrder = (int) DB::connection($connection)->table($menuTable)->max('order');
            $menuId = DB::connection($connection)->table($menuTable)->insertGetId([
                'parent_id' => 0,
                'order' => $maxOrder + 1,
                'title' => '参考商品库',
                'icon' => 'fa-tags',
                'uri' => 'procurement-reference-items',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $menuId = $menu->id;
        }

        $role = DB::connection($connection)->table($rolesTable)
            ->where('slug', 'administrator')
            ->first();

        if ($role) {
            $exists = DB::connection($connection)->table($roleMenuTable)
                ->where('role_id', $role->id)
                ->where('menu_id', $menuId)
                ->exists();

            if (!$exists) {
                DB::connection($connection)->table($roleMenuTable)->insert([
                    'role_id' => $role->id,
                    'menu_id' => $menuId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down()
    {
        $connection = config('admin.database.connection') ?: config('database.default');
        $menuTable = config('admin.database.menu_table');

        DB::connection($connection)->table($menuTable)
            ->where('uri', 'procurement-reference-items')
            ->delete();
    }
}
