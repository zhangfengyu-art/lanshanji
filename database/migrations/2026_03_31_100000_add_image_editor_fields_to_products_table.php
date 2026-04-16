<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddImageEditorFieldsToProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('image_original', 191)->nullable()->after('image');
            $table->text('image_crop_meta')->nullable()->after('image_original');
            $table->unsignedInteger('image_editor_version')->default(1)->after('image_crop_meta');
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
            $table->dropColumn(['image_original', 'image_crop_meta', 'image_editor_version']);
        });
    }
}
