<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddReviewFieldsToProcurementOrders extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('procurement_orders', function (Blueprint $table) {
            $table->tinyInteger('review_status')->default(0)->comment('审核状态: 0=待审核, 1=已通过, 2=已拒绝');
            $table->bigInteger('reviewed_by')->nullable()->comment('审核者ID');
            $table->datetime('reviewed_at')->nullable()->comment('审核时间');
            $table->text('review_comment')->nullable()->comment('审核备注');
            $table->index(['review_status', 'created_at']);
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
            $table->dropIndex(['review_status', 'created_at']);
            $table->dropColumn(['review_status', 'reviewed_by', 'reviewed_at', 'review_comment']);
        });
    }
}
