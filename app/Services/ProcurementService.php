<?php

namespace App\Services;

use App\Models\ProcurementOrder;
use App\Models\ProcurementReferenceItem;
use InvalidArgumentException;
use Illuminate\Support\Facades\Schema;

class ProcurementService
{
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

        $item = $this->pickRandomItem();
        $nickname = $this->pickRandomNickname();
        $narrative = $this->buildNarrative($item['name'], $normalizedAmount);

        return ProcurementOrder::query()->create([
            'item_name' => $item['name'],
            'item_image' => $item['image'],
            'buyer_nickname' => $nickname,
            'proxy_status' => ProcurementOrder::STATUS_PENDING,
            'order_narrative' => $narrative,
            'budget_amount' => $normalizedAmount,
            'extra' => [
                'source' => 'settlement',
                'category' => $item['category'],
                'reference_item_id' => data_get($item, 'id'),
                'reference_price' => data_get($item, 'reference_price'),
                'seed_amount' => $normalizedAmount,
            ],
        ]);
    }

    protected function pickRandomItem()
    {
        if (!Schema::hasTable('procurement_reference_items')) {
            return $this->defaultReferenceItem();
        }

        $item = ProcurementReferenceItem::query()->inRandomOrder()->first();
        if (!$item) {
            return $this->defaultReferenceItem();
        }

        return [
            'id' => $item->id,
            'name' => $item->name,
            'image' => $item->image_url,
            'category' => $item->category,
            'reference_price' => (float) $item->reference_price,
        ];
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
            '寻找%s，预算%s，支持同城面交或EMS直邮。',
        ];

        $template = $templates[array_rand($templates)];
        return sprintf($template, $itemName, number_format($amount, 2));
    }

    protected function defaultReferenceItem()
    {
        return [
            'id' => null,
            'name' => '跨境代购服务',
            'image' => '/images/procurement/default-item.jpg',
            'category' => 'general',
            'reference_price' => null,
        ];
    }
}