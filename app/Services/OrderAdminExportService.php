<?php

namespace App\Services;

use App\Models\Order;
use App\Models\UserAddress;
use Carbon\Carbon;
use Illuminate\Support\Str;

class OrderAdminExportService
{
    /** @var int[] */
    const TEXT_COLUMN_INDEXES = [0, 4, 6, 21];

    /** @var int[] */
    const IMAGE_COLUMN_INDEXES = [11];

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
            '支付时间',
            '买家昵称',
            '收货人',
            '联系电话',
            '收货地址',
            '身份证号',
            '商品名称',
            '规格',
            '数量',
            '单价(日元)',
            '商品图片',
            '寄送模式',
            'EMS计费重量(g)',
            '香烟支数',
            '烟丝重量(g)',
            '订单备注',
            '发货状态',
            '退款状态',
            '支付方式',
            '订单金额(日元)',
            '一键粘贴地址',
        ];
    }

    /**
     * 每个订单商品一行，便于代购对照图片与地址。
     *
     * @return array[]
     */
    public static function rowsForOrder(Order $order)
    {
        $address = (array) $order->address;
        $fullAddress = self::formatFullAddress($address);
        $idCard = self::resolveOrderIdCard($order);
        $pasteLine = self::buildPasteAddressLine($address, $fullAddress, $idCard);

        $fee = (array) data_get($order->extra, 'fee_details', []);
        $tobacco = (array) data_get($order->extra, 'tobacco_summary', []);
        $mode = data_get($fee, 'shipping_mode', data_get($order->extra, 'shipping_mode', ''));
        $head = self::sharedOrderCells($order, $fullAddress, $idCard);
        $tail = self::tailOrderCells($order, $fee, $tobacco, $mode, $pasteLine);

        $items = $order->items;
        if ($items->isEmpty()) {
            return [array_merge($head, ['—', '—', '', '', ''], $tail)];
        }

        $rows = [];
        foreach ($items as $item) {
            $product = $item->product;
            $title = $product ? $product->title : ($item->product_id ? '商品#'.$item->product_id : '—');
            $skuTitle = optional($item->productSku)->title;
            $imageUrl = $product ? self::absoluteImageUrl($product->image_url) : '';

            $rows[] = array_merge($head, [
                $title,
                $skuTitle ?: '—',
                (int) $item->amount,
                $item->price,
                $imageUrl,
            ], $tail);
        }

        return $rows;
    }

    protected static function sharedOrderCells(Order $order, $fullAddress, $idCard)
    {
        return [
            $order->no,
            optional($order->paid_at)->format('Y-m-d H:i:s'),
            optional($order->user)->name ?: '—',
            data_get($order->address, 'contact_name', ''),
            data_get($order->address, 'contact_phone', ''),
            $fullAddress,
            $idCard,
        ];
    }

    protected static function tailOrderCells(Order $order, $fee, $tobacco, $mode, $pasteLine)
    {
        return [
            ShippingModeService::options()[$mode] ?? $mode,
            data_get($fee, 'ems_weight_grams', ''),
            data_get($tobacco, 'total_cigarette_sticks', ''),
            data_get($tobacco, 'total_rolling_tobacco_grams', ''),
            $order->remark ?: '—',
            self::shipStatusLabel($order),
            Order::$refundStatusMap[$order->refund_status] ?? $order->refund_status,
            self::paymentMethodLabel($order->payment_method),
            $order->total_amount,
            $pasteLine,
        ];
    }

    public static function resolveOrderIdCard(Order $order)
    {
        $fromOrder = trim((string) data_get($order->address, 'id_card', ''));
        if ($fromOrder !== '') {
            return $fromOrder;
        }

        if (!$order->user_id) {
            return '';
        }

        $phone = trim((string) data_get($order->address, 'contact_phone', ''));
        $name = trim((string) data_get($order->address, 'contact_name', ''));

        $query = UserAddress::query()->where('user_id', $order->user_id);

        if ($phone !== '') {
            $match = (clone $query)
                ->where('contact_phone', $phone)
                ->orderByDesc('is_default')
                ->orderByDesc('last_used_at')
                ->first();
            if ($match && trim((string) $match->id_card) !== '') {
                return trim((string) $match->id_card);
            }
        }

        if ($name !== '') {
            $match = (clone $query)
                ->where('contact_name', $name)
                ->orderByDesc('is_default')
                ->orderByDesc('last_used_at')
                ->first();
            if ($match && trim((string) $match->id_card) !== '') {
                return trim((string) $match->id_card);
            }
        }

        $default = (clone $query)
            ->where('is_default', 1)
            ->orderByDesc('last_used_at')
            ->first();

        return $default ? trim((string) $default->id_card) : '';
    }

    public static function formatFullAddress(array $address)
    {
        $parts = array_filter([
            trim((string) data_get($address, 'province', '')),
            trim((string) data_get($address, 'city', '')),
            trim((string) data_get($address, 'district', '')),
            trim((string) data_get($address, 'address', '')),
        ], function ($part) {
            return $part !== '';
        });

        $full = implode('', $parts);
        if ($full === '') {
            $full = trim((string) data_get($address, 'full_address', ''));
        }

        $zip = trim((string) data_get($address, 'zip', ''));
        if ($zip !== '' && $full !== '') {
            $full .= '（邮编 '.$zip.'）';
        }

        return $full;
    }

    public static function buildPasteAddressLine(array $address, $fullAddress, $idCard)
    {
        $parts = [
            trim((string) data_get($address, 'contact_name', '')),
            trim((string) data_get($address, 'contact_phone', '')),
            trim((string) $fullAddress),
            trim((string) $idCard),
        ];

        $parts = array_values(array_filter($parts, function ($part) {
            return $part !== '';
        }));

        return implode('，', $parts);
    }

    public static function absoluteImageUrl($url)
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        if (Str::startsWith($url, '//')) {
            return 'https:'.$url;
        }

        return url($url);
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

        return $safe.'_'.date('Ymd_His').'.xls';
    }
}
