<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$fixed = 0;
App\Models\Product::query()->with('skus')->chunkById(100, function ($products) use (&$fixed) {
    foreach ($products as $product) {
        $minPrice = $product->skus->min('price');
        if ($minPrice === null || (float) $minPrice <= 0) {
            continue;
        }
        if ((float) $product->price !== (float) $minPrice) {
            $product->forceFill(['price' => $minPrice])->save();
            $fixed++;
            echo "product #{$product->id}: {$product->getOriginal('price')} -> {$minPrice}\n";
        }
    }
});

echo "fixed {$fixed} product(s)\n";
