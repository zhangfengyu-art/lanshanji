<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourierApplication extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    public static $statusMap = [
        self::STATUS_PENDING => '待审核',
        self::STATUS_APPROVED => '已通过',
        self::STATUS_REJECTED => '已拒绝',
    ];

    protected $fillable = [
        'user_id',
        'real_name',
        'phone',
        'id_card_number',
        'flight_ticket_path',
        'id_card_photo_path',
        'status',
        'admin_note',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
