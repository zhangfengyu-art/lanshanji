<?php

namespace App\Console\Commands;

use App\Services\DailyExportArtifactService;
use App\Services\GoogleDriveUploadService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ExportDailyToGoogleDrive extends Command
{
    protected $signature = 'exports:daily-to-google
                            {--dry-run : 仅生成 PDF/CSV 到本地临时目录，不上传 Google Drive}';

    protected $description = '生成待处理订单表与备货表（PDF+CSV），上传到 Google 云端硬盘';

    public function handle(
        DailyExportArtifactService $artifacts,
        GoogleDriveUploadService $drive
    ) {
        if (!is_site_mode_a()) {
            $this->warn('当前站点不是 A 站模式，已跳过。');

            return 0;
        }

        if (!config('daily_export.enabled')) {
            $this->warn('DAILY_EXPORT_ENABLED 未开启，已跳过。');

            return 0;
        }

        $dryRun = (bool) $this->option('dry-run');
        $timezone = (string) config('daily_export.timezone', 'Asia/Tokyo');
        $runAt = Carbon::now($timezone);

        $this->info('开始每日导出（'.$runAt->format('Y-m-d H:i').' '.$timezone.'）');

        $jobs = [
            [
                'folder' => (string) config('daily_export.google.folder_orders', ''),
                'build' => function () use ($artifacts) {
                    return $artifacts->buildOrdersPendingExport();
                },
            ],
            [
                'folder' => (string) config('daily_export.google.folder_stock_prep', ''),
                'build' => function () use ($artifacts) {
                    return $artifacts->buildStockPrepPendingExport();
                },
            ],
        ];

        $failed = false;

        foreach ($jobs as $job) {
            try {
                $bundle = $job['build']();
                $this->line('• '.$bundle['label'].'：'.$bundle['row_count'].' 行');

                if ($dryRun) {
                    $this->line('  PDF: '.$bundle['pdf']);
                    $this->line('  CSV: '.$bundle['csv']);
                    continue;
                }

                $pdf = $drive->uploadFile(
                    $bundle['pdf'],
                    $bundle['pdf_filename'],
                    $job['folder'],
                    'application/pdf'
                );
                $csv = $drive->uploadFile(
                    $bundle['csv'],
                    $bundle['csv_filename'],
                    $job['folder'],
                    'text/csv'
                );

                $this->info('  已上传 PDF：'.$pdf['name'].' ('.$pdf['id'].')');
                $this->info('  已上传 CSV：'.$csv['name'].' ('.$csv['id'].')');
            } catch (\Throwable $e) {
                $failed = true;
                $this->error('  失败：'.$e->getMessage());
                \Log::error('exports:daily-to-google failed', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        if ($failed) {
            $this->error('每日导出未全部成功，请查看 storage/logs/laravel.log');

            return 1;
        }

        $this->info($dryRun ? 'dry-run 完成（未上传 Drive）。' : '每日导出已全部上传 Google Drive。');

        return 0;
    }
}
