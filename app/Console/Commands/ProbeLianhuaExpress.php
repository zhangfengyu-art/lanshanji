<?php

namespace App\Console\Commands;

use App\Services\Lianhua\LianhuaExpressClient;
use Illuminate\Console\Command;

class ProbeLianhuaExpress extends Command
{
    protected $signature = 'lianhua:probe {--quick : 快速试探（约 10–20 秒，适合 BT 面板终端）}';

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

        $quick = (bool) $this->option('quick');
        $this->line($quick
            ? '快速试探中（约 10–20 秒）…'
            : '试探联华列表接口（约 30–60 秒）。若 BT 终端易断线，请加 --quick 或用 nohup 后台跑。');

        try {
            $result = $client->saveProbeArtifacts(null, $quick);
        } catch (\Throwable $e) {
            $this->error('探测失败：' . $e->getMessage());
            return 1;
        }

        $discovery = (array) data_get($result, 'discovery', []);

        $this->info('预报查询页 HTML 已保存：' . $result['html_path']);
        $this->line('HTML 表格解析行数：' . $result['html_table_rows']);
        if (!empty($discovery['attempts'])) {
            $this->line('本次试探请求数：' . $discovery['attempts']);
        }

        if (!empty($discovery['url']) && (int) data_get($discovery, 'score', 0) > 0) {
            $this->info('试探成功，列表接口：' . $discovery['url']);
            $this->line('匹配得分：' . $discovery['score'] . '，返回行数：' . $discovery['row_count'] . '，EMS 单号：' . $discovery['tracking_count']);

            if (!empty($discovery['filters'])) {
                $this->info('建议筛选参数：');
                foreach ($discovery['filters'] as $key => $value) {
                    $this->line('- ' . $key . '=' . $value);
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

        if (!empty($result['html_url_hints'])) {
            $this->warn('HTML 中较可能的接口（可用 lianhua:grep-html 查看）：');
            foreach ($result['html_url_hints'] as $url) {
                $this->line('- ' . $url);
            }
        }

        if (!empty($result['detected_list_url'])) {
            $this->warn('HTML 中推测到 URL：' . $result['detected_list_url'] . '，但试探未拿到有效 EMS 数据。');
        } else {
            $this->warn('未能自动找到有效的列表接口。');
        }

        $this->line('断线时可后台跑：nohup php artisan lianhua:probe --quick > /tmp/lianhua-probe.log 2>&1 &');
        $this->line('然后 tail -f /tmp/lianhua-probe.log 查看进度。');

        return 1;
    }
}
