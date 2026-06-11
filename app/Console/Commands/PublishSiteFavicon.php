<?php

namespace App\Console\Commands;

use App\Services\SiteFaviconService;
use Illuminate\Console\Command;

class PublishSiteFavicon extends Command
{
    protected $signature = 'site:publish-favicon';

    protected $description = '从后台 Logo 重新生成 public/favicon.png 与 favicon.ico';

    public function handle()
    {
        $service = app(SiteFaviconService::class);

        if (!$service->publishFromCurrentFavicon()) {
            $this->error('生成失败：请确认后台已上传对应站点的标签页图标，且 storage/app/public 下文件可读。');

            return 1;
        }

        $png = public_path('favicon.png');
        $ico = public_path('favicon.ico');
        $this->info('favicon 已生成：');
        $this->line('  '.$png.' ('.filesize($png).' bytes)');
        $this->line('  '.$ico.' ('.filesize($ico).' bytes)');

        return 0;
    }
}
