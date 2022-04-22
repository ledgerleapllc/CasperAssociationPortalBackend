<?php

namespace App\Console\Commands;

use App\Models\Shuftipro;
use App\Models\ShuftiproTemp;
use App\Models\User;
use App\Mail\AdminAlert;
use App\Http\EmailerHelper;
use Carbon\Carbon;
use Illuminate\Console\Command;

class KycReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kyc:report';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send admins a report of members stuck on KYC';

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
        // first do shufti temp records
        $now = Carbon::now();
        $yesterday = Carbon::now()->subHours(24);
        $records_temp = ShuftiproTemp::where('status', 'pending')->where('created_at', '<=', $yesterday)->limit(10);

        $persons_stuck_in_pending = array();
        $known_reference_ids = array();

        if($records_temp) {
            foreach($records_temp as $record) {
                $user_id = $record->user_id ?? 0;
                $person = User::where('id', $user_id)->first();
                $persons_stuck_in_pending[] = array(
                    "name" => $person->first_name.' '.$person->last_name,
                    "email" => $person->email,
                    "shufti_reference_id" => $record->reference_id,
                    "shufti_timestamp" => $record->created_at,
                    "stuck_reason" => "Pending for over 24 hours. "
                );

                $known_reference_ids[] = $record->reference_id;
            }
        }

        // now do shuftipro denied
        $records_pro = Shuftipro::where('status', 'denied')->where('reviewed', 0)->limit(10);

        $persons_stuck_denied = array();

        if($records_pro) {
            foreach($records_pro as $record) {
                if(!in_array($record->reference_id, $known_reference_ids)) {
                    $user_id = $record->user_id ?? 0;
                    $person = User::where('id', $user_id)->first();
                    $persons_stuck_denied[] = array(
                        "name" => $person->first_name.' '.$person->last_name,
                        "email" => $person->email,
                        "shufti_reference_id" => $record->reference_id,
                        "shufti_timestamp" => $record->created_at,
                        "stuck_reason" => "Member received denied status from Shufti. Has not been review yet. "
                    );
                }
            }
        }

        // now make a text list from the matching records
        $body = "";
        $index = 1;

        foreach($persons_stuck_in_pending as $p) {
            $body .= '<b>'.(string)$index.'. </b>'
            $body .= $p['name'].', '.$p['email'].'<br>';
            $body .= $p['stuck_reason'].$p['shufti_timestamp'].' Shufti reference: '.$p['shufti_reference_id'].'<br><br>';
            $index += 1;
        }

        foreach($persons_stuck_denied as $p) {
            $body .= '<b>'.(string)$index.'. </b>'
            $body .= $p['name'].', '.$p['email'].'<br>';
            $body .= $p['stuck_reason'].$p['shufti_timestamp'].' Shufti reference: '.$p['shufti_reference_id'].'<br><br>';
            $index += 1;
        }

        // compose email to admins
        $emailerData = EmailerHelper::getEmailerData();
        $admins = $emailerData['admins'] ?? array();

        if($admins) {
            Mail::to($admins)->send(new AdminAlert('Casper Association portal KYC issues require attention', $body));
        }
    }
}
