<?php

namespace App\Console\Commands;

use App\Models\ProcurementOrder;
use App\Models\ProcurementReferenceItem;
use App\Services\ProcurementNarrativeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class GenerateBackgroundOrders extends Command
{
    protected $signature = 'demo:generate-background-orders {--count=1 : Number of mock orders to generate}';

    protected $description = 'Generate demo mock procurement orders and auto-transition a subset of pending orders';

    public function handle()
    {
        if (!$this->isDemoEnabled()) {
            $this->info('Skip: DEMO_MODE is disabled and environment is not local/testing.');

            return 0;
        }

        if (!Schema::hasTable('procurement_orders') || !Schema::hasTable('procurement_reference_items')) {
            $this->warn('Skip: required tables are missing.');

            return 0;
        }

        $count = max(1, (int) $this->option('count'));
        $narratives = app(ProcurementNarrativeService::class);
        $usedTemplateIndices = $this->recentTemplateIndicesInHall();

        $created = 0;
        for ($i = 0; $i < $count; $i++) {
            if ($this->generateOneMockOrder($narratives, $usedTemplateIndices)) {
                $created++;
            }
        }

        $moved = $this->autoTransitionMockOrders();

        $this->info(sprintf('Background pulse completed. created=%d, transitioned=%d', $created, $moved));

        return 0;
    }

    protected function generateOneMockOrder(ProcurementNarrativeService $narratives, array &$usedTemplateIndices)
    {
        $ref = ProcurementReferenceItem::query()->inRandomOrder()->first();
        if (!$ref) {
            $this->warn('Skip create: no procurement reference items found.');

            return false;
        }

        $jitter = random_int(-150, 150);
        $budgetAmount = (int) max(2000, min(50000, (int) round((float) $ref->reference_price) + $jitter));
        $built = $narratives->build((string) $ref->name, $budgetAmount, null, $usedTemplateIndices);
        $usedTemplateIndices[] = $built['template_index'];

        $order = new ProcurementOrder();
        $order->item_name = (string) $ref->name;
        $order->item_image = '';
        $order->buyer_nickname = $this->randomNickname();
        $order->proxy_status = ProcurementOrder::STATUS_PENDING;
        $order->order_narrative = $built['text'];
        $order->budget_amount = $budgetAmount;
        $order->extra = [
            'source' => 'background_pulse',
            'reference_item_id' => (int) $ref->id,
            'reference_category' => (string) $ref->category,
            'narrative_template_index' => $built['template_index'],
            'is_demo_data' => true,
            'generated_at' => now()->toDateTimeString(),
        ];
        $order->save();

        if (Schema::hasColumn('procurement_orders', 'is_mock')) {
            ProcurementOrder::query()->where('id', $order->id)->update(['is_mock' => true]);
        }

        return true;
    }

    /**
     * 读取大厅近期订单已用话术模板，避免新生成单与首页重复。
     *
     * @return int[]
     */
    protected function recentTemplateIndicesInHall()
    {
        $orders = ProcurementOrder::query()
            ->orderBy('created_at', 'desc')
            ->limit(48)
            ->get(['extra']);

        $indices = [];
        foreach ($orders as $order) {
            $idx = data_get($order->extra, 'narrative_template_index');
            if ($idx !== null && $idx !== '') {
                $indices[] = (int) $idx;
            }
        }

        return $indices;
    }

    protected function autoTransitionMockOrders()
    {
        $query = ProcurementOrder::query()->where('proxy_status', ProcurementOrder::STATUS_PENDING);

        if (Schema::hasColumn('procurement_orders', 'is_mock')) {
            $query->where('is_mock', true);
        } else {
            $query->whereNull('order_no');
        }

        $pending = $query->get(['id']);
        $total = $pending->count();
        if ($total === 0) {
            return 0;
        }

        $target = max(1, (int) floor($total * 0.2));
        $selected = $pending->shuffle()->take($target);

        $moved = 0;
        foreach ($selected as $row) {
            $status = random_int(0, 100) < 75
                ? ProcurementOrder::STATUS_ACCEPTED
                : ProcurementOrder::STATUS_SOURCING;

            $updated = ProcurementOrder::query()
                ->where('id', $row->id)
                ->where('proxy_status', ProcurementOrder::STATUS_PENDING)
                ->update(['proxy_status' => $status]);

            if ($updated > 0) {
                $moved++;
            }
        }

        return $moved;
    }

    protected function randomNickname()
    {
        $pool = [
            '小张在大阪', '抹茶控', '浅草散步者', '东京夜猫子', '神户买手',
            '北海道小队', '奈良鹿友', '冲绳海风', '秋叶原手办党', '京都慢生活',
            '代官山通勤人', '名古屋日常', '福冈甜品脑袋', '札幌雪人', '湘南海边',
            '大阪吃货', '横滨通勤族', '广岛柠檬', '金泽和果子', '长崎华人',
        ];

        return $pool[array_rand($pool)];
    }

    protected function isDemoEnabled()
    {
        if (app()->environment(['local', 'testing'])) {
            return true;
        }

        $flag = strtolower((string) env('DEMO_MODE', 'false'));

        return in_array($flag, ['1', 'true', 'yes', 'on'], true);
    }
}
