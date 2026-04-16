<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRelayContractFieldsToShadowOrders extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('shadow_orders')) {
            return;
        }

        Schema::table('shadow_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('shadow_orders', 'source_site')) {
                $table->string('source_site', 16)->default('A')->after('shadow_no');
            }
            if (!Schema::hasColumn('shadow_orders', 'source_order_no')) {
                $table->string('source_order_no', 64)->nullable()->after('source_site');
            }
            if (!Schema::hasColumn('shadow_orders', 'merchant_id')) {
                $table->string('merchant_id', 64)->nullable()->after('source_order_no');
            }
            if (!Schema::hasColumn('shadow_orders', 'amount_minor')) {
                $table->unsignedBigInteger('amount_minor')->default(0)->after('merchant_id');
            }
            if (!Schema::hasColumn('shadow_orders', 'currency')) {
                $table->string('currency', 8)->default('JPY')->after('amount');
            }
            if (!Schema::hasColumn('shadow_orders', 'channel')) {
                $table->string('channel', 16)->default('alipay')->after('currency');
            }
            if (!Schema::hasColumn('shadow_orders', 'return_path')) {
                $table->string('return_path', 255)->default('/orders')->after('channel');
            }
        });

        Schema::table('shadow_orders', function (Blueprint $table) {
            $table->unique(['source_site', 'source_order_no'], 'shadow_orders_source_site_order_unique');
        });
    }

    public function down()
    {
        if (!Schema::hasTable('shadow_orders')) {
            return;
        }

        Schema::table('shadow_orders', function (Blueprint $table) {
            $table->dropUnique('shadow_orders_source_site_order_unique');
        });

        Schema::table('shadow_orders', function (Blueprint $table) {
            if (Schema::hasColumn('shadow_orders', 'return_path')) {
                $table->dropColumn('return_path');
            }
            if (Schema::hasColumn('shadow_orders', 'channel')) {
                $table->dropColumn('channel');
            }
            if (Schema::hasColumn('shadow_orders', 'currency')) {
                $table->dropColumn('currency');
            }
            if (Schema::hasColumn('shadow_orders', 'amount_minor')) {
                $table->dropColumn('amount_minor');
            }
            if (Schema::hasColumn('shadow_orders', 'merchant_id')) {
                $table->dropColumn('merchant_id');
            }
            if (Schema::hasColumn('shadow_orders', 'source_order_no')) {
                $table->dropColumn('source_order_no');
            }
            if (Schema::hasColumn('shadow_orders', 'source_site')) {
                $table->dropColumn('source_site');
            }
        });
    }
}
