<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

class HeatedTobaccoClassificationService
{
    public function config($key, $default = [])
    {
        return config('heated_tobacco_classification.'.$key, $default);
    }

    public function matchesPattern($text, array $patterns)
    {
        $text = (string) $text;
        if ($text === '') {
            return false;
        }

        foreach ($patterns as $pattern) {
            if ($pattern === '') {
                continue;
            }
            if (mb_stripos($text, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    public function isExcludedTitle($title)
    {
        return $this->matchesPattern($title, $this->config('title_exclude_patterns', []));
    }

    public function isHeatedCategoryName($name)
    {
        return $this->matchesPattern($name, $this->config('category_name_patterns', []));
    }

    public function isHeatedProductTitle($title)
    {
        if ($this->isExcludedTitle($title)) {
            return false;
        }

        return $this->matchesPattern($title, $this->config('title_include_patterns', []));
    }

  /**
   * @return array{match:bool,reason:string}
   */
    public function classifyProduct(Product $product)
    {
        $product->loadMissing('category');

        $title = (string) $product->title;
        $categoryName = (string) optional($product->category)->name;

        if ($this->isExcludedTitle($title)) {
            return ['match' => false, 'reason' => '标题含设备/配件排除词'];
        }

        if ($categoryName !== '' && $this->isHeatedCategoryName($categoryName)) {
            return ['match' => true, 'reason' => '分类名匹配加热烟：'.$categoryName];
        }

        if ($this->isHeatedProductTitle($title)) {
            return ['match' => true, 'reason' => '商品标题匹配加热烟'];
        }

        return ['match' => false, 'reason' => '未匹配加热烟规则'];
    }

    public function suggestedTobaccoType(Product $product)
    {
        $result = $this->classifyProduct($product);

        return $result['match']
            ? OrderTobaccoLimitService::TYPE_HEATED_TOBACCO
            : null;
    }

    /**
     * @return \Illuminate\Support\Collection|Product[]
     */
    public function scanProducts($onlyUnset = false, $includeCigarette = false)
    {
        $query = Product::query()->with('category');

        if ($onlyUnset) {
            $query->where(function ($q) {
                $q->whereNull('tobacco_type')
                    ->orWhere('tobacco_type', '');
            });
        } elseif ($includeCigarette) {
            $query->where(function ($q) {
                $q->whereNull('tobacco_type')
                    ->orWhere('tobacco_type', '')
                    ->orWhere('tobacco_type', OrderTobaccoLimitService::TYPE_CIGARETTE);
            });
        }

        return $query->get()->filter(function (Product $product) use ($includeCigarette) {
            $classification = $this->classifyProduct($product);
            if (!$classification['match']) {
                return false;
            }

            if ($product->tobacco_type === OrderTobaccoLimitService::TYPE_HEATED_TOBACCO) {
                return false;
            }

            if ($product->tobacco_type === OrderTobaccoLimitService::TYPE_CIGARETTE && !$includeCigarette) {
                return false;
            }

            if (in_array($product->tobacco_type, [
                OrderTobaccoLimitService::TYPE_ROLLING_TOBACCO,
                OrderTobaccoLimitService::TYPE_NON_TOBACCO,
            ], true)) {
                return false;
            }

            return true;
        });
    }

    public function applyToProduct(Product $product, $fillDefaultSticks = true)
    {
        $classification = $this->classifyProduct($product);
        if (!$classification['match']) {
            return false;
        }

        $payload = [
            'tobacco_type' => OrderTobaccoLimitService::TYPE_HEATED_TOBACCO,
        ];

        if ($fillDefaultSticks && (int) $product->unit_sticks < 1) {
            $payload['unit_sticks'] = (int) $this->config('default_unit_sticks', 20);
        }

        $product->update($payload);

        return true;
    }
}
