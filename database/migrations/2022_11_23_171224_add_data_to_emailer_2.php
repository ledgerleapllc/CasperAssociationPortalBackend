<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use App\Models\EmailerTriggerUser;

class AddDataToEmailer2 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
    	$userData = [
    		[
                'title' => 'User goes on probation',
                'subject' => 'Your membership is on probation',
                'content' => 'Your membership with the Casper Association is on probation. Login and view your membership tab to see why.'
            ],
            [
            	'title' => 'User membership is revoked',
            	'subject' => 'Your membership has been revoked',
            	'content' => 'Your membership with the Casper Association has been revoked. Login to see the reason why.'
            ]
    	];

    	foreach ($userData as $item) {
            $record = EmailerTriggerUser::where('title', $item['title'])->first();
            if (!$record) {
	            $record = new EmailerTriggerUser;
	            $record->title = $item['title'];
	            $record->subject = $item['subject'];
	            $record->content = $item['content'];
	            $record->save();
        	}
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    	//
    }
}
