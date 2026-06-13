<?php

namespace App\Http\Requests;

use App\Models\ProductSku;
use App\Services\CartService;
use App\Services\OrderTobaccoLimitService;
use Auth;

class AddCartRequest extends Request
{
    public function rules()
    {
        return [
            'sku_id' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (!$sku = ProductSku::with('product.category')->find($value)) {
                        $fail('该商品不存在');
                        return;
                    }
                    if (!$sku->product) {
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
                    $tobaccoLimits = app(OrderTobaccoLimitService::class);
                    if ($user && is_site_mode_a()) {
                        $cartItems = app(CartService::class)->get();
                        $existing = $user->cartItems()->where('product_sku_id', $value)->first();
                        $totalAmount = $amount + (int) optional($existing)->amount;
                        $payload = $tobaccoLimits->buildItemsPayloadFromCart($cartItems, $value, $totalAmount);

                        try {
                            $tobaccoLimits->validateCartItems($payload);
                        } catch (\App\Exceptions\InvalidRequestException $e) {
                            $fail($e->getMessage());
                            return;
                        }

                        $emsMax = $tobaccoLimits->maxUnitsForSku($sku);
                        if ($emsMax !== null && $totalAmount > $emsMax) {
                            $fail('按 EMS 计费重量与烟草限额，该商品单笔最多购买 '.$emsMax.' 件');
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
            'amount' => '商品数量',
        ];
    }

    public function messages()
    {
        return [
            'sku_id.required' => '请选择商品',
        ];
    }
}
