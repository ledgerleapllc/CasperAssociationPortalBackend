<?php

namespace App\Console\Commands;

use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class NotifCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notif:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $now = Carbon::now()->format('Y-m-d');
        // check notification waiting
        $waitingNotification = Notification::where('status', 'waiting')->where('setting', 1)->where('start_date', '<=', $now)->where('end_date', '>=', $now)->get();
        foreach ($waitingNotification as $data) {
            $data->status = 'active';
            $data->visibility = 'visible';
            $data->save();
        }

        // check notification expired
        $expiredNotification = Notification::where('end_date', '<', $now)->where('setting', 1)->get();
        foreach ($expiredNotification as $data) {
            $data->status = 'expired';
            $data->visibility = 'hidden';
            $data->save();
        }
    }
}
