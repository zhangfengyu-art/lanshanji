<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateShadowOrdersTable extends Migration
{
    public function up()
    {
        Schema::create('shadow_orders', function (Blueprint $table) {
            $table->increments('id');
            $table->string('shadow_no', 32)->unique();
            $table->decimal('amount', 12, 2);
            $table->string('status', 20)->default('pending');
            $table->string('signature_hash', 64);
            $table->timestamp('paid_at')->nullable();
            $table->text('meta')->nullable();
            $table->timestamps();

            $table->index(['status', 'amount']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('shadow_orders');
    }
}
