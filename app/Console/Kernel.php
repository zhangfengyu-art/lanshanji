<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // 每分钟检查一次是否有过期的未支付订单，如果有则关闭
        $schedule->command('orders:close-expired')
            ->everyMinute();

        // Demo/压测脉冲：每小时窗口内随机触发一次，避免固定整点行为。
        $schedule->command('demo:generate-background-orders --count=1')
            ->cron('*/45 * * * *')
            ->when(function () {
                if (!$this->isDemoModeEnabled()) {
                    return false;
                }

                // Introduce extra randomness so trigger time is less predictable.
                return random_int(1, 100) <= 70;
            })
            ->withoutOverlapping();

        $schedule->command('exports:daily-to-google')
            ->dailyAt((string) config('daily_export.run_at', '05:00'))
            ->timezone((string) config('daily_export.timezone', 'Asia/Tokyo'))
            ->when(function () {
                return (bool) config('daily_export.enabled') && is_site_mode_a();
            })
            ->withoutOverlapping();
    }

    protected function isDemoModeEnabled()
    {
        if ($this->app->environment(['local', 'testing'])) {
            return true;
        }

        $flag = strtolower((string) env('DEMO_MODE', 'false'));

        return in_array($flag, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
