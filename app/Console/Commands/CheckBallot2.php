<?php
namespace App\Console\Commands;

use App\Console\Helper;
use App\Models\Ballot;
use App\Jobs\BallotReminder24;
use Carbon\Carbon;

use Illuminate\Console\Command;

class CheckBallot2 extends Command
{
    protected $signature = 'ballot:check2';
	protected $description = 'Ballot Check 2';
	
    public function __construct() {
        parent::__construct();
    }

    public function handle() {
    	$ballots = Ballot::with(['vote', 'voteResults'])
    					->where('status', 'active')
        				->where('time_end', '<=', Carbon::now('UTC')->addHours(24))
        				->where('time_end', '>', Carbon::now('UTC'))
        				->where('reminder_24_sent', false)
        				->orderBy('time_end', 'asc')
        				->get();
		
        if ($ballots && count($ballots) > 0) {
        	foreach ($ballots as $ballot) {
        		BallotReminder24::dispatch($ballot)->onQueue('default_long');
        	}
        }

        return 0;
    }
}