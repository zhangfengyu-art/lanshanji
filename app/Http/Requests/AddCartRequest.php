<?php

namespace App\Http\Requests;

use App\Models\ProductSku;
use Auth;
use Illuminate\Support\Facades\Schema;

class AddCartRequest extends Request
{
    public function rules()
    {
        return [
            'sku_id' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (!$sku = ProductSku::find($value)) {
                        $fail('该商品不存在');
                        return;
                    }
                    $product = $sku->product;
                    if (!$product) {
                        $fail('该商品不存在');
                        return;
                    }
                    if (Schema::hasColumn('products', 'is_from_native_procurement')) {
                        $isNativeProcurement = (bool) $product->is_from_native_procurement;
                        if (is_site_mode_b() && !$isNativeProcurement) {
                            $fail('该商品不存在');
                            return;
                        }
                        if (!is_site_mode_b() && $isNativeProcurement) {
                            $fail('该商品不存在');
                            return;
                        }
                    }
                    if (!$product->on_sale) {
                        $fail('该商品未上架');
                        return;
                    }
                    if ($sku->stock === 0) {
                        $fail('该商品已售完');
                        return;
                    }
                    $amount = (int) $this->input('amount');
                    if ($amount > 0 && $sku->stock < $amount) {
                        $fail('该商品库存不足');
                        return;
                    }
                    // 检查单笔限购限制
                    $maxAllowed = $sku->getOrderMaxQty();
                    if ($amount > 0 && $maxAllowed > 0 && $amount > $maxAllowed) {
                        $fail('该商品限购，单笔最多购买 ' . $maxAllowed . ' 件');
                        return;
                    }
                    
                    // 检查购物车中已有数量 + 新增数量是否超过限购
                    $user = Auth::user();
                    if ($user) {
                        $existingItem = $user->cartItems()->where('product_sku_id', $value)->first();
                        if ($existingItem && $maxAllowed > 0) {
                            $totalAmount = $existingItem->amount + $amount;
                            if ($totalAmount > $maxAllowed) {
                                $fail('该商品限购，购物车已有 ' . $existingItem->amount . ' 件，最多还能再加 ' . ($maxAllowed - $existingItem->amount) . ' 件');
                                return;
                            }
                        }
                    }
                },
            ],
            'amount' => ['required', 'integer', 'min:1'],
        ];
    }
    
    public function attributes()
    {
        return [
            'amount' => '商品数量'
        ];
    }

    public function messages()
    {
        return [
            'sku_id.required' => '请选择商品'
        ];
    }
}

