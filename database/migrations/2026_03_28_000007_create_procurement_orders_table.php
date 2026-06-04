<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProcurementOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('procurement_orders', function (Blueprint $table) {
            $table->increments('id');
            $table->string('no')->unique();
            $table->unsignedInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->string('item_name');
            $table->string('item_image')->nullable();
            $table->string('buyer_nickname');
            $table->unsignedTinyInteger('proxy_status')->default(0);
            $table->text('order_narrative')->nullable();
            $table->decimal('budget_amount', 10, 2)->nullable();
            $table->text('extra')->nullable();
            $table->timestamps();

            $table->index(['proxy_status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('procurement_orders');
    }
}