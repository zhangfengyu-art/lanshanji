<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSourceRefIdToProductsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'source_ref_id')) {
                $table->string('source_ref_id', 32)->nullable()->after('id');
                $table->index('source_ref_id');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'source_ref_id')) {
                $table->dropIndex(['source_ref_id']);
                $table->dropColumn('source_ref_id');
            }
        });
    }
}
