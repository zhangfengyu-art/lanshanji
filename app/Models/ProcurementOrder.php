<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcurementOrder extends Model
{
    const STATUS_PENDING = 0;
    const STATUS_ACCEPTED = 1;
    const STATUS_SOURCING = 2;
    const STATUS_SHIPPED = 3;

    const REVIEW_STATUS_PENDING = 0;
    const REVIEW_STATUS_APPROVED = 1;
    const REVIEW_STATUS_REJECTED = 2;

    public static $statusMap = [
        self::STATUS_PENDING => '等待接单',
        self::STATUS_ACCEPTED => '已接单',
        self::STATUS_SOURCING => '采购中',
        self::STATUS_SHIPPED => '已发货',
    ];

    public static $reviewStatusMap = [
        self::REVIEW_STATUS_PENDING => '待审核',
        self::REVIEW_STATUS_APPROVED => '已通过',
        self::REVIEW_STATUS_REJECTED => '已拒绝',
    ];

    protected $fillable = [
        'no',
        'order_no',
        'user_id',
        'accepted_by',
        'accepted_at',
        'item_name',
        'item_image',
        'buyer_nickname',
        'proxy_status',
        'order_narrative',
        'budget_amount',
        'extra',
        'review_status',
        'reviewed_by',
        'reviewed_at',
        'review_comment',
    ];

    protected $casts = [
        'proxy_status' => 'integer',
        'budget_amount' => 'float',
        'extra' => 'json',
        'accepted_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->no) {
                $model->no = static::findAvailableNo();
                if (!$model->no) {
                    return false;
                }
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'id', 'procurement_order_id');
    }

    public static function findAvailableNo()
    {
        $prefix = 'PO' . date('YmdHis');

        for ($i = 0; $i < 10; $i++) {
            $no = $prefix . str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            if (!static::query()->where('no', $no)->exists()) {
                return $no;
            }
            usleep(100);
        }

        \Log::warning('find procurement order no failed');

        return false;
    }
}
