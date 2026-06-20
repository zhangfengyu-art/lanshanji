<?php

namespace App\Console\Commands;

use App\Services\Lianhua\LianhuaExpressClient;
use Illuminate\Console\Command;

class ProbeLianhuaExpress extends Command
{
    protected $signature = 'lianhua:probe';

    protected $description = '登录联华速递并探测预报查询列表接口（用于配置 LIANHUA_LIST_URL）';

    public function handle(LianhuaExpressClient $client)
    {
        try {
            $client->login();
            $this->info('联华登录成功。');
        } catch (\Throwable $e) {
            $this->error('联华登录失败：' . $e->getMessage());
            return 1;
        }

        $this->line('正在试探联华列表接口，可能需要半分钟左右…');

        try {
            $result = $client->saveProbeArtifacts();
        } catch (\Throwable $e) {
            $this->error('探测失败：' . $e->getMessage());
            return 1;
        }

        $discovery = (array) data_get($result, 'discovery', []);

        $this->info('预报查询页 HTML 已保存：' . $result['html_path']);
        $this->line('HTML 表格解析行数：' . $result['html_table_rows']);

        if (!empty($discovery['url']) && (int) data_get($discovery, 'score', 0) > 0) {
            $this->info('试探成功，列表接口：' . $discovery['url']);
            $this->line('匹配得分：' . $discovery['score'] . '，返回行数：' . $discovery['row_count'] . '，EMS 单号：' . $discovery['tracking_count']);

            if (!empty($discovery['filters'])) {
                $this->info('建议筛选参数：');
                foreach ($discovery['filters'] as $key => $value) {
                    $this->line('- LIANHUA_SHIPPED_' . strtoupper($key) . '=' . $value);
                }
            }

            $this->line('可在 .env 中设置：');
            $this->line('LIANHUA_LIST_URL=' . $discovery['url']);

            if (!empty($discovery['sample'])) {
                $this->info('样例数据：');
                foreach ($discovery['sample'] as $item) {
                    $this->line('- ' . data_get($item, 'recipient') . ' / ' . data_get($item, 'tracking') . ' / ' . data_get($item, 'status'));
                }
            }

            $this->info('探测结果已缓存，可直接试跑：php artisan lianhua:sync-shipments --dry-run');
            return 0;
        }

        if (!empty($result['detected_list_url'])) {
            $this->warn('HTML 中推测到 URL：' . $result['detected_list_url'] . '，但试探未拿到有效 EMS 数据。');
        } else {
            $this->warn('未能自动找到有效的列表接口。');
        }

        $this->line('请用浏览器打开「预报查询 → 已发货」，在开发者工具 Network 里找 bootstrap-table 的 POST 请求，');
        $this->line('把 Request URL 路径和 Form Data 填入 .env 的 LIANHUA_LIST_URL / LIANHUA_SHIPPED_*。');

        if (!empty($result['detected_filters'])) {
            $this->info('HTML 推测的筛选参数：');
            foreach ($result['detected_filters'] as $key => $value) {
                $this->line('- ' . $key . '=' . $value);
            }
        }

        return 1;
    }
}
