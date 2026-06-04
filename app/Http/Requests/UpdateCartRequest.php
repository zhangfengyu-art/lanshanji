<?php

namespace App\Http\Requests;

use App\Models\ProductSku;
use App\Services\CartService;
use App\Services\OrderTobaccoLimitService;
use Auth;

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

                    $sku = ProductSku::with('product.category')->find($value);
                    if (!$sku) {
                        $fail('该商品不存在');
                        return;
                    }

                    if (!$sku->product->on_sale) {
                        $fail('该商品未上架');
                        return;
                    }

                    if ($sku->isDepleted()) {
                        $fail('该商品已售罄');
                        return;
                    }

                    $amount = (int) $this->input('amount');
                    $maxAllowed = $sku->getOrderMaxQty();
                    if ($amount > 0 && $maxAllowed > 0 && $amount > $maxAllowed) {
                        $fail('该商品限购，单笔最多购买 '.$maxAllowed.' 件');
                        return;
                    }

                    $user = Auth::user();
                    if ($user && is_site_mode_a()) {
                        $cartItems = app(CartService::class)->get();
                        $payload = app(OrderTobaccoLimitService::class)
                            ->buildItemsPayloadFromCart($cartItems, $value, $amount);

                        try {
                            app(OrderTobaccoLimitService::class)->validateCartItems($payload);
                        } catch (\App\Exceptions\InvalidRequestException $e) {
                            $fail($e->getMessage());
                            return;
                        }

                        $emsMax = app(OrderTobaccoLimitService::class)->maxUnitsForSku($sku);
                        if ($emsMax !== null && $amount > $emsMax) {
                            $fail('按 EMS 计费重量与烟草限额，该商品单笔最多购买 '.$emsMax.' 件');
                        }
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
