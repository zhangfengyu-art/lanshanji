<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;

class OrderRefundPolicyService
{
    /** @var OrderFulfillmentService */
    protected $fulfillment;

    public function __construct(OrderFulfillmentService $fulfillment)
    {
        $this->fulfillment = $fulfillment;
    }

    public function adminReasonOptions()
    {
        return config('order_refund.admin_reasons', []);
    }

    public function reasonLabel($code)
    {
        return data_get($this->adminReasonOptions(), $code, $code);
    }

    /**
     * 计算后台执行退款时的比例与说明（不发起支付请求）。
     */
    public function previewAdminRefund(Order $order, array $input = [])
    {
        return $this->evaluateAdminRefund($order, $input, false);
    }

    /**
     * @param bool $strict 为 true 时遇到不允许将抛 InvalidRequestException
     */
    public function evaluateAdminRefund(Order $order, array $input = [], $strict = true)
    {
        $result = [
            'allowed' => false,
            'refund_ratio' => 0.0,
            'refund_amount_cny' => 0.0,
            'pay_amount_cny' => round($order->getPaymentAmountCny(), 2),
            'cancellation_fee_cny' => 0.0,
            'stage' => $this->fulfillment->resolveStage($order),
            'stage_label' => $this->fulfillment->stageLabel($order),
            'message' => '',
            'policy_hint' => '',
            'requires_s4_approval' => false,
            'show_supplier_shortage' => false,
            'package_at_warehouse' => $this->fulfillment->packageAtLogisticsWarehouse($order),
        ];

        if (!is_site_mode_a()) {
            $result['message'] = '当前站点模式不支持此退款规则。';

            return $this->finishEvaluate($result, $strict);
        }

        if (!$order->paid_at) {
            $result['message'] = '订单未支付，无法退款。';

            return $this->finishEvaluate($result, $strict);
        }

        if ($order->refund_status === Order::REFUND_STATUS_SUCCESS) {
            $result['message'] = '订单已退款成功。';

            return $this->finishEvaluate($result, $strict);
        }

        if ($order->refund_status === Order::REFUND_STATUS_PROCESSING) {
            $result['message'] = '退款处理中，请勿重复提交。';

            return $this->finishEvaluate($result, $strict);
        }

        $supplierFailed = (bool) data_get($input, 'supplier_cannot_supply', false);
        $s4Approved = (bool) data_get($input, 's4_special_approval', false);
        $stage = $result['stage'];

        if ($stage === OrderFulfillmentService::STAGE_S1) {
            $result['allowed'] = true;
            $result['refund_ratio'] = 1.0;
            $result['policy_hint'] = '待处理阶段：全额退款（100%）。';

            return $this->applyAmounts($result);
        }

        if ($stage === OrderFulfillmentService::STAGE_S2) {
            $result['show_supplier_shortage'] = true;
            $exceeded = $this->waitingSupplierExceeded($order);

            if ($supplierFailed || $exceeded) {
                $result['allowed'] = true;
                $result['refund_ratio'] = 1.0;
                $result['policy_hint'] = $supplierFailed
                    ? '已确认供应商无法正常供货：全额退款（100%）。'
                    : '等待供应商配送已超过 '.(int) config('order_refund.waiting_supplier_hours', 168).' 小时：全额退款（100%）。';

                return $this->applyAmounts($result);
            }

            $result['allowed'] = true;
            $result['refund_ratio'] = $this->partialRefundRatio();
            $result['policy_hint'] = '等待供应商配送未满 '.(int) config('order_refund.waiting_supplier_hours', 168).' 小时：收取 20% 取消费，退款 80%。';

            return $this->applyAmounts($result);
        }

        if ($stage === OrderFulfillmentService::STAGE_S3) {
            if ($result['package_at_warehouse']) {
                $result['message'] = '包裹已送往物流仓库，无法承诺来得及取消，原则上不支持退款。';

                return $this->finishEvaluate($result, $strict);
            }

            $result['allowed'] = true;
            $result['refund_ratio'] = $this->partialRefundRatio();
            $result['policy_hint'] = '已开始发货处理且包裹尚未送往物流仓库：收取 20% 取消费，退款 80%。';

            return $this->applyAmounts($result);
        }

        if ($stage === OrderFulfillmentService::STAGE_S4) {
            $result['requires_s4_approval'] = true;

            if (!$s4Approved) {
                $result['message'] = '已发货（已有物流单号）订单不支持取消；如需退款须勾选「已发货特批退款」并填写原因。';

                return $this->finishEvaluate($result, $strict);
            }

            $ratio = data_get($input, 's4_refund_ratio');
            if ($ratio === null || $ratio === '') {
                $ratio = $this->partialRefundRatio();
            }
            $ratio = (float) $ratio;
            if (!in_array($ratio, [1.0, $this->partialRefundRatio()], true)) {
                $result['message'] = '已发货特批退款仅可选择 80% 或 100% 退款比例。';

                return $this->finishEvaluate($result, $strict);
            }

            $result['allowed'] = true;
            $result['refund_ratio'] = $ratio;
            $result['policy_hint'] = $ratio >= 1.0
                ? '已发货特批：全额退款（100%）。'
                : '已发货特批：收取 20% 取消费，退款 80%。';

            return $this->applyAmounts($result);
        }

        $result['message'] = '当前订单状态不可退款。';

        return $this->finishEvaluate($result, $strict);
    }

    protected function partialRefundRatio()
    {
        $fee = (float) config('order_refund.cancellation_fee_ratio', 0.20);
        $fee = max(0.0, min(1.0, $fee));

        return round(1.0 - $fee, 4);
    }

    protected function waitingSupplierExceeded(Order $order)
    {
        $started = trim((string) data_get($order->extra, 'processing_started_at', ''));
        if ($started === '') {
            return false;
        }

        try {
            $hours = (int) config('order_refund.waiting_supplier_hours', 168);

            return Carbon::parse($started)->lte(now()->subHours($hours));
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function applyAmounts(array $result)
    {
        $pay = round((float) $result['pay_amount_cny'], 2);
        $ratio = round((float) $result['refund_ratio'], 4);
        $refund = round($pay * $ratio, 2);
        if ($refund > $pay) {
            $refund = $pay;
        }

        $result['refund_amount_cny'] = $refund;
        $result['cancellation_fee_cny'] = round($pay - $refund, 2);

        return $result;
    }

    protected function finishEvaluate(array $result, $strict)
    {
        if ($strict && !$result['allowed']) {
            throw new \App\Exceptions\InvalidRequestException($result['message'] ?: '当前不可退款。');
        }

        return $result;
    }
}
