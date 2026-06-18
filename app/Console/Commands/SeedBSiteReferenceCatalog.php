<?php

namespace App\Console\Commands;

use App\Data\BSiteReferenceCatalog;
use App\Models\ProcurementOrder;
use App\Models\ProcurementReferenceItem;
use App\Services\ProcurementNarrativeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SeedBSiteReferenceCatalog extends Command
{
    protected $signature = 'b-site:seed-reference-catalog
                            {--with-demo=42 : 重新生成大厅演示代购单数量，0 为不生成}';

    protected $description = '导入 B 站参考商品库（300 条，2000–50000 日元）并可重建大厅演示单';

    public function handle()
    {
        if (!Schema::hasTable('procurement_reference_items')) {
            $this->error('表 procurement_reference_items 不存在，请先执行 migrate。');

            return 1;
        }

        $catalog = BSiteReferenceCatalog::expandedItems();
        ProcurementReferenceItem::query()->delete();

        $now = now();
        foreach ($catalog as $row) {
            ProcurementReferenceItem::query()->create([
                'name' => $row['name'],
                'category' => $row['category'],
                'reference_price' => $row['reference_price'],
                'image_url' => '',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->info(sprintf('已导入 %d 条参考商品。', count($catalog)));

        $demoCount = max(0, (int) $this->option('with-demo'));
        if ($demoCount > 0 && Schema::hasTable('procurement_orders')) {
            $this->regenerateDemoOrders($demoCount);
        }

        return 0;
    }

    protected function regenerateDemoOrders(int $count)
    {
        $deleteQuery = ProcurementOrder::query();
        if (Schema::hasColumn('procurement_orders', 'is_mock')) {
            $deleteQuery->where('is_mock', true);
        } else {
            $deleteQuery->whereNull('order_no');
        }
        $deleted = $deleteQuery->delete();

        $refs = ProcurementReferenceItem::query()->inRandomOrder()->limit($count)->get();
        if ($refs->isEmpty()) {
            $this->warn('参考商品库为空，跳过演示单生成。');

            return;
        }

        $narratives = app(ProcurementNarrativeService::class);
        $nicknames = [
            '小张在大阪', '抹茶控', '浅草散步者', '东京夜猫子', '神户买手',
            '北海道小队', '奈良鹿友', '冲绳海风', '秋叶原手办党', '京都慢生活',
            '代官山通勤人', '名古屋日常', '福冈甜品脑袋', '札幌雪人', '湘南海边',
            '大阪吃货', '横滨通勤族', '广岛柠檬', '金泽和果子', '长崎华人',
        ];
        $usedTemplateIndices = [];
        $created = 0;

        foreach ($refs as $ref) {
            $jitter = random_int(95, 105) / 100;
            $budget = round(max(2000, min(50000, (float) $ref->reference_price * $jitter)), 2);
            $built = $narratives->build((string) $ref->name, $budget, null, $usedTemplateIndices);
            $usedTemplateIndices[] = $built['template_index'];

            $statusRoll = random_int(1, 100);
            $status = ProcurementOrder::STATUS_PENDING;
            if ($statusRoll > 55) {
                $status = ProcurementOrder::STATUS_ACCEPTED;
            } elseif ($statusRoll > 35) {
                $status = ProcurementOrder::STATUS_SOURCING;
            }

            $order = ProcurementOrder::query()->create([
                'item_name' => $ref->name,
                'item_image' => '',
                'buyer_nickname' => $nicknames[array_rand($nicknames)],
                'proxy_status' => $status,
                'order_narrative' => $built['text'],
                'budget_amount' => $budget,
                'extra' => [
                    'source' => 'background_pulse',
                    'reference_item_id' => $ref->id,
                    'reference_category' => $ref->category,
                    'narrative_template_index' => $built['template_index'],
                    'is_demo_data' => true,
                    'generated_at' => now()->toDateTimeString(),
                ],
            ]);

            if (Schema::hasColumn('procurement_orders', 'is_mock')) {
                ProcurementOrder::query()->where('id', $order->id)->update(['is_mock' => true]);
            }

            $created++;
        }

        $this->info(sprintf('已删除旧演示单 %d 条，新建 %d 条（话术互不重复）。', $deleted, $created));
    }
}
