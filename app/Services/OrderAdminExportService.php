<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;
class OrderAdminExportService
{
    public static function scopeOptions()
    {
        return [
            'all' => '全部已支付订单',
            'today' => '今日支付订单',
            'week' => '近7日支付订单',
            'pending_ship' => '待发货订单',
            'shipped' => '已发货未签收',
            'refund_applied' => '退款申请中',
            's1_pending' => '待处理（S1，未开始处理）',
        ];
    }

    public static function buildQuery($scope)
    {
        $query = Order::query()
            ->with(['user', 'items.product', 'items.productSku'])
            ->whereNotNull('paid_at')
            ->orderBy('paid_at', 'desc');

        $today = Carbon::today();

        switch ($scope) {
            case 'today':
                $query->whereDate('paid_at', $today);
                break;
            case 'week':
                $query->where('paid_at', '>=', $today->copy()->subDays(6)->startOfDay());
                break;
            case 'pending_ship':
                $query->where('ship_status', Order::SHIP_STATUS_PENDING);
                break;
            case 'shipped':
                $query->where('ship_status', Order::SHIP_STATUS_DELIVERED);
                break;
            case 'refund_applied':
                $query->where('refund_status', Order::REFUND_STATUS_APPLIED);
                break;
            case 's1_pending':
                $query->where('ship_status', Order::SHIP_STATUS_PENDING)
                    ->where('refund_status', Order::REFUND_STATUS_PENDING);
                break;
            case 'all':
            default:
                break;
        }

        return $query;
    }

    public static function headers()
    {
        return [
            '订单流水号',
            '买家昵称',
            '买家邮箱',
            '订单金额',
            '支付时间',
            '支付方式',
            '支付渠道单号',
            '发货状态',
            '物流公司',
            '物流单号',
            '退款状态',
            '收货人',
            '联系电话',
            '收货地址',
            '订单备注',
            '商品明细',
            '寄送模式',
            '计费重量(g)',
            '香烟支数',
            '烟丝重量(g)',
            'EMS运费(日元)',
            '是否有实拍图',
            '是否有购物凭据',
        ];
    }

    public static function row(Order $order)
    {
        $address = (array) $order->address;
        $itemsText = $order->items->map(function ($item) {
            $title = optional($item->product)->title;
            $sku = optional($item->productSku)->title;
            return trim($title.' '.$sku).' x'.$item->amount.' @'.$item->price;
        })->implode('；');

        $fee = (array) data_get($order->extra, 'fee_details', []);
        $tobacco = (array) data_get($order->extra, 'tobacco_summary', []);
        $mode = data_get($fee, 'shipping_mode', data_get($order->extra, 'shipping_mode', ''));

        return [
            $order->no,
            optional($order->user)->name,
            optional($order->user)->email,
            $order->total_amount,
            optional($order->paid_at)->format('Y-m-d H:i:s'),
            self::paymentMethodLabel($order->payment_method),
            $order->payment_no,
            self::shipStatusLabel($order),
            data_get($order->ship_data, 'express_company', ''),
            data_get($order->ship_data, 'express_no', ''),
            Order::$refundStatusMap[$order->refund_status] ?? $order->refund_status,
            data_get($address, 'contact_name', ''),
            data_get($address, 'contact_phone', ''),
            trim(data_get($address, 'address', '').' '.data_get($address, 'zip', '')),
            $order->remark,
            $itemsText,
            \App\Services\ShippingModeService::options()[$mode] ?? $mode,
            data_get($fee, 'ems_weight_grams', ''),
            data_get($tobacco, 'total_cigarette_sticks', ''),
            data_get($tobacco, 'total_rolling_tobacco_grams', ''),
            data_get($fee, 'ems_shipping_fee', ''),
            trim((string) $order->fulfillment_photo) !== '' ? '是' : '否',
            $order->hasShoppingReceipt() ? '是' : '否',
        ];
    }

    protected static function paymentMethodLabel($method)
    {
        $map = [
            'wechat' => '微信支付',
            'alipay' => '支付宝',
        ];

        return $map[$method] ?? (string) $method;
    }

    protected static function shipStatusLabel(Order $order)
    {
        if (is_site_mode_b() && $order->refund_status === Order::REFUND_STATUS_PENDING) {
            return $order->display_status;
        }

        return Order::$shipStatusMap[$order->ship_status] ?? $order->ship_status;
    }

    public static function filename($scope)
    {
        $label = self::scopeOptions()[$scope] ?? '订单';
        $safe = preg_replace('/[^\x{4e00}-\x{9fa5}a-zA-Z0-9_-]+/u', '', $label);
        if ($safe === '') {
            $safe = 'orders';
        }

        return $safe.'_'.date('Ymd_His').'.csv';
    }
}
