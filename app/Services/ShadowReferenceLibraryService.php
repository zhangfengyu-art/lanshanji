<?php

namespace App\Services;

use App\Models\ProcurementReferenceGallery;
use Illuminate\Support\Facades\Schema;

class ShadowReferenceLibraryService
{
    public function pickByAmount($amount)
    {
        $normalizedAmount = round((float) $amount, 2);
        if ($normalizedAmount <= 0) {
            return $this->defaultReferenceItem($normalizedAmount, 'invalid_amount');
        }

        if (!Schema::hasTable('procurement_reference_gallery')) {
            return $this->defaultReferenceItem($normalizedAmount, 'missing_table');
        }

        $lowerBound = round($normalizedAmount * 0.80, 2);
        $upperBound = round($normalizedAmount * 0.92, 2);

        $matched = ProcurementReferenceGallery::query()
            ->whereNotNull('reference_price')
            ->whereBetween('reference_price', [$lowerBound, $upperBound])
            ->inRandomOrder()
            ->first();

        if ($matched) {
            return $this->toPayload($matched, 'range_match', $normalizedAmount);
        }

        $closest = ProcurementReferenceGallery::query()
            ->whereNotNull('reference_price')
            ->orderByRaw('ABS(reference_price - ?) ASC', [$normalizedAmount])
            ->orderBy('id', 'asc')
            ->first();

        if ($closest) {
            return $this->toPayload($closest, 'closest_match', $normalizedAmount);
        }

        return $this->defaultReferenceItem($normalizedAmount, 'fallback_default');
    }

    protected function toPayload(ProcurementReferenceGallery $item, $strategy, $amount)
    {
        $referencePrice = round((float) $item->reference_price, 2);
        $gapAmount = round(max($amount - $referencePrice, 0), 2);
        $serviceFee = round($gapAmount * 0.35, 2);
        $shippingFee = round($gapAmount - $serviceFee, 2);

        return [
            'id' => (int) $item->id,
            'item_name' => (string) $item->item_name,
            'image_url' => (string) $item->image_url,
            'category_id' => $item->category_id ? (int) $item->category_id : null,
            'reference_price' => $referencePrice,
            'weight_estimate' => $item->weight_estimate !== null ? (float) $item->weight_estimate : null,
            'strategy' => $strategy,
            'narrative' => $this->buildNarrative($item->item_name, $amount, $strategy),
            'pricing' => [
                'total_amount' => $amount,
                'item_amount' => $referencePrice,
                'gap_amount' => $gapAmount,
                'service_fee' => $serviceFee,
                'shipping_fee' => $shippingFee,
                'gap_label' => '代购劳务费 + 国际快递费',
            ],
        ];
    }

    protected function defaultReferenceItem($amount, $strategy)
    {
        $itemName = '日本代购素材示例';

        return [
            'id' => null,
            'item_name' => $itemName,
            'image_url' => '/images/b_mode/proc-placeholder.svg',
            'category_id' => null,
            'reference_price' => null,
            'weight_estimate' => null,
            'strategy' => $strategy,
            'narrative' => $this->buildNarrative($itemName, $amount, $strategy),
            'pricing' => [
                'total_amount' => $amount,
                'item_amount' => null,
                'gap_amount' => null,
                'service_fee' => null,
                'shipping_fee' => null,
                'gap_label' => '代购劳务费 + 国际快递费',
            ],
        ];
    }

    protected function buildNarrative($itemName, $amount, $strategy)
    {
        $cityHints = [
            '大阪梅田专柜',
            '东京银座门店',
            '京都高岛屋',
            '新宿伊势丹',
            '心斋桥直营店',
            '横滨港未来商场',
        ];

        $remarks = [
            '麻烦帮我选保质期最新的',
            '一定要带原厂手提袋，送人的',
            '尽量保留原包装，不要拆封',
            '能给我留小票最好，谢谢',
            '包装要完整，麻烦拍照确认',
        ];

        $templates = [
            '求%s代购，预算大约%s，麻烦带原装小票和纸袋，急用。',
            '想找%s，预算%s左右，尽量保留包装和购物凭证。',
            '帮忙带一件%s，预算%s，优先日本本地现货。',
            '需要%s代购，预算%s，能在%s入手最好。',
        ];

        $template = $templates[array_rand($templates)];
        $city = $cityHints[array_rand($cityHints)];
        $remark = $remarks[array_rand($remarks)];

        if (strpos($template, '%s') !== false && substr_count($template, '%s') >= 3) {
            return sprintf($template, $itemName, number_format($amount, 2), $city) . ' ' . $remark . '。';
        }

        return sprintf($template, $itemName, number_format($amount, 2)) . ' ' . $remark . '。';
    }
}