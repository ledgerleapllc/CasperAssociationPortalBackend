<?php

namespace App\Console\Commands;

use App\Models\Perk;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PerkCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'perk:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perk check command';

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
        // check perk waiting
        $waitingPerks = Perk::where('status', 'waiting')->where('setting', 1)->where('start_date', '<=', $now)->where('end_date', '>=', $now)->get();
        foreach ($waitingPerks as $perk) {
            $perk->status = 'active';
            $perk->visibility = 'visible';
            $perk->save();
        }

        // check perk expired
        $expiredPerks = Perk::where('end_date', '<', $now)->where('setting', 1)->where('status', '!=', 'expired')->get();
        foreach ($expiredPerks as $perk) {
            $perk->status = 'expired';
            $perk->visibility = 'hidden';
            $perk->save();
        }
    }
}
