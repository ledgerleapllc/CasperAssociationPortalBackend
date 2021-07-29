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
        // $schedule->command('inspire')->hourly();
        $schedule->command('ballot:check')
            ->everyMinute()
            ->runInBackground();
        $schedule->command('shuftipro:check')
            ->everyFiveMinutes()
            ->runInBackground();
        // ->withoutOverlapping();
        $schedule->command('perk:check')
            ->dailyAt('00:01')
            ->runInBackground();
        $schedule->command('notif:check')
            ->dailyAt('00:01')
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
