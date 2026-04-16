<?php

namespace App\Services;

use App\Exceptions\InvalidRequestException;
use App\Models\ProductSku;

class ShippingFeeService
{
    const DEFAULT_SKU_WEIGHT_GRAMS = 30;
    const MAX_WEIGHT_GRAMS = 3000;

    /**
     * EMS 阶梯价（单位：日元）
     */
    const EMS_WEIGHT_FEE_TIERS = [
        ['max' => 500, 'fee' => 1450],
        ['max' => 600, 'fee' => 1600],
        ['max' => 700, 'fee' => 1750],
        ['max' => 800, 'fee' => 1900],
        ['max' => 900, 'fee' => 2050],
        ['max' => 1000, 'fee' => 2200],
        ['max' => 1250, 'fee' => 2500],
        ['max' => 1500, 'fee' => 2800],
        ['max' => 1750, 'fee' => 3100],
        ['max' => 2000, 'fee' => 3400],
        ['max' => 2500, 'fee' => 3900],
        ['max' => 3000, 'fee' => 4400],
    ];

    public function resolveSkuWeightGrams(ProductSku $sku)
    {
        $weight = (int) data_get($sku, 'shipping_weight_grams', 0);

        return $weight > 0 ? $weight : self::DEFAULT_SKU_WEIGHT_GRAMS;
    }

    public function calculateByWeight($totalWeightGrams)
    {
        $weight = max(0, (int) $totalWeightGrams);
        if ($weight === 0) {
            return [
                'weight_grams' => 0,
                'fee' => 0,
                'tier_upper_bound' => 0,
                'exceeded' => false,
                'reason' => null,
            ];
        }

        foreach (self::EMS_WEIGHT_FEE_TIERS as $tier) {
            if ($weight <= (int) $tier['max']) {
                return [
                    'weight_grams' => $weight,
                    'fee' => (int) $tier['fee'],
                    'tier_upper_bound' => (int) $tier['max'],
                    'exceeded' => false,
                    'reason' => null,
                ];
            }
        }

        return [
            'weight_grams' => $weight,
            'fee' => 0,
            'tier_upper_bound' => self::MAX_WEIGHT_GRAMS,
            'exceeded' => true,
            'reason' => '当前包裹总克重超过 3000g，请分拆下单。',
        ];
    }

    public function ensureWithinLimit($totalWeightGrams)
    {
        $result = $this->calculateByWeight($totalWeightGrams);
        if ($result['exceeded']) {
            throw new InvalidRequestException($result['reason']);
        }

        return $result;
    }
}
