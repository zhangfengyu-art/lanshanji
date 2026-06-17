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

    /**
     * 读取显式履约阶段（缺失时按旧规则推断并回写）。
     */
    public function resolveStage(Order $order)
    {
        if (!$order->paid_at || $order->closed) {
            return self::STAGE_S0;
        }

        if ($this->isShipped($order)) {
            $stored = $this->storedStage($order);
            if ($stored !== self::STAGE_S4) {
                $this->setStage($order, self::STAGE_S4, 'ship_status_sync');
            }

            return self::STAGE_S4;
        }

        $stored = $this->storedStage($order);
        if ($stored === '') {
            $stored = $this->inferLegacyStage($order);
            $this->setStage($order, $stored, 'legacy_backfill', false);
        }

        if ($stored === self::STAGE_S4) {
            $this->setStage($order, self::STAGE_S3, 'ship_status_reverted', false);

            return self::STAGE_S3;
        }

        return $stored;
    }

    public function stageLabel(Order $order)
    {
        return data_get(self::stageLabels(), $this->resolveStage($order), '—');
    }

    public function adminActionAvailability(Order $order)
    {
        $stage = $this->resolveStage($order);

        return [
            'stage' => $stage,
            'can_start_processing' => $stage === self::STAGE_S1,
            'can_enter_stock_prep' => in_array($stage, [self::STAGE_S1, self::STAGE_S2], true),
            'can_revert_to_pending' => $stage === self::STAGE_S2,
            'can_revert_from_stock_prep' => $stage === self::STAGE_S3
                && !$order->hasFulfillmentPhoto()
                && !$this->packageAtLogisticsWarehouse($order),
            'can_mark_warehouse' => $stage === self::STAGE_S3 && !$this->packageAtLogisticsWarehouse($order),
            'at_warehouse' => $this->packageAtLogisticsWarehouse($order),
            'has_fulfillment_photo' => $order->hasFulfillmentPhoto(),
        ];
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
        ] as $value) {
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

    public function normalizeExtraArray(Order $order)
    {
        $extra = $order->extra;
        if (is_array($extra)) {
            return $extra;
        }

        if (is_string($extra) && $extra !== '') {
            $decoded = json_decode($extra, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    protected function persistExtra(Order $order, array $extra)
    {
        $order->update(['extra' => $extra]);

        return $order->fresh();
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
            $extra = $this->normalizeExtraArray($locked);
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
        $this->assertPaidAndNotShipped($order);
        if ($this->resolveStage($order) !== self::STAGE_S1) {
            throw new InvalidRequestException('仅待处理（S1）订单可开始处理。');
        }

        $extra = $this->normalizeExtraArray($order);
        $extra['processing_started_at'] = now()->toDateTimeString();

        return $this->setStage($order, self::STAGE_S2, 'start_processing', true, $extra);
    }

    /**
     * 进入备货/打包（S3）：允许 S1 或 S2 进入，可跳过 S2。
     */
    public function enterStockPrep(Order $order)
    {
        $this->assertPaidAndNotShipped($order);
        $stage = $this->resolveStage($order);
        if (!in_array($stage, [self::STAGE_S1, self::STAGE_S2], true)) {
            throw new InvalidRequestException('仅待处理或处理中订单可进入备货/打包。');
        }

        $extra = $this->normalizeExtraArray($order);
        if ($stage === self::STAGE_S1 && !$this->processingStartedAt($order)) {
            $extra['processing_started_at'] = now()->toDateTimeString();
        }
        if (!$this->isLocked($order)) {
            $extra['locked_at'] = now()->toDateTimeString();
        }

        return $this->setStage($order, self::STAGE_S3, 'enter_stock_prep', true, $extra);
    }

    /** @deprecated 使用 enterStockPrep */
    public function lockOrder(Order $order)
    {
        return $this->enterStockPrep($order);
    }

    public function revertToPending(Order $order)
    {
        $this->assertPaidAndNotShipped($order);
        if ($this->resolveStage($order) !== self::STAGE_S2) {
            throw new InvalidRequestException('仅处理中（S2）订单可退回待处理。');
        }

        $extra = $this->normalizeExtraArray($order);
        unset($extra['processing_started_at']);

        return $this->setStage($order, self::STAGE_S1, 'revert_to_pending', true, $extra);
    }

    public function revertFromStockPrep(Order $order)
    {
        $this->assertPaidAndNotShipped($order);
        if ($this->resolveStage($order) !== self::STAGE_S3) {
            throw new InvalidRequestException('仅备货/打包（S3）订单可退回上一阶段。');
        }
        if ($order->hasFulfillmentPhoto()) {
            throw new InvalidRequestException('已上传履约实拍图，请先删除图片后再退回。');
        }
        if ($this->packageAtLogisticsWarehouse($order)) {
            throw new InvalidRequestException('已标记送往物流仓库，无法退回上一阶段。');
        }

        $extra = $this->normalizeExtraArray($order);
        unset($extra['locked_at'], $extra['logistics_warehouse_at']);

        $target = $this->processingStartedAt($order) ? self::STAGE_S2 : self::STAGE_S1;

        return $this->setStage($order, $target, 'revert_from_stock_prep', true, $extra);
    }

    /** @deprecated 使用 revertFromStockPrep */
    public function unlockOrder(Order $order)
    {
        return $this->revertFromStockPrep($order);
    }

    public function markPackageAtLogisticsWarehouse(Order $order)
    {
        $this->assertPaidAndNotShipped($order);
        if ($this->resolveStage($order) !== self::STAGE_S3) {
            throw new InvalidRequestException('仅备货/打包（S3）订单可标记送往物流仓库。');
        }
        if ($this->packageAtLogisticsWarehouse($order)) {
            throw new InvalidRequestException('订单已标记为送往物流仓库。');
        }

        $extra = $this->normalizeExtraArray($order);
        $extra['logistics_warehouse_at'] = now()->toDateTimeString();

        return $this->setStage($order, self::STAGE_S3, 'mark_logistics_warehouse', true, $extra);
    }

    public function syncShippedStage(Order $order)
    {
        if (!$this->isShipped($order)) {
            return $order;
        }

        return $this->setStage($order, self::STAGE_S4, 'ship');
    }

    public function syncUnshippedStage(Order $order)
    {
        if ($this->isShipped($order)) {
            return $order;
        }

        $stored = $this->storedStage($order);
        if ($stored === self::STAGE_S4) {
            $fallback = $this->inferLegacyStage($order);

            return $this->setStage($order, $fallback === self::STAGE_S4 ? self::STAGE_S3 : $fallback, 'unship_revert');
        }

        return $order;
    }

    public function packageAtLogisticsWarehouse(Order $order)
    {
        return trim((string) data_get($order->extra, 'logistics_warehouse_at', '')) !== '';
    }

    public static function stageFilterOptions()
    {
        return [
            '' => '全部阶段',
            self::STAGE_S1 => 'S1 待处理',
            self::STAGE_S2 => 'S2 处理中',
            self::STAGE_S3 => 'S3 备货/打包',
            self::STAGE_S4 => 'S4 已发货',
        ];
    }

    public function applyStageFilter($query, $stage)
    {
        $stage = strtoupper(trim((string) $stage));
        if (!in_array($stage, [self::STAGE_S1, self::STAGE_S2, self::STAGE_S3, self::STAGE_S4], true)) {
            return $query;
        }

        if ($stage === self::STAGE_S4) {
            return $query->whereIn('ship_status', [Order::SHIP_STATUS_DELIVERED, Order::SHIP_STATUS_RECEIVED]);
        }

        $query = $query->whereNotIn('ship_status', [Order::SHIP_STATUS_DELIVERED, Order::SHIP_STATUS_RECEIVED]);

        return $query->whereRaw(
            "JSON_UNQUOTE(JSON_EXTRACT(extra, '$.fulfillment_stage')) = ?",
            [$stage]
        );
    }

    protected function storedStage(Order $order)
    {
        $stage = strtoupper(trim((string) data_get($order->extra, 'fulfillment_stage', '')));
        $valid = [self::STAGE_S1, self::STAGE_S2, self::STAGE_S3, self::STAGE_S4];

        return in_array($stage, $valid, true) ? $stage : '';
    }

    protected function setStage(Order $order, $stage, $action, $appendLog = true, array $extra = null)
    {
        $extra = $extra ?? $this->normalizeExtraArray($order);
        $from = $this->storedStage($order);
        $extra['fulfillment_stage'] = $stage;

        if ($appendLog) {
            $log = (array) data_get($extra, 'fulfillment_stage_log', []);
            $log[] = [
                'at' => now()->toDateTimeString(),
                'action' => (string) $action,
                'from' => $from !== '' ? $from : null,
                'to' => $stage,
            ];
            $extra['fulfillment_stage_log'] = $log;
        }

        return $this->persistExtra($order, $extra);
    }

    protected function inferLegacyStage(Order $order)
    {
        if (!$order->paid_at || $order->closed) {
            return self::STAGE_S0;
        }

        if ($this->isShipped($order)) {
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

    protected function assertPaidAndNotShipped(Order $order)
    {
        if (!$order->paid_at) {
            throw new InvalidRequestException('仅已支付订单可操作。');
        }

        if ($this->isShipped($order)) {
            throw new InvalidRequestException('订单已发货，无法变更履约阶段。');
        }
    }

    protected function isShipped(Order $order)
    {
        return in_array($order->ship_status, [Order::SHIP_STATUS_DELIVERED, Order::SHIP_STATUS_RECEIVED], true);
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

    protected function addressChangeCount(Order $order)
    {
        return (int) data_get($order->extra, 'address_change_count', 0);
    }
}
