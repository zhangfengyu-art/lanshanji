<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    const SITE_MODE_A = 'A';
    const SITE_MODE_B = 'B';

    protected $fillable = [
        'name',
        'is_directory',
        'parent_id',
        'site_mode',
    ];

    protected $casts = [
        'is_directory' => 'boolean',
    ];

    public static function siteModeOptions()
    {
        return [
            self::SITE_MODE_A => 'A站（香烟）',
            self::SITE_MODE_B => 'B站（代购）',
        ];
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function setParentIdAttribute($value)
    {
        $this->attributes['parent_id'] = (int) $value === 0 ? null : $value;
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeForSite($query, $siteMode)
    {
        $siteMode = strtoupper((string) $siteMode);
        $siteMode = $siteMode === self::SITE_MODE_B ? self::SITE_MODE_B : self::SITE_MODE_A;

        return $query->where('site_mode', $siteMode);
    }

    public function descendantIds()
    {
        $rows = self::query()
            ->forSite((string) $this->site_mode)
            ->get(['id', 'parent_id']);
        $childrenMap = [];

        foreach ($rows as $row) {
            $parentId = $row->parent_id ? (int) $row->parent_id : 0;
            if (!isset($childrenMap[$parentId])) {
                $childrenMap[$parentId] = [];
            }
            $childrenMap[$parentId][] = (int) $row->id;
        }

        $result = [];
        $stack = isset($childrenMap[(int) $this->id]) ? $childrenMap[(int) $this->id] : [];

        while (!empty($stack)) {
            $currentId = array_pop($stack);
            $result[] = $currentId;

            if (isset($childrenMap[$currentId])) {
                foreach ($childrenMap[$currentId] as $childId) {
                    $stack[] = $childId;
                }
            }
        }

        return array_values(array_unique($result));
    }

    public function selfAndDescendantIds()
    {
        return array_values(array_unique(array_merge([(int) $this->id], $this->descendantIds())));
    }

    public function aggregateProductsCount()
    {
        return Product::query()->whereIn('category_id', $this->selfAndDescendantIds())->count();
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function (Category $category) {
            if ($category->children()->exists()) {
                throw new \Exception('请先删除子分类后再删除当前分类');
            }
        });
    }
}
