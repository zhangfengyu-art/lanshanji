<?php

namespace App\Services;

use App\Models\Order;

/**
 * 按订单范围汇总香烟/加热烟采购数量，导出 ZIP+HTML 备货表。
 */
class OrderStockPrepExportService
{
    /** @var int[] */
    const TEXT_COLUMN_INDEXES = [1];

    /** @var int[] */
    const IMAGE_COLUMN_INDEXES = [7];

    public static function scopeOptions()
    {
        return OrderAdminExportService::scopeOptions();
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

    public static function exportRowsWithProducer($scope, OrderFulfillmentService $fulfillment, callable $emitRow)
    {
        $aggregates = [];

        OrderAdminExportService::buildQuery($scope)->chunk(50, function ($orders) use ($scope, $fulfillment, &$aggregates) {
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
            ]);
        }

        if ($index === 0) {
            $emitRow(['—', '（当前范围无香烟/加热烟采购项）', '—', '—', 0, '—', 0, '']);

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

        if ($scope === 's1_pending' && $fulfillment->resolveStage($order) !== OrderFulfillmentService::STAGE_S1) {
            return false;
        }

        return true;
    }
}
