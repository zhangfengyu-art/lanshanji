<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTobaccoLogisticsFieldsToProductsTable extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('tobacco_type', 32)->nullable()->after('category_id');
            $table->unsignedInteger('unit_weight_grams')->default(0)->after('tobacco_type');
            $table->unsignedSmallInteger('unit_sticks')->nullable()->after('unit_weight_grams');
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['tobacco_type', 'unit_weight_grams', 'unit_sticks']);
        });
    }
}
