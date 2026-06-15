<?php

use App\Services\ImageJpegConverter;
use App\Services\Ribenyan\RibenyanHttpClient;
use App\Services\Ribenyan\RibenyanProductListParser;
use App\Services\Ribenyan\RibenyanScraper;
use App\Services\Ribenyan\ProductImportLogisticsInferrer;

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$outputDir = isset($argv[1]) ? $argv[1] : storage_path('app/ribenyan_export');

if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

$scraper = new RibenyanScraper(
    new RibenyanHttpClient(),
    new RibenyanProductListParser(),
    new ProductImportLogisticsInferrer(),
    new ImageJpegConverter()
);

echo "输出目录: {$outputDir}\n";
echo "开始抓取 ribenyan.com（日本香烟/外国香烟/加热烟草/手卷烟丝/烟斗烟丝/其他烟丝）...\n";

$result = $scraper->scrapeToDirectory($outputDir, function ($message) {
    echo '['.date('H:i:s').'] '.$message."\n";
});

echo "\n完成。\n";
echo '商品数: '.$result['product_count']."\n";
echo 'CSV: '.$result['csv_path']."\n";
echo '图片目录: '.$result['images_dir']."\n";

if (!empty($result['errors'])) {
    echo "\n部分错误（".count($result['errors'])." 条）:\n";
    foreach (array_slice($result['errors'], 0, 20) as $error) {
        echo ' - '.$error."\n";
    }
    if (count($result['errors']) > 20) {
        echo ' ... 更多错误已省略'."\n";
    }
}

echo "\n请将 {$outputDir} 下的 products.csv 与 images/ 文件夹打成 ZIP，在后台「ZIP 导入商品」上传。\n";
