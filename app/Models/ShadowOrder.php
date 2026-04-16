<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShadowOrder extends Model
{
    protected $fillable = [
        'shadow_no',
        'source_site',
        'source_order_no',
        'merchant_id',
        'amount_minor',
        'amount',
        'currency',
        'channel',
        'return_path',
        'status',
        'signature_hash',
        'paid_at',
        'meta',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'meta' => 'array',
    ];

    protected $dates = [
        'paid_at',
    ];
}
