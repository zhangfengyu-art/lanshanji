<?php

namespace App\Services;

class ChinaAreaZipService
{
    /** @var array|null */
    protected static $data;

    public static function zipFromNames($province, $city, $district)
    {
        $province = trim((string) $province);
        $city = trim((string) $city);
        $district = trim((string) $district);

        if ($province === '' || $city === '' || $district === '') {
            return '';
        }

        $data = static::loadData();
        $provinces = $data['86'] ?? [];
        $provinceId = static::findKeyByName($provinces, $province);
        if (!$provinceId) {
            return '';
        }

        $cities = $data[$provinceId] ?? [];
        $cityId = static::findKeyByName($cities, $city);
        if (!$cityId) {
            return '';
        }

        $districts = $data[$cityId] ?? [];
        $districtId = static::findKeyByName($districts, $district);
        if (!$districtId) {
            return '';
        }

        return static::normalizeZip($districtId);
    }

    public static function normalizeZip($zip)
    {
        $zip = trim((string) $zip);
        if ($zip === '' || $zip === '0' || (int) $zip === 0) {
            return '';
        }

        return $zip;
    }

    protected static function findKeyByName(array $map, $name)
    {
        foreach ($map as $id => $label) {
            if ((string) $label === $name) {
                return $id;
            }
        }

        return null;
    }

    protected static function loadData()
    {
        if (static::$data !== null) {
            return static::$data;
        }

        $paths = [
            resource_path('data/china_area_v3.json'),
            base_path('node_modules/china-area-data/v3/data.json'),
        ];

        foreach ($paths as $path) {
            if (!is_readable($path)) {
                continue;
            }

            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded)) {
                static::$data = $decoded;

                return static::$data;
            }
        }

        static::$data = [];

        return static::$data;
    }
}
