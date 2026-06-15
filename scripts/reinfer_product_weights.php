<?php

use App\Models\Product;
use App\Services\OrderTobaccoLimitService;
use App\Services\ProductWeightInferenceService;

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$apply = in_array('--apply', $argv, true);
$onlyDefault20 = in_array('--only-20', $argv, true);
$exportPath = null;

$scope = 'rolling';
if (in_array('--all-types', $argv, true)) {
    $scope = 'all';
} elseif (in_array('--cigarette-only', $argv, true)) {
    $scope = 'cigarette';
} elseif (in_array('--heated-only', $argv, true)) {
    $scope = 'heated';
} elseif (in_array('--rolling-only', $argv, true)) {
    $scope = 'rolling';
}

foreach ($argv as $arg) {
    if (strpos($arg, '--export=') === 0) {
        $exportPath = substr($arg, 9);
    }
}

$scopeLabels = [
    'rolling' => '仅手卷烟丝 (rolling_tobacco)',
    'cigarette' => '仅香烟 (cigarette)',
    'heated' => '仅加热烟 (heated_tobacco)',
    'all' => '全部烟草类型（慎用）',
];

echo '范围: '.($scopeLabels[$scope] ?? $scope)."\n";
echo $apply ? "模式: 写入数据库\n" : "模式: 预览（不加 --apply 不会改库）\n";
if ($onlyDefault20) {
    echo "附加过滤: 仅处理当前重量为 20g 的商品\n";
}
echo "\n";

$service = app(ProductWeightInferenceService::class);

$updated = 0;
$skipped = 0;
$unchanged = 0;
$rows = [];

Product::query()->orderBy('id')->chunk(200, function ($products) use (
    $service,
    $apply,
    $onlyDefault20,
    $scope,
    &$updated,
    &$skipped,
    &$unchanged,
    &$rows
) {
    foreach ($products as $product) {
        if (!matchesScope($product, $scope)) {
            $skipped++;
            continue;
        }

        $oldWeight = (int) $product->unit_weight_grams;

        if ($onlyDefault20 && $oldWeight !== 20) {
            $skipped++;
            continue;
        }

        if ($scope === 'rolling') {
            $newWeight = $service->inferShagWeightGrams($product->title);
        } else {
            $newWeight = $service->inferUnitWeightGrams(
                $product->title,
                '',
                $product->tobacco_type,
                $product->unit_sticks
            );
        }

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
    echo "手卷烟丝: php scripts/reinfer_product_weights.php --apply\n";
    echo "恢复香烟重量: php scripts/reinfer_product_weights.php --cigarette-only --apply\n";
    echo "恢复加热烟: php scripts/reinfer_product_weights.php --heated-only --apply\n";
}
echo "无变化: {$unchanged}\n";
echo "跳过: {$skipped}\n";

function matchesScope(Product $product, $scope)
{
    $type = (string) $product->tobacco_type;

    switch ($scope) {
        case 'rolling':
            return $type === OrderTobaccoLimitService::TYPE_ROLLING_TOBACCO;
        case 'cigarette':
            return $type === OrderTobaccoLimitService::TYPE_CIGARETTE;
        case 'heated':
            return $type === OrderTobaccoLimitService::TYPE_HEATED_TOBACCO;
        case 'all':
            return true;
        default:
            return false;
    }
}
