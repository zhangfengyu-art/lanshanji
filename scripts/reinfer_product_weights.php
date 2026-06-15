<?php

use App\Models\Product;
use App\Services\ProductWeightInferenceService;

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$apply = in_array('--apply', $argv, true);
$onlyDefault20 = in_array('--only-20', $argv, true);
$exportPath = null;

foreach ($argv as $arg) {
    if (strpos($arg, '--export=') === 0) {
        $exportPath = substr($arg, 9);
    }
}

$service = app(ProductWeightInferenceService::class);

$updated = 0;
$skipped = 0;
$unchanged = 0;
$rows = [];

Product::query()->orderBy('id')->chunk(200, function ($products) use (
    $service,
    $apply,
    $onlyDefault20,
    &$updated,
    &$skipped,
    &$unchanged,
    &$rows
) {
    foreach ($products as $product) {
        $oldWeight = (int) $product->unit_weight_grams;

        if ($onlyDefault20 && $oldWeight !== 20) {
            $skipped++;
            continue;
        }

        $newWeight = $service->inferUnitWeightGrams(
            $product->title,
            '',
            $product->tobacco_type,
            $product->unit_sticks
        );

        $rows[] = [
            'id' => $product->id,
            'ref_id' => $product->source_ref_id,
            'title' => $product->title,
            'tobacco_type' => $product->tobacco_type,
            'unit_sticks' => $product->unit_sticks,
            'old_weight' => $oldWeight,
            'new_weight' => $newWeight,
        ];

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

if ($exportPath) {
    $handle = fopen($exportPath, 'w');
    fprintf($handle, "\xEF\xBB\xBF");
    fputcsv($handle, ['id', 'source_ref_id', 'title', 'tobacco_type', 'unit_sticks', 'old_weight_g', 'new_weight_g']);
    foreach ($rows as $row) {
        fputcsv($handle, [
            $row['id'],
            $row['ref_id'],
            $row['title'],
            $row['tobacco_type'],
            $row['unit_sticks'],
            $row['old_weight'],
            $row['new_weight'],
        ]);
    }
    fclose($handle);
    echo "\n已导出: {$exportPath}\n";
}

echo "\n";
if ($apply) {
    echo "已更新: {$updated}\n";
} else {
    echo "预览将变更: {$updated}\n";
    echo "确认后执行: php scripts/reinfer_product_weights.php --apply\n";
    echo "仅改当前为 20g 的: php scripts/reinfer_product_weights.php --only-20 --apply\n";
}
echo "无变化: {$unchanged}\n";
echo "跳过: {$skipped}\n";
