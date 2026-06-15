<?php

namespace App\Services;

use App\Exceptions\InvalidRequestException;
use App\Models\ProductSku;

class OrderCheckoutQuoteService
{
    protected $tobaccoLimits;
    protected $emsShipping;
    protected $shippingModes;

    public function __construct(
        OrderTobaccoLimitService $tobaccoLimits,
        EmsShippingFeeService $emsShipping,
        ShippingModeService $shippingModes
    ) {
        $this->tobaccoLimits = $tobaccoLimits;
        $this->emsShipping = $emsShipping;
        $this->shippingModes = $shippingModes;
    }

    /**
     * @param array $items [['sku_id'=>int,'amount'=>int], ...]
     */
    public function quote(array $items)
    {
        $productsTotal = 0;

        foreach ($items as $row) {
            $sku = ProductSku::query()->find((int) data_get($row, 'sku_id'));
            if ($sku) {
                $productsTotal += ((float) $sku->price) * (int) data_get($row, 'amount', 0);
            }
        }

        $shippingMode = $this->shippingModes->resolvedModeFromItems($items);
        $tobaccoSummary = $this->tobaccoLimits->analyzeSkuItems($items);
        $this->tobaccoLimits->validateLimits($tobaccoSummary);

        $serviceFee = round($productsTotal * 0.13, 2);
        $packagingFee = 300.00;
        $emsFee = 0.0;
        $emsWeightGrams = null;

        if ($this->shippingModes->isTaxIncluded($shippingMode)) {
            $emsFee = 0;
        } else {
            $emsWeightGrams = (int) $tobaccoSummary['total_weight_grams'];
            $emsFee = $this->emsShipping->feeForWeightGrams($emsWeightGrams);
        }

        $payable = round($productsTotal + $serviceFee + $packagingFee + $emsFee, 2);
        $maxSticks = $this->tobaccoLimits->maxCigaretteSticks();
        $maxBoxes = $this->tobaccoLimits->maxCigaretteBoxes();
        $maxRolling = $this->tobaccoLimits->maxRollingTobaccoGrams();

        return [
            'valid' => true,
            'message' => '',
            'shipping_mode' => $shippingMode,
            'shipping_mode_label' => $this->shippingModes->label($shippingMode),
            'products_total' => round($productsTotal, 2),
            'service_fee' => $serviceFee,
            'packaging_fee' => $packagingFee,
            'ems_shipping_fee' => $emsFee,
            'payable' => $payable,
            'total_weight_grams' => (int) $tobaccoSummary['total_weight_grams'],
            'total_cigarette_sticks' => (int) $tobaccoSummary['total_cigarette_sticks'],
            'total_cigarette_boxes' => (int) $tobaccoSummary['total_cigarette_boxes'],
            'total_rolling_tobacco_grams' => (int) $tobaccoSummary['total_rolling_tobacco_grams'],
            'remaining_cigarette_sticks' => max(0, $maxSticks - (int) $tobaccoSummary['total_cigarette_sticks']),
            'remaining_cigarette_boxes' => max(0, $maxBoxes - (int) $tobaccoSummary['total_cigarette_boxes']),
            'remaining_rolling_tobacco_grams' => max(0, $maxRolling - (int) $tobaccoSummary['total_rolling_tobacco_grams']),
            'max_billable_grams' => $this->emsShipping->maxBillableGrams(),
            'tobacco_summary' => $tobaccoSummary,
        ];
    }

    public function quoteOrFail(array $items)
    {
        try {
            return $this->quote($items);
        } catch (InvalidRequestException $e) {
            $productsTotal = 0;
            foreach ($items as $row) {
                $sku = ProductSku::query()->find((int) data_get($row, 'sku_id'));
                if ($sku) {
                    $productsTotal += ((float) $sku->price) * (int) data_get($row, 'amount', 0);
                }
            }

            throw $e;
        }
    }
}
