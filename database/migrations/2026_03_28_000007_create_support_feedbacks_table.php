<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSupportFeedbacksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('support_feedbacks', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('order_no', 32);
            $table->string('contact_name', 30);
            $table->string('contact_phone', 20);
            $table->string('question_type', 32);
            $table->text('message');
            $table->string('status', 32)->default('PENDING_REVIEW');
            $table->text('admin_reply')->nullable();
            $table->unsignedInteger('handled_by')->nullable();
            $table->dateTime('handled_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('order_no');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('support_feedbacks');
    }
}
