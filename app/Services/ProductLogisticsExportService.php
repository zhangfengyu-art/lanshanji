<?php

namespace App\Services;

use App\Models\Product;

class ProductLogisticsExportService
{
    public static function buildQuery()
    {
        return Product::query()
            ->with('category:id,name,default_shipping_mode')
            ->orderBy('id');
    }

    public static function isIncomplete(Product $product)
    {
        $type = (string) $product->tobacco_type;
        $options = OrderTobaccoLimitService::tobaccoTypeOptions();

        if (!array_key_exists($type, $options)) {
            return true;
        }

        if ((int) $product->unit_weight_grams < 1) {
            return true;
        }

        if (OrderTobaccoLimitService::countsTowardStickLimit($type) && (int) $product->unit_sticks < 1) {
            return true;
        }

        if ($product->sale_status === \App\Models\ProductSku::STATUS_LIMITED && (int) $product->purchase_limit < 1) {
            return true;
        }

        return false;
    }

    public static function issueLabels(Product $product)
    {
        $issues = [];
        $type = (string) $product->tobacco_type;
        $options = OrderTobaccoLimitService::tobaccoTypeOptions();

        if (!array_key_exists($type, $options)) {
            $issues[] = '未设置烟草分类';
        }
        if ((int) $product->unit_weight_grams < 1) {
            $issues[] = '未设置重量';
        }
        if (OrderTobaccoLimitService::countsTowardStickLimit($type) && (int) $product->unit_sticks < 1) {
            $issues[] = '未设置支数';
        }
        if ($product->sale_status === \App\Models\ProductSku::STATUS_LIMITED && (int) $product->purchase_limit < 1) {
            $issues[] = '限购未填数量';
        }

        return implode('；', $issues);
    }

    public static function headers()
    {
        return [
            '商品ID',
            '商品名称',
            '分类',
            '寄送模式',
            '烟草分类',
            '重量(g)',
            '支数',
            '销售状态',
            '问题说明',
        ];
    }

    public static function row(Product $product)
    {
        $shipping = $product->shipping_mode
            ?: optional($product->category)->default_shipping_mode;
        $shippingLabel = ShippingModeService::options()[$shipping] ?? $shipping;

        return [
            $product->id,
            $product->title,
            optional($product->category)->name,
            $shippingLabel,
            OrderTobaccoLimitService::tobaccoTypeOptions()[$product->tobacco_type] ?? '—',
            (int) $product->unit_weight_grams ?: '—',
            (int) $product->unit_sticks ?: '—',
            Product::saleStatusOptions()[$product->sale_status] ?? $product->sale_status,
            self::issueLabels($product),
        ];
    }

    public static function filename()
    {
        return '物流信息未完备商品_'.date('Ymd_His').'.csv';
    }
}
