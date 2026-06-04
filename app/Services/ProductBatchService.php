<?php

namespace App\Services;

use App\Exceptions\InvalidRequestException;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSku;
use Illuminate\Support\Collection;

class ProductBatchService
{
    public function productsByIds(array $ids)
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (count($ids) === 0) {
            throw new InvalidRequestException('请先勾选商品');
        }

        return Product::query()->whereIn('id', $ids)->with(['category', 'skus'])->get();
    }

    public function batchSetCategory(array $ids, $categoryId)
    {
        $categoryId = (int) $categoryId;
        if (!Category::query()->where('id', $categoryId)->exists()) {
            throw new InvalidRequestException('目标分类不存在');
        }

        $count = Product::query()->whereIn('id', $ids)->update(['category_id' => $categoryId]);

        return ['updated' => $count, 'message' => '已更新 '.$count.' 个商品的分类'];
    }

    public function batchSetShippingMode(array $ids, $mode)
    {
        $options = ShippingModeService::options();
        if (!array_key_exists($mode, $options)) {
            throw new InvalidRequestException('无效的寄送模式');
        }

        $count = Product::query()->whereIn('id', $ids)->update(['shipping_mode' => $mode]);

        return ['updated' => $count, 'message' => '已设置寄送模式为「'.$options[$mode].'」（'.$count.' 个商品）'];
    }

    public function batchSetTobaccoType(array $ids, $type)
    {
        $options = OrderTobaccoLimitService::tobaccoTypeOptions();
        if (!array_key_exists($type, $options)) {
            throw new InvalidRequestException('无效的烟草分类');
        }

        $updated = 0;
        foreach ($this->productsByIds($ids) as $product) {
            $payload = ['tobacco_type' => $type];
            if (!OrderTobaccoLimitService::countsTowardStickLimit($type)) {
                $payload['unit_sticks'] = null;
            }
            $product->update($payload);
            $updated++;
        }

        return ['updated' => $updated, 'message' => '已设置烟草分类为「'.$options[$type].'」（'.$updated.' 个）'];
    }

    public function batchSetSaleStatus(array $ids, $status, $purchaseLimit = null)
    {
        $options = Product::saleStatusOptions();
        if (!array_key_exists($status, $options)) {
            throw new InvalidRequestException('无效的销售状态');
        }

        $payload = ['sale_status' => $status];
        if ($status === ProductSku::STATUS_LIMITED) {
            $limit = (int) $purchaseLimit;
            if ($limit < 1) {
                throw new InvalidRequestException('限购状态请填写限购数量（至少 1）');
            }
            $payload['purchase_limit'] = $limit;
        } else {
            $payload['purchase_limit'] = null;
        }

        $count = Product::query()->whereIn('id', $ids)->update($payload);

        return ['updated' => $count, 'message' => '已设置销售状态为「'.$options[$status].'」（'.$count.' 个）'];
    }

    public function batchSetOnSale(array $ids, $onSale)
    {
        $value = $onSale ? 1 : 0;
        $count = Product::query()->whereIn('id', $ids)->update(['on_sale' => $value]);
        $label = $onSale ? '已上架' : '已下架';

        return ['updated' => $count, 'message' => $label.' '.$count.' 个商品'];
    }

    public function batchSetLogistics(array $ids, $weightGrams, $unitSticks = null, $onlyEmpty = false)
    {
        $weightGrams = (int) $weightGrams;
        if ($weightGrams < 1) {
            throw new InvalidRequestException('请填写有效的单位重量（克）');
        }

        $updated = 0;
        foreach ($this->productsByIds($ids) as $product) {
            $payload = [];
            if (!$onlyEmpty || (int) $product->unit_weight_grams < 1) {
                $payload['unit_weight_grams'] = $weightGrams;
            }

            if ($unitSticks !== null && $unitSticks !== '') {
                $sticks = (int) $unitSticks;
                if (OrderTobaccoLimitService::countsTowardStickLimit($product->tobacco_type)) {
                    if ($sticks < 1) {
                        throw new InvalidRequestException('商品「'.$product->title.'」为香烟/加热烟，支数至少为 1');
                    }
                    if (!$onlyEmpty || (int) $product->unit_sticks < 1) {
                        $payload['unit_sticks'] = $sticks;
                    }
                }
            }

            if (!empty($payload)) {
                $product->update($payload);
                $updated++;
            }
        }

        return ['updated' => $updated, 'message' => '已更新 '.$updated.' 个商品的物流重量/支数'];
    }

    public function batchSetPurchaseLimit(array $ids, $limit)
    {
        $limit = (int) $limit;
        if ($limit < 1) {
            throw new InvalidRequestException('限购数量至少为 1');
        }

        $updated = 0;
        foreach ($this->productsByIds($ids) as $product) {
            $product->update([
                'sale_status' => ProductSku::STATUS_LIMITED,
                'purchase_limit' => $limit,
            ]);
            $updated++;
        }

        return ['updated' => $updated, 'message' => '已设为限购 '.$limit.' 件/单（'.$updated.' 个）'];
    }

    public function batchInheritCategoryDefaults(array $ids)
    {
        $updated = 0;
        foreach ($this->productsByIds($ids) as $product) {
            $category = $product->category;
            if (!$category || !$category->default_shipping_mode) {
                continue;
            }

            if (!array_key_exists($category->default_shipping_mode, ShippingModeService::options())) {
                continue;
            }

            $payload = [];
            if (empty($product->shipping_mode)) {
                $payload['shipping_mode'] = $category->default_shipping_mode;
            }

            if (!empty($payload)) {
                $product->update($payload);
                $updated++;
            }
        }

        return ['updated' => $updated, 'message' => '已从分类继承寄送模式（'.$updated.' 个，仅处理未单独指定寄送模式的商品）'];
    }

    public function batchAdjustPrice(array $ids, $mode, $value)
    {
        $mode = (string) $mode;
        $value = (float) $value;

        if (!in_array($mode, ['percent', 'fixed'], true)) {
            throw new InvalidRequestException('调价方式无效');
        }

        if ($mode === 'percent' && ($value <= -100 || $value > 500)) {
            throw new InvalidRequestException('百分比调价请在 -100%～500% 之间');
        }

        $skuCount = 0;
        foreach ($this->productsByIds($ids) as $product) {
            foreach ($product->skus as $sku) {
                $price = (float) $sku->price;
                if ($mode === 'percent') {
                    $price = $price * (1 + $value / 100);
                } else {
                    $price = $price + $value;
                }
                $price = max(0.01, round($price, 2));
                $sku->update(['price' => $price]);
                $skuCount++;
            }

            $minPrice = $product->skus()->min('price');
            if ($minPrice !== null) {
                $product->update(['price' => $minPrice]);
            }
        }

        $label = $mode === 'percent'
            ? ($value >= 0 ? '+' : '').$value.'%'
            : ($value >= 0 ? '+' : '').$value.' 日元';

        return [
            'updated' => count($ids),
            'message' => '已按 '.$label.' 调整 '.count($ids).' 个商品、共 '.$skuCount.' 个 SKU 价格',
        ];
    }
}
