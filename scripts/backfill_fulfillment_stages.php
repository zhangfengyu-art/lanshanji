<?php

/**
 * 为历史订单回写 extra.fulfillment_stage（部署方案 B 后执行一次）。
 *
 * 用法：php scripts/backfill_fulfillment_stages.php
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Order;
use App\Services\OrderFulfillmentService;

$fulfillment = app(OrderFulfillmentService::class);
$updated = 0;

Order::query()
    ->whereNotNull('paid_at')
    ->orderBy('id')
    ->chunk(100, function ($orders) use ($fulfillment, &$updated) {
        foreach ($orders as $order) {
            $before = trim((string) data_get($order->extra, 'fulfillment_stage', ''));
            $fulfillment->resolveStage($order);
            $order->refresh();
            $after = trim((string) data_get($order->extra, 'fulfillment_stage', ''));
            if ($before !== $after) {
                $updated++;
            }
        }
    });

echo "Backfill done. Updated {$updated} orders.\n";
