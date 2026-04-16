<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePaymentSettingsTableV2 extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('payment_settings')) {
            Schema::create('payment_settings', function (Blueprint $table) {
                $table->increments('id');
                $table->string('alipay_qr')->nullable();
                $table->string('wechat_qr')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('payment_settings');
    }
}
