<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProxyQualificationsTable extends Migration
{
    public function up()
    {
        Schema::create('proxy_qualifications', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // 上传的材料
            $table->string('id_card_front')->comment('身份证正面');
            $table->string('id_card_back')->comment('身份证背面');
            $table->string('flight_ticket')->comment('机票凭证');

            // 审核状态: 0=待审核 1=已通过 2=已拒绝
            $table->unsignedTinyInteger('status')->default(0)->comment('0待审核 1通过 2拒绝');

            $table->text('reject_reason')->nullable()->comment('拒绝原因');
            $table->unsignedInteger('reviewed_by')->nullable()->comment('审核管理员ID');
            $table->timestamp('reviewed_at')->nullable();

            $table->string('applicant_note')->nullable()->comment('申请人备注');

            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('proxy_qualifications');
    }
}
