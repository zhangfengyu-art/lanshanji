<?php

use App\Services\ProductZipImportService;

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$zipPath = isset($argv[1]) ? $argv[1] : '/tmp/ribenyan_import.zip';

if (!is_file($zipPath)) {
    echo "找不到 ZIP 文件: {$zipPath}\n";
    echo "用法: php scripts/import_products_zip.php /path/to/import.zip\n";
    exit(1);
}

echo "开始导入: {$zipPath}\n";
echo '文件大小: '.round(filesize($zipPath) / 1024 / 1024, 2).' MB'."\n";

$result = app(ProductZipImportService::class)->importFromZip($zipPath);

echo "\n导入完成。\n";
echo '新建: '.$result['created']."\n";
echo '更新: '.$result['updated']."\n";
echo '跳过: '.$result['skipped']."\n";

if (!empty($result['errors'])) {
    echo '失败: '.count($result['errors'])." 条\n";
    foreach (array_slice($result['errors'], 0, 10) as $error) {
        echo ' - '.$error."\n";
    }
    if (count($result['errors']) > 10) {
        echo ' ... 更多错误已省略'."\n";
    }
}
