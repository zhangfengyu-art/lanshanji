<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSku;
use App\Services\OrderTobaccoLimitService;
use App\Services\ShippingModeService;

$catEms = Category::firstOrCreate(
    ['name' => '[回归测试] EMS'],
    ['is_directory' => false, 'default_shipping_mode' => ShippingModeService::MODE_EMS]
);
$catTax = Category::firstOrCreate(
    ['name' => '[回归测试] 含税'],
    ['is_directory' => false, 'default_shipping_mode' => ShippingModeService::MODE_TAX_INCLUDED]
);

$ems = Product::updateOrCreate(
    ['title' => '[回归测试] EMS香烟'],
    [
        'description' => 'regression',
        'image' => 'products/regression-ems.jpg',
        'on_sale' => 1,
        'price' => 1000,
        'category_id' => $catEms->id,
        'shipping_mode' => ShippingModeService::MODE_EMS,
        'tobacco_type' => OrderTobaccoLimitService::TYPE_CIGARETTE,
        'unit_weight_grams' => 200,
        'unit_sticks' => 20,
        'sale_status' => ProductSku::STATUS_ACTIVE,
    ]
);
$emsSku = ProductSku::firstOrNew(['product_id' => $ems->id, 'title' => '默认']);
$emsSku->product_id = $ems->id;
$emsSku->description = 'regression';
$emsSku->price = 1000;
$emsSku->save();

$tax = Product::updateOrCreate(
    ['title' => '[回归测试] 含税烟'],
    [
        'description' => 'regression',
        'image' => 'products/regression-tax.jpg',
        'on_sale' => 1,
        'price' => 2000,
        'category_id' => $catTax->id,
        'shipping_mode' => ShippingModeService::MODE_TAX_INCLUDED,
        'tobacco_type' => OrderTobaccoLimitService::TYPE_HEATED_TOBACCO,
        'unit_weight_grams' => 180,
        'unit_sticks' => 20,
        'sale_status' => ProductSku::STATUS_ACTIVE,
    ]
);
$taxSku = ProductSku::firstOrNew(['product_id' => $tax->id, 'title' => '默认']);
$taxSku->product_id = $tax->id;
$taxSku->description = 'regression';
$taxSku->price = 2000;
$taxSku->save();

echo json_encode([
    'ems_product_id' => $ems->id,
    'ems_sku_id' => $emsSku->id,
    'tax_product_id' => $tax->id,
    'tax_sku_id' => $taxSku->id,
], JSON_PRETTY_PRINT)."\n";
