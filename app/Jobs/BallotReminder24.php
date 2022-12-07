<?php

namespace App\Jobs;

use App\Console\Helper;
use App\Http\EmailerHelper;

use App\Models\UserAddress;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BallotReminder24 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $ballot;

    public function __construct($ballot) {
    	$this->ballot = $ballot;
    }

    public function handle() {
    	if (!$this->ballot) return;

    	$voteResults = $this->ballot->voteResults ?? [];
    	$voteUsers = [];
    	if ($voteResults && count($voteResults) > 0) {
    		foreach ($voteResults as $item) {
    			$voteUsers[] = (int) $item->user_id;
    		}
    	}

    	$settings = Helper::getSettings();
    	$members = Helper::getActiveMembers();

    	$voting_eras_to_vote = isset($settings['voting_eras_to_vote']) ? (int) $settings['voting_eras_to_vote'] : 1;
        $voting_eras_since_redmark = isset($settings['voting_eras_since_redmark']) ? (int) $settings['voting_eras_since_redmark'] : 1;
        
    	if ($members && count($members) > 0) {
    		$emails = $processed = [];
    		foreach ($members as $member) {
    			$userId = (int) $member->id;
    			$email = $member->email;

    			if (
    				!in_array($email, $emails) &&
    				!in_array($userId, $processed) &&
    				!in_array($userId, $voteUsers)
    			) {
    				$canVote = false;
    				$processed[] = $userId;
    				
    				$userAddresses = UserAddress::select('public_address_node')
    											->where('user_id', $userId)
    											->get();
    				if ($userAddresses && count($userAddresses) > 0) {
    					foreach ($userAddresses as $addressRecord) {
    						$p = $addressRecord->public_address_node ?? '';
    						
    						$good_standing_eras = Helper::calculateVariables('good_standing_eras', $p, $settings);
            				$total_active_eras = Helper::calculateVariables('total_active_eras', $p, $settings);
    					
            				if (
				                $total_active_eras  >= $voting_eras_to_vote &&
				                $good_standing_eras >= $voting_eras_since_redmark
				            ) {
				            	$canVote = true;
				                break;
				            }
    					}
    				}

    				if ($canVote) {
    					$emails[] = $email;
    				}
    			}
    		}

    		if (count($emails) > 0) {
    			$extraOptions = [
        			'vote_title' => $this->ballot->title ?? ''
        		];
        		$emailerData = EmailerHelper::getEmailerData();
        		foreach ($emails as $email) {
        			$user = new \stdClass();
        			$user->email = $email;
        			EmailerHelper::triggerUserEmail($email, '24hr Vote Reminder', $emailerData, $user, null, $extraOptions);
        		}
    		}
    	}

    	$this->ballot->reminder_24_sent = true;
    	$this->ballot->save();
    }
}