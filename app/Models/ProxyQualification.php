<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProxyQualification extends Model
{
    // 审核状态常量
    const STATUS_PENDING  = 0;
    const STATUS_APPROVED = 1;
    const STATUS_REJECTED = 2;

    public static $statusMap = [
        self::STATUS_PENDING  => '待审核',
        self::STATUS_APPROVED => '已通过',
        self::STATUS_REJECTED => '已拒绝',
    ];

    protected $table = 'proxy_qualifications';

    protected $fillable = [
        'user_id',
        'id_card_front',
        'id_card_back',
        'flight_ticket',
        'status',
        'reject_reason',
        'reviewed_by',
        'reviewed_at',
        'applicant_note',
    ];

    protected $dates = ['reviewed_at'];

    // ── 关联 ──────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ── 状态助手 ──────────────────────────────────────────

    public function isPending()
    {
        return (int) $this->status === self::STATUS_PENDING;
    }

    public function isApproved()
    {
        return (int) $this->status === self::STATUS_APPROVED;
    }

    public function isRejected()
    {
        return (int) $this->status === self::STATUS_REJECTED;
    }

    public function statusLabel()
    {
        return self::$statusMap[$this->status] ?? '未知';
    }
}
