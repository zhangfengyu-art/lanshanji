<?php

use App\Models\Category;

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$dryRun = in_array('--dry-run', $argv, true);
$skipRegression = !in_array('--include-regression', $argv, true);

echo $dryRun ? "【预览模式】不会真正删除\n" : "【执行模式】将删除空分类\n";
if ($skipRegression) {
    echo "将跳过名称含 [回归测试] 的分类（可加 --include-regression 一并处理）\n";
}
echo "\n";

$deleted = 0;
$skipped = 0;
$maxRounds = 100;

for ($round = 1; $round <= $maxRounds; $round++) {
    $candidates = Category::query()
        ->withCount(['products', 'children'])
        ->orderBy('id')
        ->get()
        ->filter(function (Category $category) {
            return (int) $category->products_count === 0 && (int) $category->children_count === 0;
        });

    if ($candidates->isEmpty()) {
        break;
    }

    echo "--- 第 {$round} 轮，候选 ".count($candidates)." 个 ---\n";

    foreach ($candidates as $category) {
        if ($skipRegression && strpos($category->name, '[回归测试]') !== false) {
            $skipped++;
            echo "跳过 [回归测试]: id {$category->id} {$category->name}\n";
            continue;
        }

        $parentLabel = $category->parent_id
            ? '子分类 parent_id='.$category->parent_id
            : '根分类';

        if ($dryRun) {
            echo "将删除 {$parentLabel}: id {$category->id} {$category->name}\n";
            $deleted++;
            continue;
        }

        try {
            $category->delete();
            $deleted++;
            echo "已删除 {$parentLabel}: id {$category->id} {$category->name}\n";
        } catch (\Throwable $e) {
            echo "失败 id {$category->id}: ".$e->getMessage()."\n";
        }
    }

    if ($dryRun) {
        break;
    }
}

echo "\n完成。\n";
if ($dryRun) {
    echo "预览将删除: {$deleted} 个空分类\n";
    echo "跳过: {$skipped}\n";
    echo "确认后执行: php scripts/delete_empty_categories.php\n";
} else {
    echo "已删除: {$deleted}\n";
    echo "跳过: {$skipped}\n";

    $remaining = Category::query()->withCount(['products', 'children'])->get()->filter(function ($c) {
        return (int) $c->products_count === 0 && (int) $c->children_count === 0;
    })->count();

    if ($remaining > 0) {
        echo "仍有 {$remaining} 个空分类未删（可能被跳过或删除失败）\n";
    }
}
