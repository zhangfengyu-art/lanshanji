<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddOrderNoToProcurementOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('procurement_orders')) {
            return;
        }

        Schema::table('procurement_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('procurement_orders', 'order_no')) {
                $table->string('order_no', 32)->nullable()->after('no');
                $table->index('order_no');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (!Schema::hasTable('procurement_orders') || !Schema::hasColumn('procurement_orders', 'order_no')) {
            return;
        }

        Schema::table('procurement_orders', function (Blueprint $table) {
            $table->dropIndex(['order_no']);
            $table->dropColumn('order_no');
        });
    }
}