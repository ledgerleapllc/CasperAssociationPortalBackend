<?php

namespace App\Jobs;

use App\Console\Helper;
use App\Http\EmailerHelper;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PerkNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $perk;

    public function __construct($perk) {
    	$this->perk = $perk;	
    }

    public function handle() {
    	$members = Helper::getActiveMembers();
        if ($members && count($members) > 0) {
        	$emails = [];
        	foreach ($members as $member) {
        		$email = $member->email;
        		if (!in_array($email, $emails)) {
        			$emails[] = $email;
        		}
        	}
        	if (count($emails) > 0) {
        		$extraOptions = [
        			'perk_title' => $this->perk->title ?? ''
        		];
        		$emailerData = EmailerHelper::getEmailerData();
        		foreach ($emails as $email) {
        			$user = new \stdClass();
        			$user->email = $email;
        			EmailerHelper::triggerUserEmail($email, 'New Perk Created', $emailerData, $user, null, $extraOptions);
        		}
        	}
        }
    }
}
