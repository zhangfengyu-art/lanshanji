<?php

namespace App\Services;

use App\Exceptions\InvalidRequestException;

class EmsShippingFeeService
{
    public function tiers()
    {
        return config('ems_shipping.tiers', []);
    }

    public function maxBillableGrams()
    {
        return (int) config('ems_shipping.max_billable_grams', 16000);
    }

    public function feeForWeightGrams($totalGrams)
    {
        $grams = max(0, (int) ceil((float) $totalGrams));
        if ($grams <= 0) {
            throw new InvalidRequestException('订单计费重量无效，请检查商品重量设置。');
        }

        $maxGrams = $this->maxBillableGrams();
        if ($grams > $maxGrams) {
            throw new InvalidRequestException(
                '包裹计费重量已超过 EMS 第一区域上限（'.round($maxGrams / 1000, 2).'kg），请减少商品数量后重试。'
            );
        }

        foreach ($this->tiers() as $tier) {
            if ($grams <= (int) data_get($tier, 'max_grams', 0)) {
                return (float) data_get($tier, 'fee', 0);
            }
        }

        throw new InvalidRequestException('无法匹配 EMS 运费档位，请联系本站。');
    }
}
