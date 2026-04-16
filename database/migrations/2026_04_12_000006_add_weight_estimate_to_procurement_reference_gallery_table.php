<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWeightEstimateToProcurementReferenceGalleryTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('procurement_reference_gallery')) {
            return;
        }

        Schema::table('procurement_reference_gallery', function (Blueprint $table) {
            if (!Schema::hasColumn('procurement_reference_gallery', 'weight_estimate')) {
                $table->decimal('weight_estimate', 10, 2)->nullable()->after('image_url');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('procurement_reference_gallery')) {
            return;
        }

        Schema::table('procurement_reference_gallery', function (Blueprint $table) {
            if (Schema::hasColumn('procurement_reference_gallery', 'weight_estimate')) {
                $table->dropColumn('weight_estimate');
            }
        });
    }
}