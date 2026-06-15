<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductSku;
use App\Services\Ribenyan\RibenyanImportCategoryResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class ProductZipImportService
{
    protected $categoryResolver;
    protected $imageService;

    public function __construct(
        RibenyanImportCategoryResolver $categoryResolver,
        ProductImageUploadService $imageService
    ) {
        $this->categoryResolver = $categoryResolver;
        $this->imageService = $imageService;
    }

    public function importFromZip($zipAbsolutePath)
    {
        $tempRoot = storage_path('app/import_tmp/'.Str::random(16));
        File::makeDirectory($tempRoot, 0755, true, true);

        try {
            $zip = new ZipArchive();
            if ($zip->open($zipAbsolutePath) !== true) {
                throw new \RuntimeException('无法打开 ZIP 文件');
            }
            $zip->extractTo($tempRoot);
            $zip->close();

            $csvPath = $this->findCsvPath($tempRoot);
            if (!$csvPath) {
                throw new \RuntimeException('ZIP 内未找到 products.csv');
            }

            $imagesDir = $this->findImagesDir($tempRoot);
            $rows = $this->readCsv($csvPath);

            $created = 0;
            $updated = 0;
            $skipped = 0;
            $errors = [];

            foreach ($rows as $index => $row) {
                $line = $index + 2;
                try {
                    $result = $this->importRow($row, $imagesDir);
                    if ($result === 'created') {
                        $created++;
                    } elseif ($result === 'updated') {
                        $updated++;
                    } else {
                        $skipped++;
                    }
                } catch (\Throwable $e) {
                    $errors[] = '第 '.$line.' 行: '.$e->getMessage();
                }
            }

            return compact('created', 'updated', 'skipped', 'errors');
        } finally {
            File::deleteDirectory($tempRoot);
        }
    }

    protected function importRow(array $row, $imagesDir)
    {
        $refId = trim((string) data_get($row, 'ref_id', ''));
        $title = trim((string) data_get($row, 'title', ''));
        if ($title === '') {
            throw new \RuntimeException('缺少 title');
        }

        $price = (float) data_get($row, 'price', 0);
        if ($price <= 0) {
            throw new \RuntimeException('价格无效');
        }

        $product = null;
        if ($refId !== '' && db_has_column('products', 'source_ref_id')) {
            $product = Product::query()->where('source_ref_id', $refId)->first();
        }
        if (!$product) {
            $product = Product::query()->where('title', $title)->first();
        }

        $ftype = (int) data_get($row, 'ftype', 0);
        $categoryId = $this->categoryResolver->resolve(
            $ftype,
            data_get($row, 'category_brand', '')
        );

        $tobaccoType = trim((string) data_get($row, 'tobacco_type', 'cigarette'));
        if (!array_key_exists($tobaccoType, Product::tobaccoTypeOptions())) {
            $tobaccoType = data_get(config('ribenyan_import.ftype_tobacco_type'), $ftype, 'cigarette');
        }

        $unitWeight = max(1, (int) data_get($row, 'unit_weight_grams', 20));
        $unitSticks = data_get($row, 'unit_sticks', '');
        $unitSticks = $unitSticks === '' ? null : (int) $unitSticks;

        if (!OrderTobaccoLimitService::countsTowardStickLimit($tobaccoType)) {
            $unitSticks = null;
        } elseif ($unitSticks === null || $unitSticks < 1) {
            $unitSticks = 20;
        }

        $description = trim((string) data_get($row, 'description', ''));
        if ($description === '') {
            $description = trim((string) data_get($row, 'subtitle', ''));
        }
        if ($description === '') {
            $description = $title;
        }

        $onSale = (int) data_get($row, 'on_sale', 1) === 1;
        $saleStatus = trim((string) data_get($row, 'sale_status', ProductSku::STATUS_ACTIVE));
        if (!array_key_exists($saleStatus, Product::saleStatusOptions())) {
            $saleStatus = ProductSku::STATUS_ACTIVE;
        }

        $imagePath = '';
        $imageFile = trim((string) data_get($row, 'image_file', ''));
        if ($imageFile !== '' && $imagesDir) {
            $source = rtrim($imagesDir, '/\\').'/'.$imageFile;
            if (is_file($source)) {
                $imagePath = $this->storeImageFile($source, $refId !== '' ? $refId : $title);
            }
        }

        $payload = [
            'title' => $title,
            'description' => $description,
            'category_id' => $categoryId,
            'tobacco_type' => $tobaccoType,
            'unit_weight_grams' => $unitWeight,
            'unit_sticks' => $unitSticks,
            'on_sale' => $onSale,
            'sale_status' => $saleStatus,
            'purchase_limit' => null,
            'shipping_mode' => null,
            'price' => $price,
        ];

        if ($refId !== '' && db_has_column('products', 'source_ref_id')) {
            $payload['source_ref_id'] = $refId;
        }

        if ($imagePath !== '') {
            $payload['image'] = $imagePath;
        }

        $action = 'created';

        DB::transaction(function () use (&$product, &$action, $payload, $price, $row) {
            if ($product) {
                if (!isset($payload['image'])) {
                    unset($payload['image']);
                }
                $product->fill($payload)->save();
                $action = 'updated';
            } else {
                if (!isset($payload['image'])) {
                    throw new \RuntimeException('新建商品需要图片: '.data_get($row, 'image_file'));
                }
                $product = Product::query()->create($payload);
                $action = 'created';
            }

            $skuTitle = config('ribenyan_import.default_sku_title', '默认规格');
            $skuDescription = trim((string) data_get($row, 'subtitle', ''));
            if ($skuDescription === '') {
                $skuDescription = $skuTitle;
            }

            $sku = $product->skus()->first();
            if ($sku) {
                $sku->update([
                    'title' => $skuTitle,
                    'description' => $skuDescription,
                    'price' => $price,
                ]);
            } else {
                $product->skus()->create([
                    'title' => $skuTitle,
                    'description' => $skuDescription,
                    'price' => $price,
                    'stock' => 0,
                ]);
            }

            $product->forceFill(['price' => $price])->save();
        });

        if ($product && $product->image) {
            $this->imageService->normalizeStoredImageToJpeg($product);
        }

        return $action;
    }

    protected function storeImageFile($sourceAbsolutePath, $seed)
    {
        $disk = Storage::disk('public');
        $directory = trim(config('ribenyan_import.image_directory', 'images'), '/');
        $filename = 'import_'.preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $seed).'_'.Str::random(6).'.jpg';
        $relative = $directory.'/'.$filename;
        $targetAbsolute = $disk->path($relative);

        File::makeDirectory(dirname($targetAbsolute), 0755, true, true);

        if (!$this->convertFileToJpeg($sourceAbsolutePath, $targetAbsolute)) {
            throw new \RuntimeException('图片处理失败');
        }

        return $relative;
    }

    protected function convertFileToJpeg($sourcePath, $targetPath)
    {
        $type = @exif_imagetype($sourcePath);
        $image = null;

        switch ($type) {
            case IMAGETYPE_JPEG:
                $image = @imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $image = @imagecreatefrompng($sourcePath);
                break;
            case IMAGETYPE_GIF:
                $image = @imagecreatefromgif($sourcePath);
                break;
            case IMAGETYPE_WEBP:
                if (function_exists('imagecreatefromwebp')) {
                    $image = @imagecreatefromwebp($sourcePath);
                }
                break;
        }

        if (!$image) {
            return false;
        }

        if (function_exists('imagepalettetotruecolor')) {
            imagepalettetotruecolor($image);
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);
        imagedestroy($image);

        $saved = @imagejpeg($canvas, $targetPath, 90);
        imagedestroy($canvas);

        return $saved;
    }

    protected function findCsvPath($root)
    {
        $direct = $root.'/products.csv';
        if (is_file($direct)) {
            return $direct;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (strtolower($file->getFilename()) === 'products.csv') {
                return $file->getPathname();
            }
        }

        return null;
    }

    protected function findImagesDir($root)
    {
        $direct = $root.'/images';
        if (is_dir($direct)) {
            return $direct;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isDir() && strtolower($file->getFilename()) === 'images') {
                return $file->getPathname();
            }
        }

        return null;
    }

    protected function readCsv($csvPath)
    {
        $handle = fopen($csvPath, 'r');
        if (!$handle) {
            throw new \RuntimeException('无法读取 CSV');
        }

        $header = null;
        $rows = [];

        while (($line = fgetcsv($handle)) !== false) {
            if ($header === null) {
                $header = array_map(function ($col) {
                    return trim((string) $col);
                }, $line);
                continue;
            }

            if (count(array_filter($line, function ($v) {
                return trim((string) $v) !== '';
            })) === 0) {
                continue;
            }

            $row = [];
            foreach ($header as $i => $col) {
                $row[$col] = isset($line[$i]) ? trim((string) $line[$i]) : '';
            }
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }
}
