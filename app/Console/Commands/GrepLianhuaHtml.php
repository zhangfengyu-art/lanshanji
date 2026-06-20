<?php

namespace App\Console\Commands;

use App\Services\Lianhua\LianhuaExpressClient;
use Illuminate\Console\Command;

class GrepLianhuaHtml extends Command
{
    protected $signature = 'lianhua:grep-html';

    protected $description = '分析已保存的联华预报查询页 HTML（无需联网，秒出结果）';

    public function handle(LianhuaExpressClient $client)
    {
        try {
            $result = $client->analyzeSavedProbeHtml();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return 1;
        }

        $this->info('HTML 文件：' . $result['html_path']);
        $this->line('表格行数：' . $result['table_rows']);

        if (!empty($result['filters'])) {
            $this->info('推测筛选参数：');
            foreach ($result['filters'] as $key => $value) {
                $this->line('- ' . $key . '=' . $value);
            }
        }

        if (!empty($result['url_hints'])) {
            $this->info('较可能的列表接口（按优先级排序）：');
            foreach (array_slice($result['url_hints'], 0, 15) as $url) {
                $this->line('- ' . $url);
            }
        } else {
            $this->warn('HTML 中未找到 /Member/ 接口线索。');
        }

        return 0;
    }
}
