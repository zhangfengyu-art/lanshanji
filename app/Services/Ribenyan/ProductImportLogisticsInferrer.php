<?php

namespace App\Services\Ribenyan;

class ProductImportLogisticsInferrer
{
    public function infer($title, $subtitle, $tobaccoType)
    {
        $text = trim((string) $title.' '.(string) $subtitle);
        $weight = 20;
        $sticks = null;

        if (preg_match('/(\d+)\s*g装/ui', $text, $m)) {
            $weight = (int) $m[1];
        } elseif (preg_match('/(\d+)\s*g(?:\s|装|$)/ui', $text, $m)) {
            $weight = (int) $m[1];
        }

        if (\App\Services\OrderTobaccoLimitService::countsTowardStickLimit($tobaccoType)) {
            if (preg_match('/(\d+)\s*支/ui', $text, $m)) {
                $sticks = (int) $m[1];
            } else {
                $sticks = 20;
            }
        }

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
