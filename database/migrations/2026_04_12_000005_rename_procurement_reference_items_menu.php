<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class RenameProcurementReferenceItemsMenu extends Migration
{
    public function up()
    {
        $connection = config('admin.database.connection') ?: config('database.default');
        $menuTable = config('admin.database.menu_table');

        DB::connection($connection)->table($menuTable)
            ->where('uri', 'procurement-reference-items')
            ->update([
                'title' => '代购素材库',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    public function down()
    {
        $connection = config('admin.database.connection') ?: config('database.default');
        $menuTable = config('admin.database.menu_table');

        DB::connection($connection)->table($menuTable)
            ->where('uri', 'procurement-reference-items')
            ->update([
                'title' => '参考商品库',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }
}