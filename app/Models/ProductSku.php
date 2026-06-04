<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Exceptions\InternalException;

class ProductSku extends Model
{
    const STATUS_ACTIVE = 'ACTIVE';
    const STATUS_LIMITED = 'LIMITED';
    const STATUS_DEPLETED = 'DEPLETED';

    /** 正常购买时单笔可购上限（不扣库存，仅防滥用） */
    const UNLIMITED_ORDER_MAX = 999;

    protected $fillable = ['title', 'description', 'price', 'stock', 'limit_qty'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (ProductSku $sku) {
            if (!isset($sku->attributes['stock'])) {
                $sku->stock = 0;
            }
        });
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function isDepleted()
    {
        $product = $this->product;

        return $product ? $product->isDepleted() : false;
    }

    public function decreaseStock($amount)
    {
        // 代购模式不维护实物库存，下单不扣减
        return 1;
    }

    public function addStock($amount)
    {
        // 保留方法以兼容旧代码，不再使用
    }

    public function getOrderMaxQty()
    {
        $product = $this->product;
        if (!$product) {
            return 0;
        }

        return $product->getMaxPurchaseQty();
    }
}
