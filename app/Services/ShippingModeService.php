<?php

namespace App\Services;

use App\Exceptions\InvalidRequestException;
use App\Models\Product;

class ShippingModeService
{
    const MODE_EMS = 'ems_self_tax';
    const MODE_TAX_INCLUDED = 'tax_included';

    public static function options()
    {
        return [
            self::MODE_EMS => 'EMS',
            self::MODE_TAX_INCLUDED => '含税包邮',
        ];
    }

    public function resolveForProduct(Product $product)
    {
        $product->loadMissing('category');

        $mode = trim((string) $product->shipping_mode);
        if ($mode !== '' && array_key_exists($mode, self::options())) {
            return $mode;
        }

        $categoryMode = trim((string) optional($product->category)->default_shipping_mode);
        if ($categoryMode !== '' && array_key_exists($categoryMode, self::options())) {
            return $categoryMode;
        }

        return self::MODE_EMS;
    }

    public function label($mode)
    {
        return data_get(self::options(), $mode, '—');
    }

    /**
     * @param array $items [['sku_id'=>, 'amount'=>], ...]
     */
    public function assertSingleMode(array $items)
    {
        $modes = [];

        foreach ($items as $row) {
            $skuId = (int) data_get($row, 'sku_id', 0);
            if ($skuId < 1) {
                continue;
            }

            $sku = \App\Models\ProductSku::query()->with('product.category')->find($skuId);
            if (!$sku || !$sku->product) {
                continue;
            }

            $modes[$this->resolveForProduct($sku->product)] = true;
        }

        if (count($modes) > 1) {
            throw new InvalidRequestException(
                '不能在同一笔订单中混合「EMS」与「含税包邮」商品，请分开下单。'
            );
        }
    }

    public function resolvedModeFromItems(array $items)
    {
        $this->assertSingleMode($items);

        foreach ($items as $row) {
            $skuId = (int) data_get($row, 'sku_id', 0);
            if ($skuId < 1) {
                continue;
            }

            $sku = \App\Models\ProductSku::query()->with('product.category')->find($skuId);
            if ($sku && $sku->product) {
                return $this->resolveForProduct($sku->product);
            }
        }

        return self::MODE_EMS;
    }

    public function isTaxIncluded($mode)
    {
        return $mode === self::MODE_TAX_INCLUDED;
    }
}
