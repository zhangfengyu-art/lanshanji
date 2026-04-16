<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class OrderExportService
{
    const NAME_MAPPING = [
        '和平铁罐' => 'ピース (50本入) 缶',
        '七星蓝莓 5mg' => 'セブンスター・メンソール・ベリー 5',
        '柔和七星 10mg' => 'メビウス・オリジナル 10',
    ];

    public function buildExportData($startDate = null, $endDate = null)
    {
        list($startAt, $endAt) = $this->resolveRange($startDate, $endDate);

        $orders = $this->queryOrders($startAt, $endAt)->get();

        return [
            'startAt' => $startAt,
            'endAt' => $endAt,
            'generatedAt' => Carbon::now(),
            'orders' => $orders,
            'summaryRows' => $this->buildSummaryRows($orders),
            'detailRows' => $this->buildDetailRows($orders),
            'totalOrders' => $orders->count(),
            'totalItems' => $orders->sum(function (Order $order) {
                return $order->items->sum('amount');
            }),
        ];
    }

    public function buildFileName(Carbon $endAt)
    {
        return '岚山发货单_Arashiyama_Orders_' . $endAt->format('Ymd') . '.pdf';
    }

    protected function resolveRange($startDate = null, $endDate = null)
    {
        if ($startDate && $endDate) {
            return [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ];
        }

        $endAt = Carbon::now();
        $startAt = (clone $endAt)->subDay();

        return [$startAt, $endAt];
    }

    protected function queryOrders(Carbon $startAt, Carbon $endAt)
    {
        return Order::query()
            ->with(['user', 'items.product', 'items.productSku'])
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$startAt, $endAt])
            ->orderBy('paid_at');
    }

    protected function buildSummaryRows(Collection $orders)
    {
        $summary = [];

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $title = optional($item->product)->title ?: optional($item->productSku)->title ?: '未命名商品';
                $displayName = $this->getBilingualName($title);
                $key = $displayName;

                if (!isset($summary[$key])) {
                    $summary[$key] = [
                        'name_cn' => $title,
                        'name_jp' => $this->getJapaneseName($title),
                        'display_name' => $displayName,
                        'total_qty' => 0,
                    ];
                }

                $summary[$key]['total_qty'] += (int) $item->amount;
            }
        }

        return array_values($summary);
    }

    protected function buildDetailRows(Collection $orders)
    {
        $rows = [];

        foreach ($orders as $order) {
            $recipient = data_get($order->address, 'contact_name', '-');
            $phone = data_get($order->address, 'contact_phone', '-');
            $address = data_get($order->address, 'address', '-');

            foreach ($order->items as $item) {
                $title = optional($item->product)->title ?: optional($item->productSku)->title ?: '未命名商品';

                $rows[] = [
                    'order_no' => $order->no,
                    'recipient' => $recipient,
                    'phone' => $phone,
                    'address' => $address,
                    'name_cn' => $title,
                    'name_jp' => $this->getJapaneseName($title),
                    'display_name' => $this->getBilingualName($title),
                    'qty' => (int) $item->amount,
                    'paid_at' => $order->paid_at,
                ];
            }
        }

        return $rows;
    }

    protected function getBilingualName($chineseName)
    {
        $japaneseName = $this->getJapaneseName($chineseName);

        return $japaneseName ? $chineseName . ' | ' . $japaneseName : $chineseName;
    }

    protected function getJapaneseName($chineseName)
    {
        return static::NAME_MAPPING[$chineseName] ?? '';
    }
}
