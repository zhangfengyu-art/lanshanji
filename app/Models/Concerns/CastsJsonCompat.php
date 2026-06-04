<?php

namespace App\Models\Concerns;

/**
 * 兼容 JSON 字段在 attributes 中已是数组的情况（如 laravel-admin Grid 行数据）。
 */
trait CastsJsonCompat
{
    protected function castAttribute($key, $value)
    {
        if (is_array($value) && isset($this->casts[$key])) {
            $cast = $this->casts[$key];
            if (in_array($cast, ['array', 'json'], true)) {
                return $value;
            }
            if ($cast === 'object') {
                return (object) $value;
            }
        }

        return parent::castAttribute($key, $value);
    }
}
