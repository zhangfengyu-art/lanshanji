<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSortOrderToCategoriesAndProducts extends Migration
{
    public function up()
    {
        if (Schema::hasTable('categories') && !Schema::hasColumn('categories', 'sort_order')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->unsignedInteger('sort_order')->default(0);
            });

            DB::table('categories')->orderBy('id')->get(['id'])->each(function ($row) {
                DB::table('categories')->where('id', $row->id)->update(['sort_order' => (int) $row->id]);
            });
        }

        if (Schema::hasTable('products') && !Schema::hasColumn('products', 'sort_order')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unsignedInteger('sort_order')->default(0);
            });

            DB::table('products')->orderBy('id')->get(['id'])->each(function ($row) {
                DB::table('products')->where('id', $row->id)->update(['sort_order' => (int) $row->id]);
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('categories') && Schema::hasColumn('categories', 'sort_order')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropColumn('sort_order');
            });
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'sort_order')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('sort_order');
            });
        }
    }
}
