<?php

namespace App\Services;

use App\Exceptions\InvalidRequestException;
use App\Models\Product;
use App\Models\ProductSku;

class OrderTobaccoLimitService
{
    const TYPE_CIGARETTE = 'cigarette';
    const TYPE_HEATED_TOBACCO = 'heated_tobacco';
    const TYPE_ROLLING_TOBACCO = 'rolling_tobacco';
    const TYPE_NON_TOBACCO = 'non_tobacco';

    public static function tobaccoTypeOptions()
    {
        return [
            self::TYPE_CIGARETTE => '香烟',
            self::TYPE_HEATED_TOBACCO => '加热烟',
            self::TYPE_ROLLING_TOBACCO => '手卷烟丝',
            self::TYPE_NON_TOBACCO => '其它商品（仅计重量）',
        ];
    }

    public static function countsTowardStickLimit($tobaccoType)
    {
        return in_array($tobaccoType, [self::TYPE_CIGARETTE, self::TYPE_HEATED_TOBACCO], true);
    }

    public function maxCigaretteSticks()
    {
        return (int) config('ems_shipping.tobacco_limits.max_cigarette_sticks', 400);
    }

    public function maxCigaretteBoxes()
    {
        return (int) config('ems_shipping.tobacco_limits.max_cigarette_boxes', 20);
    }

    public function maxRollingTobaccoGrams()
    {
        return (int) config('ems_shipping.tobacco_limits.max_rolling_tobacco_grams', 5000);
    }

    public function settlementPackagingGramsPerUnit()
    {
        return max(0, (int) config('ems_shipping.settlement_packaging_grams_per_unit', 100));
    }

    /**
     * @param array $items [['sku_id' => int, 'amount' => int], ...]
     */
    public function analyzeSkuItems(array $items)
    {
        $lines = [];
        $totalWeightGrams = 0;
        $totalCigaretteSticks = 0;
        $totalCigaretteBoxes = 0;
        $totalRollingTobaccoGrams = 0;
        $packagingPerUnit = $this->settlementPackagingGramsPerUnit();

        foreach ($items as $row) {
            $skuId = (int) data_get($row, 'sku_id', 0);
            $amount = (int) data_get($row, 'amount', 0);
            if ($skuId < 1 || $amount < 1) {
                continue;
            }

            $sku = ProductSku::query()->with('product')->find($skuId);
            if (!$sku || !$sku->product) {
                throw new InvalidRequestException('购物车中存在无效商品，请刷新后重试。');
            }

            $product = $sku->product;
            $this->assertProductLogisticsConfigured($product);

            $unitWeight = (int) $product->unit_weight_grams;
            $lineWeight = $amount * $unitWeight;
            $lineBillableWeight = $lineWeight + ($amount * $packagingPerUnit);
            $lineSticks = 0;
            if (self::countsTowardStickLimit($product->tobacco_type)) {
                $sticksPerUnit = (int) $product->unit_sticks;
                if ($sticksPerUnit < 1) {
                    throw new InvalidRequestException('商品「'.$product->title.'」未设置每包/每盒支数。');
                }
                $lineSticks = $amount * $sticksPerUnit;
                $totalCigaretteSticks += $lineSticks;
                if ($product->tobacco_type === self::TYPE_CIGARETTE) {
                    $totalCigaretteBoxes += $amount;
                }
            } elseif ($product->tobacco_type === self::TYPE_ROLLING_TOBACCO) {
                $totalRollingTobaccoGrams += $lineWeight;
            }

            $totalWeightGrams += $lineBillableWeight;

            $lines[] = [
                'sku_id' => $skuId,
                'product_id' => $product->id,
                'product_title' => $product->title,
                'tobacco_type' => $product->tobacco_type,
                'amount' => $amount,
                'unit_weight_grams' => $unitWeight,
                'settlement_packaging_grams_per_unit' => $packagingPerUnit,
                'unit_sticks' => (int) $product->unit_sticks,
                'line_weight_grams' => $lineWeight,
                'line_billable_weight_grams' => $lineBillableWeight,
                'line_sticks' => $lineSticks,
            ];
        }

        if (count($lines) === 0) {
            throw new InvalidRequestException('请至少选择一件商品后再提交订单');
        }

        return [
            'lines' => $lines,
            'settlement_packaging_grams_per_unit' => $packagingPerUnit,
            'total_weight_grams' => $totalWeightGrams,
            'total_cigarette_sticks' => $totalCigaretteSticks,
            'total_cigarette_boxes' => $totalCigaretteBoxes,
            'total_rolling_tobacco_grams' => $totalRollingTobaccoGrams,
        ];
    }

    public function validateLimits(array $summary)
    {
        $maxSticks = $this->maxCigaretteSticks();
        $maxBoxes = $this->maxCigaretteBoxes();
        $maxRollingGrams = $this->maxRollingTobaccoGrams();

        if ((int) data_get($summary, 'total_cigarette_sticks', 0) > $maxSticks) {
            throw new InvalidRequestException(
                '单笔订单香烟、加热烟合计不得超过 '.$maxSticks.' 支（当前 '.(int) $summary['total_cigarette_sticks'].' 支）。'
            );
        }

        if ((int) data_get($summary, 'total_cigarette_boxes', 0) > $maxBoxes) {
            throw new InvalidRequestException(
                '单笔订单香烟合计不得超过 '.$maxBoxes.' 盒/包（当前 '.(int) $summary['total_cigarette_boxes'].' 盒/包）。'
            );
        }

        if ((int) data_get($summary, 'total_rolling_tobacco_grams', 0) > $maxRollingGrams) {
            throw new InvalidRequestException(
                '单笔订单手卷烟丝合计不得超过 '.round($maxRollingGrams / 1000, 2).'kg（当前 '
                .round($summary['total_rolling_tobacco_grams'] / 1000, 2).'kg）。'
            );
        }
    }

    public function assertProductLogisticsConfigured(Product $product)
    {
        $type = (string) $product->tobacco_type;
        $options = self::tobaccoTypeOptions();

        if (!array_key_exists($type, $options)) {
            throw new InvalidRequestException('商品「'.$product->title.'」未设置烟草分类，暂不可购买。');
        }

        $weight = (int) $product->unit_weight_grams;
        if ($weight < 1) {
            throw new InvalidRequestException('商品「'.$product->title.'」未设置单位重量，暂不可购买。');
        }

        if (self::countsTowardStickLimit($type) && (int) $product->unit_sticks < 1) {
            throw new InvalidRequestException('商品「'.$product->title.'」未设置每包/每盒支数，暂不可购买。');
        }
    }

    /**
     * 加购/改数量时预检：合并购物车现有数量与本次变更。
     */
    public function validateCartItems(array $items)
    {
        if (count($items) === 0) {
            return;
        }

        app(ShippingModeService::class)->assertSingleMode($items);
        $summary = $this->analyzeSkuItems($items);
        $this->validateLimits($summary);
    }

    /**
     * @param \Illuminate\Support\Collection|\App\Models\CartItem[] $cartItems
     */
    public function buildItemsPayloadFromCart($cartItems, $skuId, $newAmount)
    {
        $items = [];
        $found = false;

        foreach ($cartItems as $cartItem) {
            $id = (int) $cartItem->product_sku_id;
            $amount = (int) $cartItem->amount;
            if ($id === (int) $skuId) {
                $amount = (int) $newAmount;
                $found = true;
            }
            if ($amount > 0) {
                $items[] = ['sku_id' => $id, 'amount' => $amount];
            }
        }

        if (!$found && (int) $newAmount > 0) {
            $items[] = ['sku_id' => (int) $skuId, 'amount' => (int) $newAmount];
        }

        return $items;
    }

    public function maxUnitsForSku(ProductSku $sku)
    {
        $product = $sku->product;
        if (!$product) {
            return null;
        }

        $weight = (int) $product->unit_weight_grams;
        if ($weight < 1) {
            return null;
        }

        $billableUnitWeight = $weight + $this->settlementPackagingGramsPerUnit();
        $shippingMode = app(ShippingModeService::class)->resolveForProduct($product);
        if (app(ShippingModeService::class)->isTaxIncluded($shippingMode)) {
            return null;
        }

        $maxGrams = app(EmsShippingFeeService::class)->maxBillableGrams();
        $byWeight = (int) floor($maxGrams / $billableUnitWeight);

        $limits = [];
        if ($product->tobacco_type === self::TYPE_CIGARETTE) {
            if ((int) $product->unit_sticks > 0) {
                $limits[] = (int) floor($this->maxCigaretteSticks() / (int) $product->unit_sticks);
            }
            $limits[] = $this->maxCigaretteBoxes();
        } elseif (self::countsTowardStickLimit($product->tobacco_type) && (int) $product->unit_sticks > 0) {
            $limits[] = (int) floor($this->maxCigaretteSticks() / (int) $product->unit_sticks);
        }
        if ($product->tobacco_type === self::TYPE_ROLLING_TOBACCO) {
            $limits[] = (int) floor($this->maxRollingTobaccoGrams() / $weight);
        }

        if (count($limits) === 0) {
            return $byWeight > 0 ? $byWeight : null;
        }

        $cap = min(array_merge($limits, [$byWeight]));

        return $cap > 0 ? $cap : null;
    }
}
