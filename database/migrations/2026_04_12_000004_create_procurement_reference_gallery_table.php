<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProcurementReferenceGalleryTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('procurement_reference_gallery')) {
            return;
        }

        Schema::create('procurement_reference_gallery', function (Blueprint $table) {
            $table->increments('id');
            $table->string('item_name', 255);
            $table->decimal('reference_price', 12, 2)->default(0);
            $table->unsignedInteger('category_id')->nullable()->index();
            $table->string('image_url', 1024)->nullable();
            $table->timestamps();

            $table->index(['reference_price', 'category_id'], 'procurement_reference_gallery_price_category_index');
            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->onDelete('set null');
        });
    }

    public function down()
    {
        if (!Schema::hasTable('procurement_reference_gallery')) {
            return;
        }

        Schema::table('procurement_reference_gallery', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
        });

        Schema::dropIfExists('procurement_reference_gallery');
    }
}