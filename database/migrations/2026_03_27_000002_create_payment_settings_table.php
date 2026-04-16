<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentSettingsTable extends Migration
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

            \DB::table('payment_settings')->insert([
                'id' => 1,
                'alipay_qr' => null,
                'wechat_qr' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down()
    {
        Schema::dropIfExists('payment_settings');
    }
}
