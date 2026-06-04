<?php

namespace App\Models;

use App\Models\Concerns\CastsJsonCompat;
use Illuminate\Database\Eloquent\Model;

class SupportFeedback extends Model
{
    use CastsJsonCompat;
    const STATUS_PENDING = 'pending';
    const STATUS_HANDLED = 'handled';

    /** 同一用户两次提交最短间隔（秒） */
    const SUBMIT_MIN_INTERVAL_SECONDS = 120;

    /** 同一用户每日最多提交条数 */
    const SUBMIT_DAILY_MAX = 10;

    protected $table = 'support_feedbacks';

    public static function questionTypeOptions()
    {
        return [
            'order' => '订单问题',
            'payment' => '支付问题',
            'shipping' => '物流/发货',
            'refund' => '退款售后',
            'product' => '商品咨询',
            'account' => '账号问题',
            'other' => '其他',
        ];
    }

    public function getQuestionTypeLabelAttribute()
    {
        return self::questionTypeOptions()[$this->question_type] ?? (string) $this->question_type;
    }

    public function getStatusLabelAttribute()
    {
        return $this->status === self::STATUS_HANDLED ? '已回复' : '待处理';
    }

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
        'handled_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 检查用户是否可提交新反馈，不可提交时返回错误文案。
     *
     * @param int $userId
     * @return string|null
     */
    public static function submissionBlockedMessage($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return '请先登录后再提交反馈';
        }

        $last = static::query()
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($last && $last->created_at) {
            $elapsed = $last->created_at->diffInSeconds(now());
            if ($elapsed < self::SUBMIT_MIN_INTERVAL_SECONDS) {
                $wait = self::SUBMIT_MIN_INTERVAL_SECONDS - $elapsed;

                return '提交过于频繁，请 '.max(1, (int) ceil($wait / 60)).' 分钟后再试';
            }
        }

        $todayCount = static::query()
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        if ($todayCount >= self::SUBMIT_DAILY_MAX) {
            return '今日反馈次数已达上限（'.self::SUBMIT_DAILY_MAX.' 条），请明日再试';
        }

        return null;
    }
}
