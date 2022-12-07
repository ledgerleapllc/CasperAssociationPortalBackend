<?php

namespace App\Console\Commands;

use App\Console\Helper;
use App\Models\Ballot;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckBallot extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ballot:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'ballot check';

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
        $settings = Helper::getSettings();
        $quorumRate = $settings['quorum_rate_ballot'] ?? 50;
        $ballots = Ballot::with(['vote'])->where('status', 'active')
        								->where('time_end', '<=', Carbon::now('UTC'))
        								->get();
        foreach ($ballots as $ballot) {
            $vote = $ballot->vote;
            if ($vote->result_count == 0) {
                $ballot->status = 'fail';
                $ballot->save();
            } else {
                $quorum = $vote->for_value / $vote->result_count * 100;
                if($quorum >= $quorumRate) {
                    $ballot->status = 'pass';
                } else {
                    $ballot->status = 'fail';
                }
                $ballot->save();
            }
        }
    }
}
