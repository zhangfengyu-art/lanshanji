<?php

namespace App\Services;

use App\Exceptions\InvalidRequestException;
use App\Models\Order;
use App\Notifications\OrderShippedNotification;

class AdminOrderShipmentService
{
    public function applyShipment(Order $order, $expressCompany, $expressNo, $notifyUser = true)
    {
        if (!$order->paid_at) {
            throw new InvalidRequestException('该订单未付款');
        }

        if ($order->ship_status !== Order::SHIP_STATUS_PENDING) {
            throw new InvalidRequestException('该订单已发货');
        }

        if ($order->refund_status === Order::REFUND_STATUS_SUCCESS) {
            throw new InvalidRequestException('该订单已退款，无法发货');
        }

        if ($order->refund_status !== Order::REFUND_STATUS_PENDING) {
            throw new InvalidRequestException('该订单正在退款流程中，无法发货');
        }

        $expressCompany = trim((string) $expressCompany);
        $expressNo = trim((string) $expressNo);

        if ($expressCompany === '' || $expressNo === '') {
            throw new InvalidRequestException('物流公司与单号不能为空');
        }

        if (is_site_mode_a()) {
            $allowed = site_express_carrier_options();
            if (!in_array($expressCompany, $allowed, true)) {
                $expressCompany = site_express_default_carrier();
            }
        }

        $order->update([
            'ship_status' => Order::SHIP_STATUS_DELIVERED,
            'ship_data' => [
                'express_company' => $expressCompany,
                'express_no' => $expressNo,
            ],
        ]);

        $order->refresh();
        app(OrderFulfillmentService::class)->syncShippedStage($order);
        $order->refresh();

        if ($notifyUser && $order->user && is_site_mode_a()) {
            try {
                $order->user->notify(new OrderShippedNotification($order));
            } catch (\Throwable $e) {
                \Log::warning('发货通知邮件发送失败', [
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $order;
    }
}
