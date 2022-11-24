<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

use App\Mail\UserAlert;

use App\Models\User;
use App\Models\UpgradeList;

use Carbon\Carbon;

class NewUpgradeNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $upgrade;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($upgrade)
    {
        $this->upgrade = $upgrade;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (!$this->upgrade) return;

        $upgrade = $this->upgrade;
        $emails = [];

        // Fetch Upgrade List
        $items = UpgradeList::all();
        if ($items && count($items) > 0) {
        	foreach ($items as $item) {
        		$email = $item->email;
        		if (!in_array($email, $emails)) {
        			$emails[] = $email;
        		}
        	}
        }

        // Fetch Valid Users
		$users = User::select('email')
        			->has('addresses')
        			->where('banned', 0)
        			->where('role', 'member')
        			->whereNotNull('public_address_node')
        			->whereNotNull('signature_request_id')
        			->whereNotNull('signed_file')
        			->whereNotNull('node_verified_at')
        			->whereNotNull('letter_file')
        			->whereNotNull('letter_verified_at')
        			->where(function ($query) {
        				$query->doesntHave('profile')
        					->orWhereHas('profile', function ($query2) {
        						$query2->where('profile.extra_status', '!=', 'Suspended')
        								->orWhereNull('profile.extra_status');
        					});
        			})
        			->get();
       	if ($users && count($users) > 0) {
       		foreach ($users as $user) {
       			$email = $user->email;
        		if (!in_array($email, $emails)) {
        			$emails[] = $email;
        		}
       		}
       	}

       	if (count($emails) > 0) {
       		$date = Carbon::parse($upgrade->activation_date)->format('d/m/Y');

       		$subject = 'Casper Protocol Upgrade Available - Upgrade before ' . $date;
			$content = 'Version ' . $upgrade->version . ' of the Casper protocol is available and must be installed prior to ' . $date . '. ';
			$content .= 'This version goes live on mainnet in ERA #' . $upgrade->activation_era . '. ';
			$content .= 'If you have already completed this upgrade, please click "Mark as Done" in the Upgrades tab of your Casper Membership Portal.';
			$content .= '<br /><br />This upgrade can be found at ' . $upgrade->link . '.<br/><br/>';
			$content .= 'Changes include:<br />' . $upgrade->notes . '.';
			
			foreach ($emails as $email) {
				Mail::to($email)->send(new UserAlert($subject, $content));
    		}
		}
    }
}