<?php

namespace App\Services;

use App\Exceptions\InvalidRequestException;
use App\Models\Order;

class OrderBatchService
{
    public function batchStartProcessing(array $orderIds, OrderFulfillmentService $fulfillment)
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
                if ($fulfillment->resolveStage($order) !== OrderFulfillmentService::STAGE_S1) {
                    $skipped++;
                    continue;
                }
                $fulfillment->startProcessing($order);
                $success++;
            } catch (InvalidRequestException $e) {
                $errors[] = $order->no.': '.$e->getMessage();
            }
        }

        $message = '已开始处理 '.$success.' 单';
        if ($skipped > 0) {
            $message .= '，跳过非待处理 '.$skipped.' 单';
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
