<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddPaymentSettingsMenuForAdmin extends Migration
{
    public function up()
    {
        $connection = config('admin.database.connection') ?: config('database.default');
        $menuTable = config('admin.database.menu_table');
        $rolesTable = config('admin.database.roles_table');
        $roleMenuTable = config('admin.database.role_menu_table');
        $now = date('Y-m-d H:i:s');

        $menu = DB::connection($connection)->table($menuTable)
            ->where('uri', 'payment_settings')
            ->first();

        if (!$menu) {
            $maxOrder = (int) DB::connection($connection)->table($menuTable)->max('order');
            $menuId = DB::connection($connection)->table($menuTable)->insertGetId([
                'parent_id' => 0,
                'order' => $maxOrder + 1,
                'title' => 'Payment Settings',
                'icon' => 'fa-qrcode',
                'uri' => 'payment_settings',
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
            ->where('uri', 'payment_settings')
            ->delete();
    }
}
