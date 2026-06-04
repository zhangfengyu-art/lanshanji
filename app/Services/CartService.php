<?php

namespace App\Services;

use Auth;
use App\Models\CartItem;
use App\Models\ProductSku;
use App\Exceptions\InvalidRequestException;

class CartService
{
    public function get()
    {
        return Auth::user()->cartItems()->with(['productSku.product'])->get();
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
