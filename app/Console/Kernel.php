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
        $schedule->command('ballot:check')
            ->everyTwoMinutes()
            ->runInBackground();

        $schedule->command('ballot:check2')
            ->hourly()
            ->runInBackground();

        $schedule->command('upgrade:check')
            ->hourly()
            ->runInBackground();

        $schedule->command('perk:check')
            ->everyThirtyMinutes()
            ->runInBackground();

        $schedule->command('notif:check')
            ->dailyAt('00:01')
            ->runInBackground();

        $schedule->command('node-status:check')
            ->everyFiveMinutes()
            ->runInBackground();

        $schedule->command('token-price:check')
            ->everyThirtyMinutes()
            ->runInBackground();

        $schedule->command('historical-data')
            ->withoutOverlapping()
            ->everyFiveMinutes()
            ->runInBackground();
            
        $schedule->command('kyc:report')
            ->dailyAt('10:02')
            ->runInBackground();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
