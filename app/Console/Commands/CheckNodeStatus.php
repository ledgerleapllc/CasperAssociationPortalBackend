<?php

namespace App\Console\Commands;

use App\Models\MonitoringCriteria;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckNodeStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'node-status:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check node status';

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
        $uptime = MonitoringCriteria::where('type', 'uptime')->first();
        $uptimeProbationStart = $uptime->probation_start;
        if ($uptime->given_to_correct_unit == 'Weeks') {
            $uptimeTime = (float) $uptime->given_to_correct_value * 7 * 24;
        } else if ($uptime->given_to_correct_unit == 'Days') {
            $uptimeTime = (float) $uptime->given_to_correct_value * 24;
        } else {
            $uptimeTime = (float) $uptime->given_to_correct_value;
        }

        $blockHeight = MonitoringCriteria::where('type', 'block-height')->first();
        $blockHeightProbationStart = (float) $blockHeight->probation_start;
        if ($blockHeight->given_to_correct_unit == 'Weeks') {
            $blockHeightTime = (float)$blockHeight->given_to_correct_value * 7 * 24;
        } else if ($blockHeight->given_to_correct_unit == 'Days') {
            $blockHeightTime = (float)$blockHeight->given_to_correct_value * 24;
        } else {
            $blockHeightTime = (float) $blockHeight->given_to_correct_value;
        }

        $updateResponsiveness = MonitoringCriteria::where('type', 'update-responsiveness')->first();
        $updateResponsivenessProbationStart = (float) $updateResponsiveness->probation_start;
        if ($updateResponsiveness->given_to_correct_unit == 'Weeks') {
            $updateResponsivenessTime = (float)$updateResponsiveness->given_to_correct_value * 7 * 24;
        } else if ($updateResponsiveness->given_to_correct_unit == 'Days') {
            $updateResponsivenessTime = (float) $updateResponsiveness->given_to_correct_value * 24;
        } else {
            $updateResponsivenessTime = (float) $updateResponsiveness->given_to_correct_value;
        }

        $now =  Carbon::now('UTC');
        $users = User::where('role', 'member')->where('banned', 0)->with(['metric'])->get();
        foreach ($users as $user) {
            if (!$user->metric || !$user->node_verified_at || !$user->letter_verified_at || !$user->signature_request_id) {
                $user->node_status = null;
                $user->save();
                continue;
            }
            if (
                $user->metric->uptime >= $uptimeProbationStart && $user->metric->block_height_average >= $blockHeightProbationStart
                && $user->metric->update_responsiveness >= $updateResponsivenessProbationStart
            ) {
                $user->node_status = 'Ok';
                $user->save();
                $user->metric->uptime_time_end = null;
                $user->metric->block_height_average_time_end = null;
                $user->metric->update_responsiveness_time_end = null;
                $user->metric->uptime_time_start = null;
                $user->metric->block_height_average_time_start = null;
                $user->metric->update_responsiveness_time_start = null;
                $user->metric->save();
                continue;
            }
            if ($user->node_status != 'Probation' && $user->node_status != 'Pulled') {
                if ($user->metric->uptime < $uptimeProbationStart) {
                    $user->node_status = 'Probation';
                    $user->metric->uptime_time_start = now();
                    $user->metric->uptime_time_end =  Carbon::now('UTC')->addHours($uptimeTime);
                }
                if ($user->metric->block_height_average < $blockHeightProbationStart) {
                    $user->node_status = 'Probation';
                    $user->metric->block_height_average_time_start = now();
                    $user->metric->block_height_average_time_end =  Carbon::now('UTC')->addHours($blockHeightTime);
                }

                if ($user->metric->update_responsiveness < $updateResponsivenessProbationStart) {
                    $user->node_status = 'Probation';
                    $user->metric->update_responsiveness_time_start = now();
                    $user->metric->update_responsiveness_time_end =  Carbon::now('UTC')->addHours($updateResponsivenessTime);
                }
                $user->save();
                $user->metric->save();
                continue;
            }
            if($user->node_status == 'Probation') {
                if($user->metric->uptime_time_end <= $now && $user->metric->uptime < $uptimeProbationStart) {
                    $user->node_status = 'Pulled';
                }
                if($user->metric->block_height_average_time_end <= $now && $user->metric->block_height_average < $blockHeightProbationStart) {
                    $user->node_status = 'Pulled';
                }
                if($user->metric->update_responsiveness_time_end <= $now && $user->metric->update_responsiveness < $updateResponsivenessProbationStart) {
                    $user->node_status = 'Pulled';
                }
                $user->save();
            }
        }
    }
}
