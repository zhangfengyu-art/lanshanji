<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAcceptedByToProcurementOrders extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('procurement_orders')) {
            return;
        }

        Schema::table('procurement_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('procurement_orders', 'accepted_by')) {
                $table->unsignedInteger('accepted_by')->nullable()->after('proxy_status');
                $table->foreign('accepted_by')->references('id')->on('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('procurement_orders', 'accepted_at')) {
                $table->timestamp('accepted_at')->nullable()->after('accepted_by');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('procurement_orders')) {
            return;
        }

        Schema::table('procurement_orders', function (Blueprint $table) {
            if (Schema::hasColumn('procurement_orders', 'accepted_by')) {
                $table->dropForeign(['accepted_by']);
                $table->dropColumn('accepted_by');
            }
            if (Schema::hasColumn('procurement_orders', 'accepted_at')) {
                $table->dropColumn('accepted_at');
            }
        });
    }
}
