<?php

use App\Models\Category;
use App\Models\Product;

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$updated = 0;
$skipped = 0;
$merged = 0;
$errors = [];

Category::query()->orderBy('id')->chunk(100, function ($categories) use (&$updated, &$skipped, &$merged, &$errors) {
    foreach ($categories as $category) {
        $newName = preg_replace('/\s*EMS直邮\s*$/u', '', trim($category->name));
        $newName = trim($newName);

        if ($newName === '' || $newName === $category->name) {
            $skipped++;
            continue;
        }

        $duplicate = Category::query()
            ->where('parent_id', $category->parent_id)
            ->where('name', $newName)
            ->where('id', '!=', $category->id)
            ->first();

        if ($duplicate) {
            $productCount = Product::query()->where('category_id', $category->id)->count();
            if ($productCount > 0) {
                Product::query()
                    ->where('category_id', $category->id)
                    ->update(['category_id' => $duplicate->id]);
            }

            try {
                $category->delete();
                $merged++;
                echo "合并: {$category->name} (id {$category->id}) → {$newName} (id {$duplicate->id}), 迁移商品 {$productCount} 个\n";
            } catch (\Throwable $e) {
                $errors[] = "id {$category->id}: ".$e->getMessage();
            }

            continue;
        }

        $oldName = $category->name;
        $category->forceFill(['name' => $newName])->save();
        $updated++;
        echo "更新: {$oldName} → {$newName}\n";
    }
});

echo "\n完成。\n";
echo "改名: {$updated}\n";
echo "合并重复: {$merged}\n";
echo "跳过: {$skipped}\n";

if (!empty($errors)) {
    echo "错误: ".count($errors)."\n";
    foreach ($errors as $error) {
        echo " - {$error}\n";
    }
}
