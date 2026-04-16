<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserOperationAuditsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_operation_audits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('admin_user_id')->nullable();
            $table->unsignedInteger('target_user_id')->nullable();
            $table->string('action', 80);
            $table->text('detail')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 191)->nullable();
            $table->timestamps();

            $table->index(['action', 'created_at']);
            $table->index(['target_user_id', 'created_at']);
            $table->index(['admin_user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_operation_audits');
    }
}
