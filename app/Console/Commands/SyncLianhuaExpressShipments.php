<?php

namespace App\Console\Commands;

use App\Services\Lianhua\LianhuaExpressShipmentSyncService;
use Illuminate\Console\Command;

class SyncLianhuaExpressShipments extends Command
{
    protected $signature = 'lianhua:sync-shipments {--dry-run : 只匹配不写入后台}';

    protected $description = '从联华速递同步已发货 EMS 单号并写入后台订单';

    public function handle(LianhuaExpressShipmentSyncService $syncService)
    {
        if (!config('lianhua_express.enabled')) {
            $this->warn('LIANHUA_ENABLED 未开启，已跳过。');
            return 0;
        }

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->info('dry-run 模式：只报告匹配结果，不会写入订单。');
        }

        try {
            $report = $syncService->sync($dryRun);
        } catch (\Throwable $e) {
            $this->error('同步失败：' . $e->getMessage());
            return 1;
        }

        $this->line('联华已发货记录：' . $report['fetched']);
        $this->line('后台待发货订单：' . $report['pending_orders']);
        $this->line('成功匹配：' . $report['matched']);
        $this->line(($dryRun ? '预计写入' : '已写入') . '：' . $report['applied']);

        if (!empty($report['skipped'])) {
            $this->warn('已跳过 ' . count($report['skipped']) . ' 条：');
            foreach ($report['skipped'] as $item) {
                $this->line('- ' . $item['tracking'] . ' / ' . $item['recipient'] . '：' . $item['reason']);
            }
        }

        if (!empty($report['ambiguous'])) {
            $this->warn('同名多单未能自动匹配 ' . count($report['ambiguous']) . ' 条：');
            foreach ($report['ambiguous'] as $item) {
                $this->line('- ' . $item['tracking'] . ' / ' . $item['recipient'] . ' → 订单 ' . implode(', ', $item['order_nos']));
            }
        }

        if (!empty($report['unmatched_records'])) {
            $this->warn('联华记录未找到对应订单 ' . count($report['unmatched_records']) . ' 条：');
            foreach ($report['unmatched_records'] as $item) {
                $phone = trim((string) data_get($item, 'phone'));
                $suffix = $phone !== '' ? ' / ' . $phone : '';
                $this->line('- ' . $item['tracking'] . ' / ' . $item['recipient'] . $suffix);
            }
        }

        if ($dryRun && (int) $report['matched'] === 0 && !empty($report['pending_samples'])) {
            $this->line('后台待发货样例（前 20 单，用于对照姓名/电话）：');
            foreach ($report['pending_samples'] as $item) {
                $this->line('- ' . $item['no'] . ' / ' . ($item['name'] !== '' ? $item['name'] : '（无姓名）') . ' / ' . ($item['phone'] !== '' ? $item['phone'] : '（无电话）'));
            }
        }

        if (!empty($report['errors'])) {
            $this->error('写入失败 ' . count($report['errors']) . ' 条：');
            foreach ($report['errors'] as $item) {
                $this->line('- 订单 ' . $item['order_no'] . ' / ' . $item['tracking'] . '：' . $item['message']);
            }
        }

        return empty($report['errors']) ? 0 : 1;
    }
}
