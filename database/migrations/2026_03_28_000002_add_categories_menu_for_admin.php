<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddCategoriesMenuForAdmin extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $exists = DB::table('admin_menu')->where('uri', 'categories')->exists();
        if ($exists) {
            return;
        }

        $lastOrder = (int) DB::table('admin_menu')->where('parent_id', 0)->max('order');

        $data = [
            'parent_id' => 0,
            'order' => $lastOrder + 1,
            'title' => '分类管理',
            'icon' => 'fa-tags',
            'uri' => 'categories',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('admin_menu', 'permission')) {
            $data['permission'] = null;
        }

        DB::table('admin_menu')->insert($data);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('admin_menu')->where('uri', 'categories')->delete();
    }
}
