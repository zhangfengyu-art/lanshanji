<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcurementReferenceItem extends Model
{
    protected $fillable = [
        'name',
        'category',
        'reference_price',
        'image_url',
    ];

    protected $casts = [
        'reference_price' => 'float',
    ];
}
