<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddLimitQtyToProductSkusTable extends Migration
{
    public function up()
    {
        Schema::table('product_skus', function (Blueprint $table) {
            if (!Schema::hasColumn('product_skus', 'limit_qty')) {
                $table->unsignedInteger('limit_qty')->default(0)->after('stock');
            }
        });
    }

    public function down()
    {
        Schema::table('product_skus', function (Blueprint $table) {
            if (Schema::hasColumn('product_skus', 'limit_qty')) {
                $table->dropColumn('limit_qty');
            }
        });
    }
}
