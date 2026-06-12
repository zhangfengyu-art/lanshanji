<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'title',
        'description',
        'image',
        'on_sale',
        'rating',
        'sold_count',
        'review_count',
        'price',
        'category_id',
        'sort_order',
        'shipping_mode',
        'tobacco_type',
        'unit_weight_grams',
        'unit_sticks',
        'sale_status',
        'purchase_limit',
    ];

    protected $casts = [
        'on_sale' => 'boolean',
        'purchase_limit' => 'integer',
        'unit_weight_grams' => 'integer',
        'unit_sticks' => 'integer',
    ];

    public static function saleStatusOptions()
    {
        return [
            ProductSku::STATUS_ACTIVE => '正常购买',
            ProductSku::STATUS_LIMITED => '限购',
            ProductSku::STATUS_DEPLETED => '售罄',
        ];
    }

    public static function tobaccoTypeOptions()
    {
        return \App\Services\OrderTobaccoLimitService::tobaccoTypeOptions();
    }

    public function getTobaccoTypeLabelAttribute()
    {
        return data_get(self::tobaccoTypeOptions(), $this->tobacco_type, '—');
    }

    public function isCigarette()
    {
        return $this->tobacco_type === \App\Services\OrderTobaccoLimitService::TYPE_CIGARETTE;
    }

    public function isHeatedTobacco()
    {
        return $this->tobacco_type === \App\Services\OrderTobaccoLimitService::TYPE_HEATED_TOBACCO;
    }

    public function countsTowardStickLimit()
    {
        return \App\Services\OrderTobaccoLimitService::countsTowardStickLimit($this->tobacco_type);
    }

    public function isRollingTobacco()
    {
        return $this->tobacco_type === \App\Services\OrderTobaccoLimitService::TYPE_ROLLING_TOBACCO;
    }

    public static function shippingModeOptions()
    {
        return \App\Services\ShippingModeService::options();
    }

    public function getShippingModeResolvedAttribute()
    {
        return app(\App\Services\ShippingModeService::class)->resolveForProduct($this);
    }

    public function getShippingModeLabelAttribute()
    {
        return app(\App\Services\ShippingModeService::class)->label($this->shipping_mode_resolved);
    }

    public function skus()
    {
        return $this->hasMany(ProductSku::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function isDepleted()
    {
        return $this->inventory_status === ProductSku::STATUS_DEPLETED;
    }

    public function isLimited()
    {
        return $this->inventory_status === ProductSku::STATUS_LIMITED;
    }

    public function getImageUrlAttribute()
    {
        if (Str::startsWith($this->attributes['image'], ['http://', 'https://'])) {
            return $this->attributes['image'];
        }

        return \Storage::disk('public')->url($this->attributes['image']);
    }

    public function getInventoryStatusAttribute()
    {
        $status = (string) ($this->attributes['sale_status'] ?? ProductSku::STATUS_ACTIVE);

        if (!array_key_exists($status, self::saleStatusOptions())) {
            return ProductSku::STATUS_ACTIVE;
        }

        return $status;
    }

    public function getLimitQtyAttribute()
    {
        if ($this->inventory_status !== ProductSku::STATUS_LIMITED) {
            return null;
        }

        $limit = (int) $this->purchase_limit;

        return $limit > 0 ? $limit : null;
    }

    public function getMaxPurchaseQty()
    {
        if ($this->isDepleted()) {
            return 0;
        }

        if ($this->isLimited()) {
            $limit = (int) $this->purchase_limit;

            return $limit > 0 ? $limit : 1;
        }

        return 999;
    }
}
