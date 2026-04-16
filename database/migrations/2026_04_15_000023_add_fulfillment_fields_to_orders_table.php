<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFulfillmentFieldsToOrdersTable extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('fulfillment_photo')->nullable()->after('ship_data');
            $table->string('tracking_no')->nullable()->after('fulfillment_photo');
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['fulfillment_photo', 'tracking_no']);
        });
    }
}