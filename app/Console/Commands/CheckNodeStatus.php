<?php

namespace App\Console\Commands;

use App\Models\MonitoringCriteria;
use App\Models\User;
use App\Services\NodeHelper;
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
            $blockHeightTime = (float) $blockHeight->given_to_correct_value * 7 * 24;
        } else if ($blockHeight->given_to_correct_unit == 'Days') {
            $blockHeightTime = (float) $blockHeight->given_to_correct_value * 24;
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
        $nodeHelper = new NodeHelper();
        $now =  Carbon::now('UTC');
        $users = User::where('role', 'member')->where('banned', 0)->with(['metric', 'nodeInfo'])->get();
        
        foreach ($users as $user) {
            $user->node_status = 'Online';
            $user->save();

            $nodeInfo = $user->nodeInfo ? $user->nodeInfo : $user->metric;
            if (!$nodeInfo || !$user->node_verified_at || !$user->letter_verified_at || !$user->signature_request_id) {
                $user->node_status = null;
                $user->save();
                continue;
            }
            /*
            if ($nodeHelper->validateValidatorId($user->public_address_node) != true) {
                $user->node_status = null;
                $user->save();
                continue;
            }
            */
            if ($user->is_fail_node == 1) {
                $user->node_status = 'Offline';
                $user->save();
                continue;
            }
            $nodeInfo->uptime = $nodeInfo->uptime ? $nodeInfo->uptime : 0;
            $nodeInfo->block_height_average = $nodeInfo->block_height_average ? $nodeInfo->block_height_average : 0;
            $nodeInfo->update_responsiveness = $nodeInfo->update_responsiveness ? $nodeInfo->update_responsiveness : 100;
            if (
                $nodeInfo->uptime >= $uptimeProbationStart && $nodeInfo->block_height_average >= $blockHeightProbationStart
                && $nodeInfo->update_responsiveness >= $updateResponsivenessProbationStart
            ) {
                $user->node_status = 'Online';
                $user->save();
                $nodeInfo->uptime_time_end = null;
                $nodeInfo->block_height_average_time_end = null;
                $nodeInfo->update_responsiveness_time_end = null;
                $nodeInfo->uptime_time_start = null;
                $nodeInfo->block_height_average_time_start = null;
                $nodeInfo->update_responsiveness_time_start = null;
                $nodeInfo->save();
                continue;
            }
            if ($user->node_status != 'Probation' && $user->node_status != 'Pulled') {
                if ($nodeInfo->uptime < $uptimeProbationStart) {
                    $user->node_status = 'Probation';
                    $nodeInfo->uptime_time_start = now();
                    $nodeInfo->uptime_time_end =  Carbon::now('UTC')->addHours($uptimeTime);
                }
                if ($nodeInfo->block_height_average < $blockHeightProbationStart) {
                    $user->node_status = 'Probation';
                    $nodeInfo->block_height_average_time_start = now();
                    $nodeInfo->block_height_average_time_end =  Carbon::now('UTC')->addHours($blockHeightTime);
                }

                if ($nodeInfo->update_responsiveness < $updateResponsivenessProbationStart) {
                    $user->node_status = 'Probation';
                    $nodeInfo->update_responsiveness_time_start = now();
                    $nodeInfo->update_responsiveness_time_end =  Carbon::now('UTC')->addHours($updateResponsivenessTime);
                }
                $user->save();
                $nodeInfo->save();
                continue;
            }
            if($user->node_status == 'Probation') {
                if($nodeInfo->uptime_time_end <= $now && $nodeInfo->uptime < $uptimeProbationStart) {
                    $user->node_status = 'Pulled';
                }
                if($nodeInfo->block_height_average_time_end <= $now && $nodeInfo->block_height_average < $blockHeightProbationStart) {
                    $user->node_status = 'Pulled';
                }
                if($nodeInfo->update_responsiveness_time_end <= $now && $nodeInfo->update_responsiveness < $updateResponsivenessProbationStart) {
                    $user->node_status = 'Pulled';
                }
                $user->save();
            }
        }
    }
}
