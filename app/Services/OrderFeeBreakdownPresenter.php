<?php

namespace App\Services;

use App\Models\Order;

class OrderFeeBreakdownPresenter
{
    public static function forOrder(Order $order): array
    {
        $fee = (array) data_get($order->extra, 'fee_details', []);

        $itemsSubtotal = round($order->items->sum(function ($item) {
            return (float) $item->price * (int) $item->amount;
        }), 2);

        $goodsAfterDiscount = round((float) data_get(
            $fee,
            'base_amount',
            $itemsSubtotal
        ), 2);

        $couponDiscount = 0.0;
        if ($order->couponCode && $itemsSubtotal > $goodsAfterDiscount) {
            $couponDiscount = round($itemsSubtotal - $goodsAfterDiscount, 2);
        }

        $serviceFee = round((float) data_get($fee, 'service_fee', 0), 2);
        $packagingFee = round((float) data_get($fee, 'packaging_fee', 0), 2);
        $emsShippingFee = round((float) data_get($fee, 'ems_shipping_fee', 0), 2);
        $totalJpy = round($order->getAmountJpy(), 2);
        $paymentCny = $order->paid_at ? round($order->getPaymentAmountCny(), 2) : null;
        $exchangeRate = $order->paid_at ? round($order->getExchangeRateJpyPerCny(), 6) : null;

        $shippingMode = data_get($fee, 'shipping_mode', data_get($order->extra, 'shipping_mode', ''));
        $shippingModeLabel = $shippingMode
            ? (ShippingModeService::options()[$shippingMode] ?? $shippingMode)
            : '';

        $emsWeight = data_get($fee, 'ems_weight_grams');
        $emsZone = data_get($fee, 'ems_zone');

        return [
            'items_subtotal' => $itemsSubtotal,
            'goods_after_discount' => $goodsAfterDiscount,
            'coupon_discount' => $couponDiscount,
            'has_coupon' => (bool) $order->couponCode,
            'coupon_code' => optional($order->couponCode)->code,
            'coupon_description' => optional($order->couponCode)->description,
            'service_fee' => $serviceFee,
            'packaging_fee' => $packagingFee,
            'ems_shipping_fee' => $emsShippingFee,
            'shipping_mode_label' => $shippingModeLabel,
            'ems_weight_grams' => $emsWeight,
            'ems_zone' => $emsZone,
            'total_jpy' => $totalJpy,
            'payment_cny' => $paymentCny,
            'exchange_rate' => $exchangeRate,
            'has_fee_details' => !empty($fee),
        ];
    }
}
