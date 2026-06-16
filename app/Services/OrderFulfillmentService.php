<?php

namespace App\Services;

use App\Exceptions\InvalidRequestException;
use App\Models\Order;
class OrderFulfillmentService
{
    const STAGE_S0 = 'S0';
    const STAGE_S1 = 'S1';
    const STAGE_S2 = 'S2';
    const STAGE_S3 = 'S3';
    const STAGE_S4 = 'S4';

    const MAX_ADDRESS_CHANGES = 2;

    public static function stageLabels()
    {
        return [
            self::STAGE_S0 => '待支付',
            self::STAGE_S1 => '待处理',
            self::STAGE_S2 => '处理中',
            self::STAGE_S3 => '备货/打包',
            self::STAGE_S4 => '已发货',
        ];
    }

    public function resolveStage(Order $order)
    {
        if (!$order->paid_at || $order->closed) {
            return self::STAGE_S0;
        }

        if (in_array($order->ship_status, [Order::SHIP_STATUS_DELIVERED, Order::SHIP_STATUS_RECEIVED], true)) {
            return self::STAGE_S4;
        }

        if ($this->isLocked($order) || $order->hasFulfillmentPhoto()) {
            return self::STAGE_S3;
        }

        if ($this->processingStartedAt($order)) {
            return self::STAGE_S2;
        }

        return self::STAGE_S1;
    }

    public function stageLabel(Order $order)
    {
        $stage = $this->resolveStage($order);

        return data_get(self::stageLabels(), $stage, $stage);
    }

    public function canSelfChangeAddress(Order $order)
    {
        if (is_site_mode_b()) {
            return false;
        }

        return $this->resolveStage($order) === self::STAGE_S1
            && $this->refundAllowsModification($order)
            && $this->addressChangeCount($order) < self::MAX_ADDRESS_CHANGES;
    }

    public function remainingAddressChanges(Order $order)
    {
        return max(0, self::MAX_ADDRESS_CHANGES - $this->addressChangeCount($order));
    }

    public function buildOrderAddressPayload(array $input)
    {
        $province = trim((string) data_get($input, 'province'));
        $city = trim((string) data_get($input, 'city'));
        $district = trim((string) data_get($input, 'district'));
        $detail = trim((string) data_get($input, 'address'));
        $contactName = trim((string) data_get($input, 'contact_name'));
        $contactPhone = trim((string) data_get($input, 'contact_phone'));
        $zip = (int) data_get($input, 'zip', 0);

        foreach ([
            'province' => $province,
            'city' => $city,
            'district' => $district,
            'address' => $detail,
            'contact_name' => $contactName,
            'contact_phone' => $contactPhone,
        ] as $label => $value) {
            if ($value === '') {
                throw new InvalidRequestException('请完整填写省市区、详细地址、收件人与手机号。');
            }
        }

        return [
            'province' => $province,
            'city' => $city,
            'district' => $district,
            'address' => $detail,
            'full_address' => $province.$city.$district.$detail,
            'zip' => $zip,
            'contact_name' => $contactName,
            'contact_phone' => $contactPhone,
        ];
    }

    public function updateAddress(Order $order, array $input)
    {
        if (!$this->canSelfChangeAddress($order)) {
            throw new InvalidRequestException('当前订单状态不可自助改址，请通过客户反馈联系本站。');
        }

        $address = $this->buildOrderAddressPayload($input);

        return \DB::transaction(function () use ($order, $address) {
            $locked = Order::query()->lockForUpdate()->find($order->id);
            if (!$this->canSelfChangeAddress($locked)) {
                throw new InvalidRequestException('当前订单状态不可自助改址。');
            }

            $before = $locked->address;
            $extra = $locked->extra ?: [];
            $log = (array) data_get($extra, 'address_change_log', []);
            $log[] = [
                'at' => now()->toDateTimeString(),
                'before' => $before,
                'after' => $address,
            ];
            $extra['address_change_log'] = $log;
            $extra['address_change_count'] = $this->addressChangeCount($locked) + 1;

            $locked->update([
                'address' => $address,
                'extra' => $extra,
            ]);

            return $locked->fresh();
        });
    }

    public function startProcessing(Order $order)
    {
        if (!$order->paid_at) {
            throw new InvalidRequestException('仅已支付订单可开始处理。');
        }

        if ($this->resolveStage($order) === self::STAGE_S4) {
            throw new InvalidRequestException('订单已发货，无法开始处理。');
        }

        $extra = $order->extra ?: [];
        if ($this->processingStartedAt($order)) {
            throw new InvalidRequestException('订单已在处理中。');
        }

        $extra['processing_started_at'] = now()->toDateTimeString();
        $order->update(['extra' => $extra]);

        return $order->fresh();
    }

    public function lockOrder(Order $order)
    {
        if (!$order->paid_at) {
            throw new InvalidRequestException('仅已支付订单可锁定。');
        }

        if (in_array($order->ship_status, [Order::SHIP_STATUS_DELIVERED, Order::SHIP_STATUS_RECEIVED], true)) {
            throw new InvalidRequestException('订单已发货，无法锁定。');
        }

        $extra = $order->extra ?: [];
        $extra['locked_at'] = now()->toDateTimeString();
        $order->update(['extra' => $extra]);

        return $order->fresh();
    }

    public function unlockOrder(Order $order)
    {
        if (in_array($order->ship_status, [Order::SHIP_STATUS_DELIVERED, Order::SHIP_STATUS_RECEIVED], true)) {
            throw new InvalidRequestException('订单已发货，无法解除锁定。');
        }

        $extra = $order->extra ?: [];
        unset($extra['locked_at']);
        $order->update(['extra' => $extra]);

        return $order->fresh();
    }

    protected function refundAllowsModification(Order $order)
    {
        return $order->refund_status === Order::REFUND_STATUS_PENDING;
    }

    protected function processingStartedAt(Order $order)
    {
        return trim((string) data_get($order->extra, 'processing_started_at', '')) !== '';
    }

    protected function isLocked(Order $order)
    {
        return trim((string) data_get($order->extra, 'locked_at', '')) !== '';
    }

    public function packageAtLogisticsWarehouse(Order $order)
    {
        return trim((string) data_get($order->extra, 'logistics_warehouse_at', '')) !== '';
    }

    public function markPackageAtLogisticsWarehouse(Order $order)
    {
        if (!$order->paid_at) {
            throw new InvalidRequestException('仅已支付订单可标记。');
        }

        if (in_array($order->ship_status, [Order::SHIP_STATUS_DELIVERED, Order::SHIP_STATUS_RECEIVED], true)) {
            throw new InvalidRequestException('订单已发货，无法标记送往仓库。');
        }

        $extra = $order->extra ?: [];
        if ($this->packageAtLogisticsWarehouse($order)) {
            throw new InvalidRequestException('订单已标记为送往物流仓库。');
        }

        $extra['logistics_warehouse_at'] = now()->toDateTimeString();
        $order->update(['extra' => $extra]);

        return $order->fresh();
    }

    protected function addressChangeCount(Order $order)
    {
        return (int) data_get($order->extra, 'address_change_count', 0);
    }

}
