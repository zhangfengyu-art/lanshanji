<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddLogisticsFieldsToProductSkusTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('product_skus', function (Blueprint $table) {
            $table->enum('item_type', ['cigarette', 'tobacco_silk'])->nullable()->after('limit_qty');
            $table->unsignedInteger('unit_sticks')->default(0)->after('item_type');
            $table->unsignedInteger('unit_weight')->default(0)->after('unit_sticks');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('product_skus', function (Blueprint $table) {
            $table->dropColumn(['item_type', 'unit_sticks', 'unit_weight']);
        });
    }
}
