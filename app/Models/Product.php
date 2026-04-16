<?php

namespace App\Models;
use Illuminate\Support\Str;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['title', 'description', 'image', 'on_sale', 'rating', 'sold_count', 'review_count', 'price', 'category_id', 'is_from_native_procurement', 'procurement_order_id'];
    protected $casts = [
        'on_sale' => 'boolean', // on_sale 是一个布尔类型的字段
    ];
    // 与商品SKU关联
    public function skus()
    {
        return $this->hasMany(ProductSku::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function getStockAttribute()
    {
        if ($this->relationLoaded('skus')) {
            return (int) $this->skus->sum('stock');
        }

        return (int) $this->skus()->sum('stock');
    }

    public function getImageUrlAttribute()
    {
        $image = trim((string) data_get($this->attributes, 'image', ''));

        if ($image === '') {
            return asset('images/brand-logo.svg');
        }

        // 如果 image 字段本身就已经是完整的 url 就直接返回
        if (Str::startsWith($image, ['http://', 'https://'])) {
            return $image;
        }
        return \Storage::disk('public')->url($image);
    }

    public function getInventoryStatusAttribute()
    {
        if ($this->stock <= 0) {
            return ProductSku::STATUS_DEPLETED;
        }

        $skus = $this->relationLoaded('skus')
            ? $this->skus
            : $this->skus()->get(['stock', 'limit_qty']);

        $hasLimited = $skus->contains(function ($sku) {
            return (int) $sku->stock > 0 && (int) $sku->limit_qty > 0;
        });

        return $hasLimited ? ProductSku::STATUS_LIMITED : ProductSku::STATUS_ACTIVE;
    }

    public function getLimitQtyAttribute()
    {
        $skus = $this->relationLoaded('skus')
            ? $this->skus
            : $this->skus()->get(['stock', 'limit_qty']);

        $qtyList = $skus->filter(function ($sku) {
            return (int) $sku->stock > 0 && (int) $sku->limit_qty > 0;
        })->pluck('limit_qty')->map(function ($qty) {
            return (int) $qty;
        });

        return $qtyList->isEmpty() ? null : $qtyList->min();
    }

    public function getMappedCategoryAttribute()
    {
        $seed = $this->category_id ?: $this->id;

        if (!is_site_mode_b()) {
            return optional($this->category)->name;
        }

        return b2b_fixed_category_name($seed);
    }

    public function getMappedCategoryPathAttribute()
    {
        if (!is_site_mode_b()) {
            $category = $this->category;
            $categoryParentName = optional(optional($category)->parent)->name;
            $categoryName = optional($category)->name;

            return trim(($categoryParentName ? $categoryParentName . ' ' : '') . ($categoryName ?: ''));
        }

        return $this->mapped_category;
    }
}
