<?php

namespace App\Services;

use Auth;
use App\Models\CartItem;
use App\Models\ProductSku;
use App\Exceptions\InvalidRequestException;

class CartService
{
    const LOGISTICS_MAX_STICKS = 400;
    const LOGISTICS_MAX_WEIGHT = 500;

    public function get()
    {
        return Auth::user()->cartItems()->with(['productSku.product'])->get();
    }

    public function validateLogisticsLimits($cartItems = null, $throwOnExceeded = false)
    {
        if ($cartItems === null) {
            $cartItems = $this->get();
        }

        $totalSticks = 0;
        $totalWeight = 0;

        foreach ($cartItems as $item) {
            $amount = (int) data_get($item, 'amount', 0);
            if ($amount <= 0) {
                continue;
            }

            $sku = data_get($item, 'productSku');
            if (!$sku) {
                continue;
            }

            $itemType = (string) data_get($sku, 'item_type', '');
            if ($itemType === 'cigarette') {
                $unitSticks = max(0, (int) data_get($sku, 'unit_sticks', 0));
                $totalSticks += $amount * $unitSticks;
                continue;
            }
            if ($itemType === 'tobacco_silk') {
                $unitWeight = max(0, (int) data_get($sku, 'unit_weight', 0));
                $totalWeight += $amount * $unitWeight;
            }
        }

        $sticksExceeded = $totalSticks > self::LOGISTICS_MAX_STICKS;
        $weightExceeded = $totalWeight > self::LOGISTICS_MAX_WEIGHT;
        $exceeded = $sticksExceeded || $weightExceeded;
        $sticksOver = max(0, $totalSticks - self::LOGISTICS_MAX_STICKS);
        $weightOver = max(0, $totalWeight - self::LOGISTICS_MAX_WEIGHT);

        $reason = null;
        if ($sticksExceeded && $weightExceeded) {
            $reason = sprintf(
                '当前包裹香烟超出 %d 支、烟丝超重 %dg，请分拆下单。',
                $sticksOver,
                $weightOver
            );
        } elseif ($sticksExceeded) {
            $reason = sprintf(
                '当前包裹香烟超出 %d 支，请分拆下单。',
                $sticksOver
            );
        } elseif ($weightExceeded) {
            $reason = sprintf(
                '当前包裹烟丝超重 %dg，请分拆下单。',
                $weightOver
            );
        }

        if ($exceeded && $throwOnExceeded) {
            throw new InvalidRequestException($reason ?: '根据邮寄规则，香烟总支数需在 400 支以内且烟丝总克重需在 500g 以内（可同时寄 400 支香烟 + 500g 烟丝），请分拆下单。');
        }

        return [
            'total_sticks' => $totalSticks,
            'sticks_limit' => self::LOGISTICS_MAX_STICKS,
            'total_weight' => $totalWeight,
            'weight_limit' => self::LOGISTICS_MAX_WEIGHT,
            'exceeded' => $exceeded,
            'reason' => $reason,
        ];
    }

    public function add($skuId, $amount)
    {
        $user = Auth::user();
        $sku = ProductSku::find($skuId);
        if (!$sku) {
            throw new InvalidRequestException('该商品不存在');
        }

        $maxAllowed = $sku->getOrderMaxQty();
        
        // 从数据库中查询该商品是否已经在购物车中
        if ($item = $user->cartItems()->where('product_sku_id', $skuId)->first()) {
            // 检查最终数量是否超过限购
            $newAmount = $item->amount + $amount;
            if ($maxAllowed > 0 && $newAmount > $maxAllowed) {
                throw new InvalidRequestException('该商品限购，购物车已有 ' . $item->amount . ' 件，无法再添加 ' . $amount . ' 件');
            }
            // 如果存在则直接叠加商品数量
            $item->update([
                'amount' => $newAmount,
            ]);
        } else {
            // 检查新增数量是否超过限购
            if ($maxAllowed > 0 && $amount > $maxAllowed) {
                throw new InvalidRequestException('该商品限购，单笔最多购买 ' . $maxAllowed . ' 件');
            }
            // 否则创建一个新的购物车记录
            $item = new CartItem(['amount' => $amount]);
            $item->user()->associate($user);
            $item->productSku()->associate($skuId);
            $item->save();
        }

        return $item;
    }

    public function remove($skuIds)
    {
        if (!is_array($skuIds)) {
            $skuIds = [$skuIds];
        }
        Auth::user()->cartItems()->whereIn('product_sku_id', $skuIds)->delete();
    }

    public function update($skuId, $amount)
    {
        $user = Auth::user();
        $item = $user->cartItems()->where('product_sku_id', $skuId)->first();

        if (!$item) {
            return null;
        }

        $sku = $item->productSku;
        $maxAllowed = $sku->getOrderMaxQty();
        
        // 检查更新后的数量是否超过限购
        if ($maxAllowed > 0 && $amount > $maxAllowed) {
            throw new InvalidRequestException('该商品限购，单笔最多购买 ' . $maxAllowed . ' 件');
        }

        $item->update([
            'amount' => $amount,
        ]);

        return $item;
    }
}
