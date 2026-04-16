<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Exceptions\InternalException;

class ProductSku extends Model
{
    const STATUS_ACTIVE = 'ACTIVE';
    const STATUS_LIMITED = 'LIMITED';
    const STATUS_DEPLETED = 'DEPLETED';

    protected $fillable = ['title', 'description', 'price', 'stock', 'limit_qty', 'item_type', 'unit_sticks', 'unit_weight'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function decreaseStock($amount)
    {
        if ($amount < 0) {
            throw new InternalException('减库存不可小于0');
        }

        return $this->newQuery()->where('id', $this->id)->where('stock', '>=', $amount)->decrement('stock', $amount);
    }

    public function addStock($amount)
    {
        if ($amount < 0) {
            throw new InternalException('加库存不可小于0');
        }
        $this->increment('stock', $amount);
    }

    public function getOrderMaxQty()
    {
        // 使用 limit_qty 作为单笔订单最大数量，如果为 0 或 null 则不限制
        $limitQty = (int) $this->limit_qty;
        return $limitQty > 0 ? $limitQty : $this->stock;
    }
}
