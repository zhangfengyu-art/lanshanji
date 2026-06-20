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

        try {
            $result = $client->saveProbeArtifacts();
        } catch (\Throwable $e) {
            $this->error('探测失败：' . $e->getMessage());
            return 1;
        }

        $this->info('预报查询页 HTML 已保存：' . $result['html_path']);

        if (!empty($result['detected_list_url'])) {
            $this->info('探测到列表接口：' . $result['detected_list_url']);
            $this->line('可在 .env 中设置：LIANHUA_LIST_URL=' . $result['detected_list_url']);
        } else {
            $this->warn('未能从 HTML 自动探测列表接口。');
            $this->line('请用浏览器打开「预报查询 → 已发货」，在开发者工具 Network 里找 bootstrap-table 的 POST 请求，');
            $this->line('把 Request URL 路径填入 LIANHUA_LIST_URL（例如 /Member/XXX）。');
        }

        if (!empty($result['detected_filters'])) {
            $this->info('推测的已发货筛选参数：');
            foreach ($result['detected_filters'] as $key => $value) {
                $this->line('- ' . $key . '=' . $value);
            }
        }

        $this->line('HTML 表格解析行数：' . $result['html_table_rows']);

        if ($result['html_table_rows'] > 0) {
            $this->info('页面内已有可解析表格，即使未配置 LIANHUA_LIST_URL 也可能直接同步。');
        }

        return 0;
    }
}
