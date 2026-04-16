<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAcceptedByToProcurementOrders extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('procurement_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('accepted_by')->nullable()->after('user_id')->comment('代购方用户ID，接单后记录');
            $table->timestamp('accepted_at')->nullable()->after('accepted_by')->comment('接单时间');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('procurement_orders', function (Blueprint $table) {
            $table->dropColumn(['accepted_by', 'accepted_at']);
        });
    }
}
