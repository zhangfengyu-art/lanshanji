<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserOperationAudit extends Model
{
    protected $fillable = [
        'admin_user_id',
        'target_user_id',
        'action',
        'detail',
        'ip',
        'user_agent',
    ];

    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
