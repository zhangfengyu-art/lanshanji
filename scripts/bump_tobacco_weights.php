<?php

use App\Models\Product;
use App\Services\OrderTobaccoLimitService;
use App\Services\ProductWeightInferenceService;

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$apply = in_array('--apply', $argv, true);
$percent = null;

foreach ($argv as $arg) {
    if (strpos($arg, '--percent=') === 0) {
        $percent = (float) substr($arg, 10);
    }
}

$service = app(ProductWeightInferenceService::class);
if ($percent === null) {
    $percent = $service->getTobaccoWeightMarkupPercent();
}

if ($percent <= 0) {
    echo "上浮比例为 0，无需调整。可在 config/tobacco_weight.php 设置 markup_percent。\n";
    exit(0);
}

$multiplier = 1 + $percent / 100;
$tobaccoTypes = [
    OrderTobaccoLimitService::TYPE_CIGARETTE,
    OrderTobaccoLimitService::TYPE_HEATED_TOBACCO,
    OrderTobaccoLimitService::TYPE_ROLLING_TOBACCO,
];

echo '范围: 全部烟草（香烟 + 加热烟 + 手卷烟丝）'."\n";
echo '上浮: '.$percent.'%'."\n";
echo $apply ? "模式: 写入数据库\n" : "模式: 预览（不加 --apply 不会改库）\n";
echo "\n";

$updated = 0;
$unchanged = 0;

Product::query()
    ->whereIn('tobacco_type', $tobaccoTypes)
    ->orderBy('id')
    ->chunk(200, function ($products) use ($apply, $multiplier, &$updated, &$unchanged) {
        foreach ($products as $product) {
            $oldWeight = (int) $product->unit_weight_grams;
            $newWeight = max(1, (int) round($oldWeight * $multiplier));

            if ($newWeight === $oldWeight) {
                $unchanged++;
                continue;
            }

            if ($apply) {
                $product->forceFill(['unit_weight_grams' => $newWeight])->save();
                echo "id {$product->id}: {$oldWeight}g → {$newWeight}g | {$product->title}\n";
            } else {
                echo "[预览] id {$product->id}: {$oldWeight}g → {$newWeight}g | {$product->title}\n";
            }

            $updated++;
        }
    });

echo "\n";
if ($apply) {
    echo "已更新: {$updated}\n";
} else {
    echo "预览将变更: {$updated}\n";
    echo "写入: php scripts/bump_tobacco_weights.php --apply\n";
}
echo "无变化: {$unchanged}\n";
