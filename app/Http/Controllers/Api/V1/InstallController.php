<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\EmailerHelper;
use App\Mail\AdminAlert;
use App\Models\EmailerTriggerAdmin;
use App\Models\EmailerTriggerUser;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class InstallController extends Controller
{
    public function install()
    {
        /* Setting */
        $names = [
            'quorum_rate_ballot' => '50',    
        ];
        foreach ($names as $name => $value) {
            $setting = Setting::where('name', $name)->first();
            if (!$setting) {
                $setting = new Setting;
                $setting->name = $name;
                $setting->value = $value;
                $setting->save();
            }
        }
        echo "Setting created<br/>";
    }

    public function installEmailer() {

        // Setup User
        $userData = [
            [
                'title' => 'Welcome to the Casper',
                'subject' => 'Welcome to the Casper Association portal!',
                'content' => 'Welcome to the Casper Association,<br/><br/>To access your member portal you must complete 3 simple steps that you can find once you log in.<br/><br/>#1. E-sign the terms of the portal.<br/><br/>#2. Verify your node. For this step you will need to enter the public address for your node. Next, you will be given a file to sign locally on your node (for security) by following the instructions provided in this process. You will the upload the signed file to verify that you own the provided public address.<br/><br/>#3. Upload a letter of motivation. This is the final step for membership and requires you to write and upload a letter of motivation explaining why you would like to be a member. Remember, members are able to vote about important matters effecting the Casper network. For this reason, we like to know why everyone is here. Feel free to explain why you like Casper, why you are running a node, and why you want to be able to vote and participate as a member. Don\'t over-think it but be detailed. There are no wrong answers here!<br/><br/>Please reply to this email if you need help'
            ],
            [
                'title' => 'Your Node is Verified',
                'subject' => 'Your Node is Verified',
                'content' => 'Well done! Your public node [node address] is verified as owned by you. If you have not yet completed the remaining on-boarding steps, please remember you need to e-sign the documents and upload a letter of motivation prior to accessing the member\'s dashboard.'
            ],
            [
                'title' => 'Your letter of motivation is received',
                'subject' => 'Your letter of motivation is received',
                'content' => 'Thank you. Your letter of motivation has been received. Please allow our team up to 72 hours to review this letter. You will receive further communication once this process is complete.'
            ],
            [
                'title' => 'Your letter of motivation is APPROVED',
                'subject' => 'Your letter of motivation is APPROVED',
                'content' => 'Well done! Your letter of motivation has been approved. Please log in to your portal and complete any on-boarding steps that are not yet finished. If this is your last step then you will now have access to the member\'s dashboard.'
            ],
            [
                'title' => 'Congratulations',
                'subject' => 'User [email] has uploaded a letter of motivation',
                'content' => 'Excellent work! You have completed all the required steps to access the member\'s dashboard.<br/><br/>Please log in to explore. You now have access to the following:<br/>- Node and network metrics<br/>- Discussion previews<br/>- Viewing previous votes<br/><br/>To fully unlock your dashboard\'s features, you will need to verify yourself inside the portal. This will grant you the Casper Red Checkmark, proving to the network that you are a Verified Member worthy of a higher level of trust. This process is free and takes only 5 minutes of your time.<br/><br/>Verified Members access all membership perks more likely to be trusted by the public for staking delegation and even get access to a public profile. It\'s a very fast process to upgrade to a Verified Member. Just look for the get verified links on the dashboard.<br/><br/>Verified Members can do the following:<br/>- Start and participate in member discussions<br/>- Vote on protocol updates or changes<br/>- Display a page to verify their status to the public<br/>- Access member benefits and perks<br/>- View all network and node metrics<br/>- Easily view details earnings from staking<br/>- Track all health metrics for their node<br/>'
            ],
        ];

        EmailerTriggerUser::where('id', '>', 0)->delete();

        if (count($userData)) {
            foreach ($userData as $item) {
                $record = EmailerTriggerUser::where('title', $item['title'])->first();
                if ($record) $record->delete();

                $record = new EmailerTriggerUser;
                $record->title = $item['title'];
                $record->subject = $item['subject'];
                $record->content = $item['content'];
                $record->save();
            }
        }

        // Setup Admin
        $adminData = [
            [
                'title' => 'User uploads a letter',
                'subject' => 'User [email] has uploaded a letter of motivation',
                'content' => 'Please log in to the portal to review the letter of motivation for [email] in the Admin\'s "Intake" tab.'
            ],
            [
                'title' => 'KYC or AML need review',
                'subject' => 'KYC or AML for [name] needs review',
                'content' => 'Please log in to the portal and go to the lower "Verifications" table in the Admin\'s "Intake" tab. You must click the review button next to user [name] to review this users information and select a further action based on the organization\'s guidelines'
            ],
        ];

        EmailerTriggerAdmin::where('id', '>', 0)->delete();

        if (count($adminData)) {
            foreach ($adminData as $item) {
                $record = EmailerTriggerAdmin::where('title', $item['title'])->first();
                if ($record) $record->delete();

                $record = new EmailerTriggerAdmin;
                $record->title = $item['title'];
                $record->subject = $item['subject'];
                $record->content = $item['content'];
                $record->save();
            }
        }
    }
}
