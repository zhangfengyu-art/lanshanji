<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;

/**
 * 按订单范围汇总香烟/加热烟采购数量，导出 ZIP+HTML 备货表。
 */
class OrderStockPrepExportService
{
    /** @var int[] */
    const TEXT_COLUMN_INDEXES = [1];

    /** @var int[] */
    const IMAGE_COLUMN_INDEXES = [7];

    /** @var int[] */
    const CHECKBOX_COLUMN_INDEXES = [8];

    public static function scopeOptions()
    {
        return [
            'pending_fulfillment' => '待发货采购汇总（S1+S2+S3）',
            's1' => 'S1 待处理',
            's2' => 'S2 处理中',
            's3' => 'S3 备货/打包',
            'paid_today' => '今日新支付（待发货）',
            'paid_week' => '近7日新支付（待发货）',
            'refund_applied' => '退款申请中（慎选）',
        ];
    }

    public static function headers()
    {
        return [
            '序号',
            '商品名称',
            '类型',
            '每包支数',
            '采购数量(包)',
            '合计支数',
            '涉及订单数',
            '商品图片',
            '采购确认',
        ];
    }

    public static function filename($scope)
    {
        $label = self::scopeOptions()[$scope] ?? '备货';
        $safe = preg_replace('/[^\x{4e00}-\x{9fa5}a-zA-Z0-9_-]+/u', '', $label.'_备货表');
        if ($safe === '') {
            $safe = 'stock_prep';
        }

        return $safe.'_'.date('Ymd_His').'.zip';
    }

    public static function buildQuery($scope)
    {
        $query = Order::query()
            ->with(['items.product'])
            ->whereNotNull('paid_at')
            ->where('closed', false);

        $today = Carbon::today();

        switch ($scope) {
            case 'pending_fulfillment':
            case 's1':
            case 's2':
            case 's3':
                $query->where('ship_status', Order::SHIP_STATUS_PENDING);
                break;
            case 'paid_today':
                $query->whereDate('paid_at', $today)
                    ->where('ship_status', Order::SHIP_STATUS_PENDING);
                break;
            case 'paid_week':
                $query->where('paid_at', '>=', $today->copy()->subDays(6)->startOfDay())
                    ->where('ship_status', Order::SHIP_STATUS_PENDING);
                break;
            case 'refund_applied':
                $query->where('refund_status', Order::REFUND_STATUS_APPLIED);
                break;
            default:
                $query->where('ship_status', Order::SHIP_STATUS_PENDING);
                break;
        }

        return $query;
    }

    public static function exportRowsWithProducer($scope, OrderFulfillmentService $fulfillment, callable $emitRow)
    {
        $aggregates = [];

        self::buildQuery($scope)->chunk(50, function ($orders) use ($scope, $fulfillment, &$aggregates) {
            foreach ($orders as $order) {
                if (!self::orderIncludedInStockPrep($order, $scope, $fulfillment)) {
                    continue;
                }

                $productsInOrder = [];
                foreach ($order->items as $item) {
                    $product = $item->product;
                    if (!$product || !OrderTobaccoLimitService::countsTowardStickLimit((string) $product->tobacco_type)) {
                        continue;
                    }

                    $qty = (int) $item->amount;
                    if ($qty < 1) {
                        continue;
                    }

                    $productId = (int) $product->id;
                    if (!isset($aggregates[$productId])) {
                        $aggregates[$productId] = [
                            'product' => $product,
                            'title' => (string) $product->title,
                            'tobacco_type_label' => (string) $product->tobacco_type_label,
                            'unit_sticks' => (int) $product->unit_sticks,
                            'qty' => 0,
                            'order_ids' => [],
                        ];
                    }

                    $aggregates[$productId]['qty'] += $qty;
                    $productsInOrder[$productId] = true;
                }

                foreach (array_keys($productsInOrder) as $productId) {
                    $aggregates[$productId]['order_ids'][(int) $order->id] = true;
                }
            }
        });

        uasort($aggregates, function ($a, $b) {
            $typeCmp = strcmp($a['tobacco_type_label'], $b['tobacco_type_label']);
            if ($typeCmp !== 0) {
                return $typeCmp;
            }

            if ($a['qty'] !== $b['qty']) {
                return $b['qty'] - $a['qty'];
            }

            return strcmp($a['title'], $b['title']);
        });

        $index = 0;
        $totalPacks = 0;
        $totalSticks = 0;

        foreach ($aggregates as $row) {
            $index++;
            $packs = (int) $row['qty'];
            $unitSticks = (int) $row['unit_sticks'];
            $sticks = $unitSticks > 0 ? $packs * $unitSticks : 0;
            $totalPacks += $packs;
            $totalSticks += $sticks;

            $emitRow([
                $index,
                $row['title'],
                $row['tobacco_type_label'],
                $unitSticks > 0 ? $unitSticks : '—',
                $packs,
                $sticks > 0 ? $sticks : '—',
                count($row['order_ids']),
                OrderAdminExportService::imageLocalPathForProduct($row['product']),
                '',
            ]);
        }

        if ($index === 0) {
            $emitRow(['—', '（当前范围无香烟/加热烟采购项）', '—', '—', 0, '—', 0, '', '']);

            return;
        }

        $emitRow([
            '合计',
            $index.' 种商品',
            '—',
            '—',
            $totalPacks,
            $totalSticks > 0 ? $totalSticks : '—',
            '—',
            '',
            '',
        ]);
    }

    protected static function orderIncludedInStockPrep(Order $order, $scope, OrderFulfillmentService $fulfillment)
    {
        if ($order->closed) {
            return false;
        }

        if ($order->refund_status === Order::REFUND_STATUS_SUCCESS) {
            return false;
        }

        $stage = $fulfillment->resolveStage($order);

        switch ($scope) {
            case 'pending_fulfillment':
                return in_array($stage, [
                    OrderFulfillmentService::STAGE_S1,
                    OrderFulfillmentService::STAGE_S2,
                    OrderFulfillmentService::STAGE_S3,
                ], true);
            case 's1':
                return $stage === OrderFulfillmentService::STAGE_S1;
            case 's2':
                return $stage === OrderFulfillmentService::STAGE_S2;
            case 's3':
                return $stage === OrderFulfillmentService::STAGE_S3;
            case 'paid_today':
            case 'paid_week':
                return in_array($stage, [
                    OrderFulfillmentService::STAGE_S1,
                    OrderFulfillmentService::STAGE_S2,
                    OrderFulfillmentService::STAGE_S3,
                ], true);
            case 'refund_applied':
                return true;
            default:
                return in_array($stage, [
                    OrderFulfillmentService::STAGE_S1,
                    OrderFulfillmentService::STAGE_S2,
                    OrderFulfillmentService::STAGE_S3,
                ], true);
        }
    }
}
