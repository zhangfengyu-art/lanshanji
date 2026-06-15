<?php

namespace App\Services\Ribenyan;

use Illuminate\Support\Facades\File;

class RibenyanScraper
{
    protected $client;
    protected $parser;
    protected $logisticsInferrer;
    protected $seenRefIds = [];

    public function __construct(
        RibenyanHttpClient $client,
        RibenyanProductListParser $parser,
        ProductImportLogisticsInferrer $logisticsInferrer
    ) {
        $this->client = $client;
        $this->parser = $parser;
        $this->logisticsInferrer = $logisticsInferrer;
    }

    public function scrapeToDirectory($outputDir, $progressCallback = null)
    {
        $outputDir = rtrim($outputDir, '/\\');
        $imagesDir = $outputDir.'/images';

        File::makeDirectory($imagesDir, 0755, true, true);

        $listUrls = $this->discoverListUrls();
        $allRows = [];
        $errors = [];

        foreach ($listUrls as $listMeta) {
            $this->report($progressCallback, '抓取列表: '.$listMeta['url']);

            try {
                $html = $this->client->get($listMeta['url']);
                $brandName = $listMeta['brand'];
                if ($brandName === '') {
                    $brandName = $this->extractBrandNameFromListPage($html);
                }
                $items = $this->parser->parseListPage(
                    $html,
                    $listMeta['ftype'],
                    $brandName,
                    $listMeta['parent']
                );

                foreach ($items as $item) {
                    $refId = (string) $item['ref_id'];
                    if (isset($this->seenRefIds[$refId])) {
                        continue;
                    }
                    $this->seenRefIds[$refId] = true;

                    $tobaccoType = data_get(
                        config('ribenyan_import.ftype_tobacco_type'),
                        (int) $item['ftype'],
                        'cigarette'
                    );
                    $logistics = $this->logisticsInferrer->infer(
                        $item['title'],
                        $item['subtitle'],
                        $tobaccoType
                    );

                    $imageFile = '';
                    if (!empty($item['image_url'])) {
                        $imageFile = $this->downloadImage($item['image_url'], $refId, $imagesDir, $errors);
                    }

                    $allRows[] = [
                        'ref_id' => $refId,
                        'goods_id' => $item['goods_id'],
                        'title' => $item['title'],
                        'subtitle' => $item['subtitle'],
                        'price' => $item['price'],
                        'image_file' => $imageFile,
                        'category_parent' => $item['category_parent'],
                        'category_brand' => $item['category_brand'],
                        'ftype' => $item['ftype'],
                        'tobacco_type' => $tobaccoType,
                        'unit_weight_grams' => $logistics['unit_weight_grams'],
                        'unit_sticks' => $logistics['unit_sticks'] ?? '',
                        'on_sale' => 1,
                        'sale_status' => 'ACTIVE',
                        'description' => $this->logisticsInferrer->buildDescription(
                            $item['title'],
                            $item['subtitle'],
                            $item['extra_notes']
                        ),
                    ];
                }
            } catch (\Throwable $e) {
                $errors[] = $listMeta['url'].' => '.$e->getMessage();
            }
        }

        $csvPath = $outputDir.'/products.csv';
        $this->writeCsv($csvPath, $allRows);

        return [
            'csv_path' => $csvPath,
            'images_dir' => $imagesDir,
            'product_count' => count($allRows),
            'errors' => $errors,
        ];
    }

    protected function discoverListUrls()
    {
        $html = $this->client->get(config('ribenyan_import.base_url').'/index.php');
        $allowed = config('ribenyan_import.allowed_ftypes', []);
        $roots = config('ribenyan_import.ftype_roots', []);

        $urls = [];
        $seenUrls = [];
        if (!preg_match_all(
            '/href="index\.php\?m=goods&a=list&brand=(\d+)&goods_type=(\d+)&ftype=(\d+)"/u',
            $html,
            $matches,
            PREG_SET_ORDER
        )) {
            return $urls;
        }

        foreach ($matches as $match) {
            $ftype = (int) $match[3];
            if (!in_array($ftype, $allowed, true)) {
                continue;
            }

            $brandId = (int) $match[1];
            $goodsType = (int) $match[2];
            $relativeUrl = 'index.php?m=goods&a=list&brand='.$brandId
                .'&goods_type='.$goodsType.'&ftype='.$ftype;

            if (isset($seenUrls[$relativeUrl])) {
                continue;
            }
            $seenUrls[$relativeUrl] = true;

            $brandName = $this->extractBrandName($html, $relativeUrl);
            $parentName = data_get($roots, $ftype, '');

            $urls[] = [
                'url' => config('ribenyan_import.base_url').'/'.$relativeUrl,
                'ftype' => $ftype,
                'brand' => $brandName,
                'parent' => $parentName,
            ];
        }

        return $urls;
    }

    protected function extractBrandName($homepageHtml, $relativeUrl)
    {
        $escaped = preg_quote($relativeUrl, '/');
        if (preg_match('/href="'.$escaped.'"[^>]*>([^<]+)</u', $homepageHtml, $match)) {
            return trim(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'));
        }

        return '';
    }

    protected function extractBrandNameFromListPage($html)
    {
        if (preg_match_all('/<li class="breadcrumb-item small">([^<]+)</u', $html, $matches) && count($matches[1]) >= 2) {
            return trim(html_entity_decode($matches[1][1], ENT_QUOTES, 'UTF-8'));
        }

        return '';
    }

    protected function downloadImage($imageUrl, $refId, $imagesDir, &$errors)
    {
        $safeRef = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $refId);
        $targetPath = $imagesDir.'/'.$safeRef.'.jpg';

        try {
            $binary = $this->client->getBinary($imageUrl);
            if ($binary === '') {
                throw new \RuntimeException('图片为空');
            }

            $tempPath = $targetPath.'.tmp';
            file_put_contents($tempPath, $binary);

            if ($this->convertToJpeg($tempPath, $targetPath)) {
                @unlink($tempPath);

                return basename($targetPath);
            }

            @unlink($tempPath);
            throw new \RuntimeException('图片转 JPG 失败');
        } catch (\Throwable $e) {
            $errors[] = $refId.' 图片下载失败: '.$e->getMessage();

            return '';
        }
    }

    protected function convertToJpeg($sourcePath, $targetPath)
    {
        $image = null;
        $type = @exif_imagetype($sourcePath);

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

    protected function writeCsv($csvPath, array $rows)
    {
        $headers = [
            'ref_id',
            'goods_id',
            'title',
            'subtitle',
            'price',
            'image_file',
            'category_parent',
            'category_brand',
            'ftype',
            'tobacco_type',
            'unit_weight_grams',
            'unit_sticks',
            'on_sale',
            'sale_status',
            'description',
        ];

        $handle = fopen($csvPath, 'w');
        fprintf($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $header) {
                $line[] = isset($row[$header]) ? $row[$header] : '';
            }
            fputcsv($handle, $line);
        }

        fclose($handle);
    }

    protected function report($callback, $message)
    {
        if (is_callable($callback)) {
            $callback($message);
        }
    }
}
