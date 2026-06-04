<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProcurementReferenceItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('procurement_reference_items')) {
            return;
        }

        Schema::create('procurement_reference_items', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('category', 64)->index();
            $table->decimal('reference_price', 10, 2)->default(0);
            $table->string('image_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('procurement_reference_items');
    }
}
