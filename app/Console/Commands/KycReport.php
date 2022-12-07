<?php

namespace App\Console\Commands;

use App\Models\Shuftipro;
use App\Models\ShuftiproTemp;
use App\Models\User;
use App\Mail\AdminAlert;
use App\Http\EmailerHelper;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

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
        $now = Carbon::now('UTC');
        $yesterday = Carbon::now('UTC')->subHours(12);
        $records_temp = ShuftiproTemp::where('status', 'pending')
            ->where('created_at', '<=', $yesterday)
            ->limit(10)
            ->get();

        $persons_stuck_in_pending = [];
        $known_reference_ids = [];

        if($records_temp) {
            foreach($records_temp as $record) {
                $user_id = $record->user_id ?? 0;
                $person = User::where('id', $user_id)->first();
                $persons_stuck_in_pending[] = [
                    'name' => $person->first_name.' '.$person->last_name,
                    'email' => $person->email,
                    'shufti_reference_id' => $record->reference_id,
                    'shufti_timestamp' => $record->created_at
                ];

                $known_reference_ids[] = $record->reference_id;
            }
        }

        // now do shuftipro denied
        $records_pro = Shuftipro::where('status', 'denied')
            ->where('reviewed', 0)
            ->limit(10)
            ->get();

        $persons_stuck_denied = [];

        if($records_pro) {
            foreach($records_pro as $record) {
                if(!in_array($record->reference_id, $known_reference_ids)) {
                    $user_id = $record->user_id ?? 0;
                    $person = User::where('id', $user_id)->first();
                    $persons_stuck_denied[] = [
                        'name' => $person->first_name.' '.$person->last_name,
                        'email' => $person->email,
                        'shufti_reference_id' => $record->reference_id,
                        'shufti_timestamp' => $record->created_at
                    ];
                }
            }
        }

        // now make a text list from the matching records
        $body = '';
        $index = 1;

        if($persons_stuck_in_pending) {
            $body .= "Members pending for over 24 hours:<br>";

            foreach($persons_stuck_in_pending as $p) {
                $body .= '<b>' . (string) $index . '. </b>';
                $body .= $p['name'] . ', ' . $p['email'] . '<br>';
                $index += 1;
            }
        }

        $index = 1;

        if($persons_stuck_denied) {
            if($persons_stuck_in_pending) {
                $body .= "<br><br>";
            }

            $body .= "Members denied by Shufti. Not reviewed yet:<br>";

            foreach($persons_stuck_denied as $p) {
                $body .= '<b>' . (string)$index . '. </b>';
                $body .= $p['name'] . ', ' . $p['email'] . '<br>';
                $index += 1;
            }
        }

        // compose email to admins
        if($persons_stuck_in_pending || $persons_stuck_denied) {
            $emailerData = EmailerHelper::getEmailerData();
            $admins = $emailerData['admins'] ?? [];

            if($admins) {
                Mail::to($admins)->send(new AdminAlert('Casper Association portal KYC issues require attention', $body));
            }
        }
    }
}
