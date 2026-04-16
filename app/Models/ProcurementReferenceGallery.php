<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcurementReferenceGallery extends Model
{
    protected $table = 'procurement_reference_gallery';

    protected $fillable = [
        'item_name',
        'reference_price',
        'category_id',
        'image_url',
        'weight_estimate',
    ];

    protected $casts = [
        'reference_price' => 'float',
        'category_id' => 'integer',
        'weight_estimate' => 'float',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}