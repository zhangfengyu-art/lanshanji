<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddShippingModeFields extends Migration
{
    public function up()
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('default_shipping_mode', 32)->nullable()->after('is_directory');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('shipping_mode', 32)->nullable()->after('category_id');
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('shipping_mode');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('default_shipping_mode');
        });
    }
}
