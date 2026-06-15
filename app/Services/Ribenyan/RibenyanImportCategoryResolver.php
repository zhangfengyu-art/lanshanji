<?php

namespace App\Services\Ribenyan;

use App\Models\Category;
use App\Services\ShippingModeService;

class RibenyanImportCategoryResolver
{
    public function resolve($ftype, $brandName)
    {
        $rootName = data_get(config('ribenyan_import.ftype_roots'), (int) $ftype);
        if (!$rootName) {
            throw new \InvalidArgumentException('未知 ftype: '.$ftype);
        }

        $root = $this->findOrCreateRootCategory($rootName);
        $brandName = trim((string) $brandName);

        if ($brandName === '') {
            return $root->id;
        }

        $childName = $this->formatBrandCategoryName($brandName);

        $child = Category::query()
            ->where('parent_id', $root->id)
            ->where(function ($query) use ($childName) {
                $query->where('name', $childName)
                    ->orWhere('name', $childName.' EMS直邮');
            })
            ->first();

        if ($child) {
            return $child->id;
        }

        $child = Category::query()->create([
            'name' => $childName,
            'parent_id' => $root->id,
            'is_directory' => false,
            'sort_order' => 0,
            'default_shipping_mode' => ShippingModeService::MODE_EMS,
        ]);

        return $child->id;
    }

    protected function findOrCreateRootCategory($name)
    {
        $category = Category::query()
            ->whereNull('parent_id')
            ->where('name', $name)
            ->first();

        if ($category) {
            return $category;
        }

        return Category::query()->create([
            'name' => $name,
            'parent_id' => null,
            'is_directory' => true,
            'sort_order' => 0,
            'default_shipping_mode' => ShippingModeService::MODE_EMS,
        ]);
    }

    protected function formatBrandCategoryName($brand)
    {
        return preg_replace('/\s*\/\s*/', ' / ', trim($brand));
    }
}
