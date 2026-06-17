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

            $fulfillment->startProcessing($order);

            return 'ok';
        }, '已开始处理');
    }

    public function batchLockOrders(array $orderIds, OrderFulfillmentService $fulfillment)
    {
        return $this->runBatch($orderIds, function (Order $order) use ($fulfillment) {
            $stage = $fulfillment->resolveStage($order);
            if (in_array($stage, [OrderFulfillmentService::STAGE_S3, OrderFulfillmentService::STAGE_S4], true)) {
                return 'skip';
            }

            $fulfillment->lockOrder($order);

            return 'ok';
        }, '已锁定（S3）');
    }

    public function batchUnlockOrders(array $orderIds, OrderFulfillmentService $fulfillment)
    {
        return $this->runBatch($orderIds, function (Order $order) use ($fulfillment) {
            if ($order->hasFulfillmentPhoto()) {
                return 'skip';
            }

            if (trim((string) data_get($order->extra, 'locked_at', '')) === '') {
                return 'skip';
            }

            $fulfillment->unlockOrder($order);

            return 'ok';
        }, '已解除锁定');
    }

    public function batchMarkLogisticsWarehouse(array $orderIds, OrderFulfillmentService $fulfillment)
    {
        return $this->runBatch($orderIds, function (Order $order) use ($fulfillment) {
            $stage = $fulfillment->resolveStage($order);
            if (!in_array($stage, [OrderFulfillmentService::STAGE_S2, OrderFulfillmentService::STAGE_S3], true)) {
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
