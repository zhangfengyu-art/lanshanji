<?php

use App\Models\Product;
use App\Models\ProductSku;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSaleStatusToProductsTable extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'sale_status')) {
                $table->string('sale_status', 32)->default(ProductSku::STATUS_ACTIVE)->after('on_sale');
            }
            if (!Schema::hasColumn('products', 'purchase_limit')) {
                $table->unsignedInteger('purchase_limit')->nullable()->after('sale_status');
            }
        });

        if (!Schema::hasTable('products')) {
            return;
        }

        Product::query()->with('skus')->chunkById(100, function ($products) {
            foreach ($products as $product) {
                $status = ProductSku::STATUS_ACTIVE;
                $purchaseLimit = null;
                $totalStock = (int) $product->skus->sum('stock');

                if ($totalStock <= 0) {
                    $status = ProductSku::STATUS_DEPLETED;
                } else {
                    $limitedSku = $product->skus->first(function ($sku) {
                        return (int) $sku->stock > 0 && (int) $sku->limit_qty > 0;
                    });
                    if ($limitedSku) {
                        $status = ProductSku::STATUS_LIMITED;
                        $purchaseLimit = (int) $limitedSku->limit_qty;
                    }
                }

                $product->forceFill([
                    'sale_status' => $status,
                    'purchase_limit' => $purchaseLimit,
                ])->save();
            }
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'purchase_limit')) {
                $table->dropColumn('purchase_limit');
            }
            if (Schema::hasColumn('products', 'sale_status')) {
                $table->dropColumn('sale_status');
            }
        });
    }
}
