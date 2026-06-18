<?php

namespace App\Services;

use App\Models\ProcurementOrder;
use App\Models\ProcurementReferenceItem;
use InvalidArgumentException;
use Illuminate\Support\Facades\Schema;

class ProcurementService
{
    /** @var ProcurementNarrativeService */
    protected $narratives;

    public function __construct(ProcurementNarrativeService $narratives)
    {
        $this->narratives = $narratives;
    }

    /**
     * 从结算金额生成一条 C2C 代购需求单（按金额匹配参考商品库）。
     */
    public function createFromSettlement(float $amount)
    {
        $normalizedAmount = round($amount, 2);
        if ($normalizedAmount <= 0) {
            throw new InvalidArgumentException('Settlement amount must be greater than zero.');
        }

        $item = $this->pickItemForAmount($normalizedAmount);
        $nickname = $this->pickRandomNickname();
        $narrative = $this->narratives->build($item['name'], $normalizedAmount);

        return ProcurementOrder::query()->create([
            'item_name' => $item['name'],
            'item_image' => '',
            'buyer_nickname' => $nickname,
            'proxy_status' => ProcurementOrder::STATUS_PENDING,
            'order_narrative' => $narrative['text'],
            'budget_amount' => $normalizedAmount,
            'extra' => [
                'source' => 'settlement',
                'category' => $item['category'],
                'reference_item_id' => data_get($item, 'id'),
                'reference_price' => data_get($item, 'reference_price'),
                'narrative_template_index' => $narrative['template_index'],
                'seed_amount' => $normalizedAmount,
            ],
        ]);
    }

    protected function pickItemForAmount(float $amount)
    {
        if (!Schema::hasTable('procurement_reference_items')) {
            return $this->defaultReferenceItem($amount);
        }

        $item = ProcurementReferenceItem::query()
            ->whereBetween('reference_price', [2000, 50000])
            ->orderByRaw('ABS(reference_price - ?)', [$amount])
            ->first();

        if (!$item) {
            $item = ProcurementReferenceItem::query()->inRandomOrder()->first();
        }

        if (!$item) {
            return $this->defaultReferenceItem($amount);
        }

        return [
            'id' => $item->id,
            'name' => $item->name,
            'image' => '',
            'category' => $item->category,
            'reference_price' => (float) $item->reference_price,
        ];
    }

    protected function pickRandomNickname()
    {
        $pool = [
            '小张在大阪', '抹茶控', '浅草散步者', '东京夜猫子', '神户买手',
            '北海道小队', '奈良鹿友', '冲绳海风', '秋叶原手办党', '京都慢生活',
            '代官山通勤人', '名古屋日常', '福冈甜品脑袋', '札幌雪人', '湘南海边',
            '大阪吃货', '横滨通勤族', '广岛柠檬', '金泽和果子', '长崎华人',
            '静冈茶农', '鹿儿岛黑糖', '高松海苔', '盛冈冷面', '宇治抹茶',
            '箱根温泉客', '镰仓海边', '轻井泽避暑', '富士山游客', '小樽玻璃',
        ];

        return $pool[array_rand($pool)];
    }

    protected function defaultReferenceItem(float $amount)
    {
        return [
            'id' => null,
            'name' => '日本人气商品',
            'image' => '',
            'category' => 'general',
            'reference_price' => $amount,
        ];
    }
}
