<?php

namespace App\Services\Lianhua;

use App\Models\Order;
use App\Services\AdminOrderShipmentService;
use App\Services\ShippingModeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LianhuaExpressShipmentSyncService
{
    /** @var LianhuaExpressClient */
    protected $client;

    /** @var AdminOrderShipmentService */
    protected $shipmentService;

    public function __construct(LianhuaExpressClient $client = null, AdminOrderShipmentService $shipmentService = null)
    {
        $this->client = $client ?: new LianhuaExpressClient();
        $this->shipmentService = $shipmentService ?: app(AdminOrderShipmentService::class);
    }

    public function sync($dryRun = false)
    {
        $records = $this->client->fetchShippedRecords();
        $pendingOrders = $this->pendingShipmentOrders();

        $report = [
            'fetched' => count($records),
            'pending_orders' => $pendingOrders->count(),
            'matched' => 0,
            'applied' => 0,
            'skipped' => [],
            'ambiguous' => [],
            'unmatched_records' => [],
            'errors' => [],
        ];

        $usedTrackingNumbers = [];

        foreach ($records as $record) {
            $tracking = strtoupper(trim((string) data_get($record, 'tracking')));
            if ($tracking === '') {
                continue;
            }

            if (isset($usedTrackingNumbers[$tracking])) {
                $report['skipped'][] = [
                    'tracking' => $tracking,
                    'recipient' => data_get($record, 'recipient'),
                    'reason' => '联华列表中出现重复单号，已跳过',
                ];
                continue;
            }

            if ($this->trackingAlreadyUsed($tracking)) {
                $report['skipped'][] = [
                    'tracking' => $tracking,
                    'recipient' => data_get($record, 'recipient'),
                    'reason' => '该单号已在后台使用过',
                ];
                $usedTrackingNumbers[$tracking] = true;
                continue;
            }

            $matches = $this->matchOrders($pendingOrders, $record);

            if ($matches->isEmpty()) {
                $report['unmatched_records'][] = [
                    'tracking' => $tracking,
                    'recipient' => data_get($record, 'recipient'),
                    'phone' => data_get($record, 'phone'),
                ];
                continue;
            }

            if ($matches->count() > 1) {
                $report['ambiguous'][] = [
                    'tracking' => $tracking,
                    'recipient' => data_get($record, 'recipient'),
                    'phone' => data_get($record, 'phone'),
                    'order_nos' => $matches->pluck('no')->all(),
                ];
                continue;
            }

            /** @var Order $order */
            $order = $matches->first();
            $report['matched']++;

            if ($dryRun) {
                $report['applied']++;
                Log::info('[lianhua] dry-run 将发货', [
                    'order_no' => $order->no,
                    'recipient' => data_get($record, 'recipient'),
                    'tracking' => $tracking,
                ]);
                continue;
            }

            try {
                $expressCompany = $this->resolveExpressCompany($order);

                DB::transaction(function () use ($order, $tracking, $expressCompany) {
                    $this->shipmentService->applyShipment(
                        $order->fresh(),
                        $expressCompany,
                        $tracking
                    );
                });

                $report['applied']++;
                $usedTrackingNumbers[$tracking] = true;
                $pendingOrders = $pendingOrders->reject(function (Order $candidate) use ($order) {
                    return (int) $candidate->id === (int) $order->id;
                });

                Log::info('[lianhua] 已自动发货', [
                    'order_no' => $order->no,
                    'tracking' => $tracking,
                    'recipient' => data_get($record, 'recipient'),
                ]);
            } catch (\Throwable $e) {
                $report['errors'][] = [
                    'order_no' => $order->no,
                    'tracking' => $tracking,
                    'message' => $e->getMessage(),
                ];
                Log::warning('[lianhua] 自动发货失败', [
                    'order_no' => $order->no,
                    'tracking' => $tracking,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $report;
    }

    protected function resolveExpressCompany(Order $order)
    {
        $configured = trim((string) config('lianhua_express.express_company', ''));
        if ($configured !== '') {
            return $configured;
        }

        $mode = trim((string) data_get($order->extra, 'fee_details.shipping_mode', data_get($order->extra, 'shipping_mode', '')));
        $options = site_express_carrier_options();

        if ($mode === ShippingModeService::MODE_EMS) {
            foreach ($options as $option) {
                if (stripos($option, 'EMS') !== false) {
                    return $option;
                }
            }
        }

        return site_express_default_carrier();
    }

    protected function pendingShipmentOrders()
    {
        return Order::query()
            ->whereNotNull('paid_at')
            ->where('closed', false)
            ->where('ship_status', Order::SHIP_STATUS_PENDING)
            ->where('refund_status', Order::REFUND_STATUS_PENDING)
            ->orderBy('paid_at')
            ->get();
    }

    protected function matchOrders($orders, array $record)
    {
        $recipient = $this->normalizePersonName(data_get($record, 'recipient'));
        $phone = $this->normalizePhone(data_get($record, 'phone'));

        if ($recipient === '') {
            return collect();
        }

        $matches = $orders->filter(function (Order $order) use ($recipient) {
            $orderName = $this->normalizePersonName(data_get($order->address, 'contact_name'));

            return $orderName !== '' && $orderName === $recipient;
        });

        if ($matches->count() <= 1) {
            return $matches->values();
        }

        if ($phone !== '') {
            $phoneMatches = $matches->filter(function (Order $order) use ($phone) {
                $orderPhone = $this->normalizePhone(data_get($order->address, 'contact_phone'));

                return $orderPhone !== '' && ($orderPhone === $phone || substr($orderPhone, -4) === substr($phone, -4));
            });

            if ($phoneMatches->count() === 1) {
                return $phoneMatches->values();
            }
        }

        return $matches->values();
    }

    protected function trackingAlreadyUsed($tracking)
    {
        if (!db_has_column('orders', 'ship_data')) {
            return false;
        }

        return Order::query()
            ->whereIn('ship_status', [Order::SHIP_STATUS_DELIVERED, Order::SHIP_STATUS_RECEIVED])
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(ship_data, '$.express_no')) = ?", [$tracking])
            ->exists();
    }

    protected function normalizePersonName($name)
    {
        $name = trim((string) $name);
        $name = str_replace([' ', '　', '•', '·'], '', $name);

        return $name;
    }

    protected function normalizePhone($phone)
    {
        return preg_replace('/\D+/', '', (string) $phone);
    }
}
