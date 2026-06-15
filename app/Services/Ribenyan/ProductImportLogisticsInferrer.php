<?php

namespace App\Services\Ribenyan;

use App\Services\ProductWeightInferenceService;

class ProductImportLogisticsInferrer
{
    protected $weightInference;

    public function __construct(ProductWeightInferenceService $weightInference)
    {
        $this->weightInference = $weightInference;
    }

    public function infer($title, $subtitle, $tobaccoType)
    {
        $text = trim((string) $title.' '.(string) $subtitle);
        $sticks = null;

        if (\App\Services\OrderTobaccoLimitService::countsTowardStickLimit($tobaccoType)) {
            if (preg_match('/(\d+)\s*支/ui', $text, $m)) {
                $sticks = (int) $m[1];
            } else {
                $sticks = 20;
            }
        }

        $weight = $this->weightInference->inferUnitWeightGrams($title, $subtitle, $tobaccoType, $sticks);

        return [
            'unit_weight_grams' => max(1, $weight),
            'unit_sticks' => $sticks,
        ];
    }

    public function buildDescription($title, $subtitle, $extra = '')
    {
        $parts = array_filter([
            trim((string) $title),
            trim((string) $subtitle),
            trim((string) $extra),
        ]);

        $description = implode("\n", $parts);

        return $description !== '' ? $description : '日本代购商品';
    }
}
