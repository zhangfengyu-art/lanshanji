<?php

namespace App\Http\Requests;

use App\Models\ProductSku;

class UpdateCartRequest extends Request
{
    public function rules()
    {
        return [
            'amount' => ['required', 'integer', 'min:1'],
            'sku_id' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    if (!$value) {
                        return;
                    }

                    $sku = ProductSku::find($value);
                    if (!$sku) {
                        $fail('该商品不存在');
                        return;
                    }

                    if (!$sku->product->on_sale) {
                        $fail('该商品未上架');
                        return;
                    }

                    $amount = (int) $this->input('amount');
                    if ($sku->stock < $amount) {
                        $fail('该商品库存不足');
                        return;
                    }

                    // 检查单笔限购限制
                    $maxAllowed = $sku->getOrderMaxQty();
                    if ($amount > 0 && $maxAllowed > 0 && $amount > $maxAllowed) {
                        $fail('该商品限购，单笔最多购买 ' . $maxAllowed . ' 件');
                        return;
                    }
                },
            ],
        ];
    }

    public function attributes()
    {
        return [
            'amount' => '商品数量',
        ];
    }
}

