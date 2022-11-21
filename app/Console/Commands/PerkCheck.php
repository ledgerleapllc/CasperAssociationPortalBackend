<?php

namespace App\Console\Commands;

use App\Models\Perk;
use App\Jobs\PerkNotification;
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
        $now = Carbon::now('UTC');

        // check perk waiting
        $waitingPerks = Perk::where('status', 'waiting')
        					->where('setting', 1)
        					->where('time_begin', '<=', $now)
        					->where('time_end', '>=', $now)
        					->get();
        foreach ($waitingPerks as $perk) {
            $perk->status = 'active';
            $perk->visibility = 'visible';
            $perk->save();

            PerkNotification::dispatch($perk)->onQueue('default_long');
        }
        
        // check perk expired
        $expiredPerks = Perk::where('time_end', '<', $now)
        					->where('setting', 1)
        					->where('status', '!=', 'expired')
        					->get();
        foreach ($expiredPerks as $perk) {
            $perk->status = 'expired';
            $perk->visibility = 'hidden';
            $perk->save();
        }
    }
}
