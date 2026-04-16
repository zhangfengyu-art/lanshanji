<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddNativeProcurementFlagToProducts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_from_native_procurement')->default(false)->after('on_sale')->comment('是否来自原生B站求购');
            $table->unsignedBigInteger('procurement_order_id')->nullable()->after('is_from_native_procurement')->comment('关联的ProcurementOrder ID');
            $table->index('is_from_native_procurement');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['is_from_native_procurement']);
            $table->dropColumn('procurement_order_id');
            $table->dropColumn('is_from_native_procurement');
        });
    }
}
