<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSupportFeedbacksTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('support_feedbacks')) {
            return;
        }

        Schema::create('support_feedbacks', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('order_no', 64)->nullable();
            $table->string('contact_name', 64)->nullable();
            $table->string('contact_phone', 32)->nullable();
            $table->string('question_type', 64)->nullable();
            $table->text('message');
            $table->text('images')->nullable();
            $table->string('status', 32)->default('pending');
            $table->text('admin_reply')->nullable();
            $table->unsignedInteger('handled_by')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('support_feedbacks');
    }
}
