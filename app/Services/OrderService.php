<?php

namespace App\Services;

use App\Exceptions\CouponCodeUnavailableException;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\Order;
use App\Models\ProductSku;
use App\Models\CouponCode;
use App\Exceptions\InvalidRequestException;
use App\Jobs\CloseOrder;
use Carbon\Carbon;
use App\Services\ExchangeRateService;

class OrderService
{
    /** @var OrderCheckoutQuoteService */
    protected $checkoutQuote;

    public function __construct(OrderCheckoutQuoteService $checkoutQuote)
    {
        $this->checkoutQuote = $checkoutQuote;
    }

    public function store(User $user, UserAddress $address, $remark, $items, CouponCode $coupon = null)
    {
        if (empty($items) || count($items) === 0) {
            throw new InvalidRequestException('请至少选择一件商品后再提交订单');
        }

        if ($coupon) {
            $coupon->checkAvailable($user);
        }
        // 开启一个数据库事务
        $order = \DB::transaction(function () use ($user, $address, $remark, $items, $coupon) {
            // 更新此地址的最后使用时间
            $address->update(['last_used_at' => Carbon::now()]);
            // 创建一个订单
            $order   = new Order([
                'address'      => [
                    'province'      => $address->province,
                    'city'          => $address->city,
                    'district'      => $address->district,
                    'address'       => $address->address,
                    'full_address'  => $address->full_address,
                    'zip'           => $address->zip,
                    'contact_name'  => $address->contact_name,
                    'contact_phone' => $address->contact_phone,
                ],
                'remark'       => $remark,
                'total_amount' => 0,
            ]);
            // 订单关联到当前用户
            $order->user()->associate($user);
            // 写入数据库
            $order->save();

            $totalAmount = 0;
            // 遍历用户提交的 SKU
            foreach ($items as $data) {
                $sku  = ProductSku::query()->lockForUpdate()->find($data['sku_id']);
                if (!$sku) {
                    throw new InvalidRequestException('该商品不存在');
                }
                if (!$sku->product->on_sale) {
                    throw new InvalidRequestException('该商品未上架');
                }

                $amount = (int) $data['amount'];
                if ($amount > $sku->getOrderMaxQty()) {
                    throw new InvalidRequestException('物资调拨状态已变更，请重新确认配额。');
                }
                // 创建一个 OrderItem 并直接与当前订单关联
                $item = $order->items()->make([
                    'amount' => $amount,
                    'price'  => $sku->price,
                ]);
                $item->product()->associate($sku->product_id);
                $item->productSku()->associate($sku);
                $item->save();
                $totalAmount += $sku->price * $amount;
                $sku->decreaseStock($amount);
            }
            if ($coupon) {
                // 总金额已经计算出来了，检查是否符合优惠券规则
                $coupon->checkAvailable($user, $totalAmount);
                // 把订单金额修改为优惠后的金额
                $totalAmount = $coupon->getAdjustedPrice($totalAmount);
                // 将订单与优惠券关联
                $order->couponCode()->associate($coupon);
                // 增加优惠券的用量，需判断返回值
                if ($coupon->changeUsed() <= 0) {
                    throw new CouponCodeUnavailableException('该优惠券已被兑完');
                }
            }


            $isNativeProcurement = !empty($items[0]['is_native_procurement']);
            $serviceFee = round($totalAmount * 0.13, 2);
            $packagingFee = 300.00;
            $emsShippingFee = 1750.00;
            $tobaccoSummary = null;
            $shippingMode = null;

            if (is_site_mode_a() && !$isNativeProcurement) {
                $quote = $this->checkoutQuote->quote($items);
                $serviceFee = $quote['service_fee'];
                $packagingFee = $quote['packaging_fee'];
                $emsShippingFee = $quote['ems_shipping_fee'];
                $tobaccoSummary = $quote['tobacco_summary'];
                $shippingMode = $quote['shipping_mode'];
            } elseif ($isNativeProcurement) {
                $emsShippingFee = 0;
                $packagingFee = 0;
                $serviceFee = 0;
            }

            $finalPayAmount = round($totalAmount + $serviceFee + $packagingFee + $emsShippingFee, 2);

            $extra = $order->extra ?: [];
            $extra['currency'] = 'JPY';
            $extra['amount_jpy'] = $finalPayAmount;
            if ($shippingMode) {
                $extra['shipping_mode'] = $shippingMode;
            }
            if ($tobaccoSummary) {
                $extra['tobacco_summary'] = [
                    'total_weight_grams' => $tobaccoSummary['total_weight_grams'],
                    'total_cigarette_sticks' => $tobaccoSummary['total_cigarette_sticks'],
                    'total_cigarette_boxes' => $tobaccoSummary['total_cigarette_boxes'],
                    'total_rolling_tobacco_grams' => $tobaccoSummary['total_rolling_tobacco_grams'],
                ];
            }
            $extra['fee_details'] = [
                'base_amount' => round($totalAmount, 2),
                'service_fee' => $serviceFee,
                'packaging_fee' => $packagingFee,
                'ems_shipping_fee' => $emsShippingFee,
                'ems_weight_grams' => $tobaccoSummary ? $tobaccoSummary['total_weight_grams'] : null,
                'ems_zone' => ($tobaccoSummary && $emsShippingFee > 0) ? config('ems_shipping.zone_label') : null,
                'shipping_mode' => $shippingMode,
            ];

            $order->update([
                'total_amount' => $finalPayAmount,
                'extra' => $extra,
            ]);

            app(ExchangeRateService::class)->snapshotQuoteOnOrder($order->fresh());
            
            // 将下单的商品从购物车中移除
            $skuIds = collect($items)->pluck('sku_id')->all();
            app(CartService::class)->remove($skuIds);

            return $order;
        });

        return $order;
    }
}
