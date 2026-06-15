<?php

use App\Services\ImageJpegConverter;
use App\Services\Ribenyan\RibenyanHttpClient;

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$exportDir = isset($argv[1]) ? $argv[1] : storage_path('app/ribenyan_export');
$csvPath = rtrim($exportDir, '/\\').'/products.csv';
$imagesDir = rtrim($exportDir, '/\\').'/images';

if (!is_file($csvPath)) {
    echo "未找到 CSV: {$csvPath}\n";
    exit(1);
}

if (!is_dir($imagesDir)) {
    mkdir($imagesDir, 0755, true);
}

$handle = fopen($csvPath, 'r');
$header = fgetcsv($handle);
if (!$header) {
    echo "CSV 为空\n";
    exit(1);
}

$header = array_map('trim', $header);
if (!in_array('image_url', $header, true)) {
    echo "CSV 缺少 image_url 列，请重新运行 php scripts/scrape_ribenyan.php 生成新 CSV。\n";
    exit(1);
}

$client = new RibenyanHttpClient();
$converter = new ImageJpegConverter();

$ok = 0;
$failed = 0;
$skipped = 0;
$rows = [];

while (($line = fgetcsv($handle)) !== false) {
    $row = [];
    foreach ($header as $i => $col) {
        $row[$col] = isset($line[$i]) ? trim((string) $line[$i]) : '';
    }
    $rows[] = $row;
}
fclose($handle);

foreach ($rows as $row) {
    $refId = $row['ref_id'] ?? '';
    $imageUrl = $row['image_url'] ?? '';
    $safeRef = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $refId);
    $targetPath = $imagesDir.'/'.$safeRef.'.jpg';

    if ($imageUrl === '' || $safeRef === '') {
        $failed++;
        continue;
    }

    if (is_file($targetPath) && filesize($targetPath) > 0) {
        $row['image_file'] = basename($targetPath);
        $skipped++;
        continue;
    }

    try {
        $binary = $client->getBinary($imageUrl);
        if ($binary === '') {
            throw new RuntimeException('图片为空');
        }

        $pathPart = (string) parse_url($imageUrl, PHP_URL_PATH);
        $ext = strtolower((string) pathinfo($pathPart, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            $ext = 'webp';
        }
        $tempPath = $imagesDir.'/'.$safeRef.'.'.$ext;
        file_put_contents($tempPath, $binary);

        if (!$converter->convertFileToJpeg($tempPath, $targetPath)) {
            @unlink($tempPath);
            throw new RuntimeException('转 JPG 失败');
        }

        @unlink($tempPath);
        $row['image_file'] = basename($targetPath);
        $ok++;
        echo '['.date('H:i:s').'] OK '.$refId."\n";
    } catch (Throwable $e) {
        $failed++;
        echo '['.date('H:i:s').'] FAIL '.$refId.' '.$e->getMessage()."\n";
    }
}

$outHandle = fopen($csvPath, 'w');
fprintf($outHandle, "\xEF\xBB\xBF");
fputcsv($outHandle, $header);
foreach ($rows as $row) {
    $line = [];
    foreach ($header as $col) {
        $line[] = isset($row[$col]) ? $row[$col] : '';
    }
    fputcsv($outHandle, $line);
}
fclose($outHandle);

echo "\n完成：成功 {$ok}，已存在跳过 {$skipped}，失败 {$failed}\n";
