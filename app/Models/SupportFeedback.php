<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportFeedback extends Model
{
    const STATUS_PENDING_REVIEW = 'PENDING_REVIEW';
    const STATUS_UNDER_INVESTIGATION = 'UNDER_INVESTIGATION';
    const STATUS_OFFICIALLY_RESOLVED = 'OFFICIALLY_RESOLVED';

    public static $statusMap = [
        self::STATUS_PENDING_REVIEW => '待审核',
        self::STATUS_UNDER_INVESTIGATION => '调查中',
        self::STATUS_OFFICIALLY_RESOLVED => '已结案',
    ];

    public static $questionTypeMap = [
        'ORDER_DELIVERY' => '订单/物流',
        'PAYMENT' => '支付问题',
        'AFTER_SALES' => '售后问题',
        'OTHER' => '其他咨询',
    ];

    protected $fillable = [
        'user_id',
        'order_no',
        'contact_name',
        'contact_phone',
        'question_type',
        'message',
        'images',
        'status',
        'admin_reply',
        'handled_by',
        'handled_at',
    ];

    protected $casts = [
        'images' => 'array',
    ];

    protected $dates = ['handled_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
