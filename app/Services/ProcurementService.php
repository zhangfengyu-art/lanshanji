<?php

namespace App\Services;

use App\Models\ProcurementOrder;
use App\Services\ShadowReferenceLibraryService;
use InvalidArgumentException;

class ProcurementService
{
    /** @var ShadowReferenceLibraryService */
    protected $shadowReferenceLibraryService;

    public function __construct(ShadowReferenceLibraryService $shadowReferenceLibraryService)
    {
        $this->shadowReferenceLibraryService = $shadowReferenceLibraryService;
    }

    /**
     * 从结算金额生成一条 C2C 代购需求单。
     *
     * @param float $amount
     * @return ProcurementOrder
     */
    public function createFromSettlement(float $amount)
    {
        $normalizedAmount = round($amount, 2);
        if ($normalizedAmount <= 0) {
            throw new InvalidArgumentException('Settlement amount must be greater than zero.');
        }

        $item = $this->shadowReferenceLibraryService->pickByAmount($normalizedAmount);
        $nickname = $this->pickRandomNickname();
        $narrative = (string) data_get($item, 'narrative', '');
        if ($narrative === '') {
            $narrative = $this->buildNarrative((string) data_get($item, 'item_name', '日本代购素材示例'), $normalizedAmount);
        }

        $itemName = (string) data_get($item, 'item_name', '日本代购素材示例');
        $itemImage = (string) data_get($item, 'image_url', '/images/procurement/default-item.jpg');
        $categoryId = data_get($item, 'category_id');
        $referencePrice = data_get($item, 'reference_price');

        return ProcurementOrder::query()->create([
            'item_name' => $itemName,
            'item_image' => $itemImage,
            'buyer_nickname' => $nickname,
            'proxy_status' => ProcurementOrder::STATUS_PENDING,
            'order_narrative' => $narrative,
            'budget_amount' => $normalizedAmount,
            'extra' => [
                'source' => 'settlement',
                'category' => (string) data_get($item, 'category.name', 'general'),
                'reference_item_id' => data_get($item, 'id'),
                'reference_price' => $referencePrice,
                'reference_strategy' => data_get($item, 'strategy'),
                'reference_snapshot' => [
                    'item_name' => $itemName,
                    'image_url' => $itemImage,
                    'reference_price' => $referencePrice,
                    'category_id' => $categoryId,
                    'weight_estimate' => data_get($item, 'weight_estimate'),
                    'narrative' => $narrative,
                ],
                'pricing_snapshot' => data_get($item, 'pricing', []),
                'seed_amount' => $normalizedAmount,
            ],
        ]);
    }

    protected function pickRandomNickname()
    {
        $pool = [
            '小张在大阪',
            '抹茶控',
            '浅草散步者',
            '东京夜猫子',
            '神户买手',
            '北海道小队',
            '奈良鹿友',
            '冲绳海风',
            '秋叶原手办党',
            '京都慢生活',
            '代官山通勤人',
            '名古屋日常',
            '福冈甜品脑袋',
            '札幌雪人',
            '湘南海边',
        ];

        return $pool[array_rand($pool)];
    }

    protected function buildNarrative($itemName, $amount)
    {
        $templates = [
            '想求购%s，预算大约%s，要求正品可提供小票。',
            '求代购%s，预算%s左右，近期可发货优先。',
            '帮忙带一件%s，预算%s，包装完整优先。',
            '寻找%s，预算%s，支持同城面交或直邮。',
        ];

        $template = $templates[array_rand($templates)];
        return sprintf($template, $itemName, number_format($amount, 2));
    }
}
