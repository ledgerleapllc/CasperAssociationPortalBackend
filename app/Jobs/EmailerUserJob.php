<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Console\Helper;
use App\Http\EmailerHelper;

class EmailerUserJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $user;
    private $title;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($user, $title)
    {
        $this->user = $user;
        $this->title = $title;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
    	if (!$this->user || !$this->title) {
    		return;
    	}

    	$user = $this->user;
    	$title = $this->title;
    	$email = $user->email ?? '';

    	if ($email) {
    		$emailerData = EmailerHelper::getEmailerData();
    		EmailerHelper::triggerUserEmail($email, $title, $emailerData, $user);
    	}
    }
}