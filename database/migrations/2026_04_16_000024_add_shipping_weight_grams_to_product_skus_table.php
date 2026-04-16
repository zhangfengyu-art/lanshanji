<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddShippingWeightGramsToProductSkusTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('product_skus', 'shipping_weight_grams')) {
            Schema::table('product_skus', function (Blueprint $table) {
                $table->unsignedInteger('shipping_weight_grams')->default(30)->after('unit_weight');
            });
        }

        DB::table('product_skus')
            ->whereNull('shipping_weight_grams')
            ->orWhere('shipping_weight_grams', '<=', 0)
            ->update(['shipping_weight_grams' => 30]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('product_skus', 'shipping_weight_grams')) {
            Schema::table('product_skus', function (Blueprint $table) {
                $table->dropColumn('shipping_weight_grams');
            });
        }
    }
}
