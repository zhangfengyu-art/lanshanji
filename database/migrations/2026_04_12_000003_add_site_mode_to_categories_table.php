<?php

use App\Models\Category;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddSiteModeToCategoriesTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('categories')) {
            return;
        }

        if (!Schema::hasColumn('categories', 'site_mode')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->string('site_mode', 1)->default(Category::SITE_MODE_A)->after('name');
                $table->index('site_mode');
            });
        }

        DB::table('categories')->whereNull('site_mode')->update(['site_mode' => Category::SITE_MODE_A]);

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'is_from_native_procurement')) {
            $stats = DB::table('products')
                ->select(
                    'category_id',
                    DB::raw('SUM(CASE WHEN is_from_native_procurement = 1 THEN 1 ELSE 0 END) AS b_count'),
                    DB::raw('SUM(CASE WHEN is_from_native_procurement = 1 THEN 0 ELSE 1 END) AS a_count')
                )
                ->whereNotNull('category_id')
                ->groupBy('category_id')
                ->get();

            foreach ($stats as $row) {
                $siteMode = ((int) $row->b_count > 0 && (int) $row->a_count === 0)
                    ? Category::SITE_MODE_B
                    : Category::SITE_MODE_A;

                DB::table('categories')
                    ->where('id', (int) $row->category_id)
                    ->update(['site_mode' => $siteMode]);
            }
        }
    }

    public function down()
    {
        if (!Schema::hasTable('categories') || !Schema::hasColumn('categories', 'site_mode')) {
            return;
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['site_mode']);
            $table->dropColumn('site_mode');
        });
    }
}
