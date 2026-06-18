<?php

namespace App\Services;

use App\Exceptions\InvalidRequestException;
use App\Models\Order;

class OrderBatchService
{
    public function batchStartProcessing(array $orderIds, OrderFulfillmentService $fulfillment)
    {
        return $this->runBatch($orderIds, function (Order $order) use ($fulfillment) {
            if ($fulfillment->resolveStage($order) !== OrderFulfillmentService::STAGE_S1) {
                return 'skip';
            }

            if (trim((string) data_get($order->extra, 'processing_started_at', '')) !== '') {
                return 'skip';
            }

            $fulfillment->startProcessing($order);

            return 'ok';
        }, '已标记开始处理');
    }

    public function batchEnterStockPrep(array $orderIds, OrderFulfillmentService $fulfillment)
    {
        return $this->runBatch($orderIds, function (Order $order) use ($fulfillment) {
            if ($fulfillment->resolveStage($order) !== OrderFulfillmentService::STAGE_S1) {
                return 'skip';
            }

            $fulfillment->enterStockPrep($order);

            return 'ok';
        }, '已进入备货/打包');
    }

    /** @deprecated */
    public function batchLockOrders(array $orderIds, OrderFulfillmentService $fulfillment)
    {
        return $this->batchEnterStockPrep($orderIds, $fulfillment);
    }

    public function batchRevertFromStockPrep(array $orderIds, OrderFulfillmentService $fulfillment)
    {
        return $this->runBatch($orderIds, function (Order $order) use ($fulfillment) {
            if ($fulfillment->resolveStage($order) !== OrderFulfillmentService::STAGE_S3) {
                return 'skip';
            }

            $fulfillment->revertFromStockPrep($order);

            return 'ok';
        }, '已退回上一阶段');
    }

    /** @deprecated */
    public function batchUnlockOrders(array $orderIds, OrderFulfillmentService $fulfillment)
    {
        return $this->batchRevertFromStockPrep($orderIds, $fulfillment);
    }

    public function batchMarkLogisticsWarehouse(array $orderIds, OrderFulfillmentService $fulfillment)
    {
        return $this->runBatch($orderIds, function (Order $order) use ($fulfillment) {
            if ($fulfillment->resolveStage($order) !== OrderFulfillmentService::STAGE_S3) {
                return 'skip';
            }

            if ($fulfillment->packageAtLogisticsWarehouse($order)) {
                return 'skip';
            }

            $fulfillment->markPackageAtLogisticsWarehouse($order);

            return 'ok';
        }, '已标记送往物流仓库');
    }

    protected function runBatch(array $orderIds, callable $action, $actionLabel)
    {
        $orderIds = array_values(array_filter(array_map('intval', $orderIds)));
        if (count($orderIds) === 0) {
            throw new InvalidRequestException('请先勾选订单');
        }

        $success = 0;
        $skipped = 0;
        $errors = [];

        foreach (Order::query()->whereIn('id', $orderIds)->get() as $order) {
            try {
                $result = $action($order);
                if ($result === 'skip') {
                    $skipped++;
                    continue;
                }
                $success++;
            } catch (InvalidRequestException $e) {
                $errors[] = $order->no.': '.$e->getMessage();
            }
        }

        $message = $actionLabel.' '.$success.' 单';
        if ($skipped > 0) {
            $message .= '，跳过 '.$skipped.' 单';
        }
        if (count($errors) > 0) {
            $message .= '；失败 '.count($errors).' 单';
        }

        return [
            'success' => $success,
            'skipped' => $skipped,
            'errors' => $errors,
            'message' => $message,
        ];
    }
}
