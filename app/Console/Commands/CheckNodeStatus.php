<?php

namespace App\Console\Commands;

use App\Console\Helper;

use App\Models\MonitoringCriteria;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\NodeInfo;
use App\Models\AllNodeData2;

use Carbon\Carbon;

use Illuminate\Support\Facades\DB;
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
        $settings = Helper::getSettings();
        $current_era_id = Helper::getCurrentERAId();

        $now = Carbon::now('UTC');
        $users = User::with(['addresses', 'profile'])
                        ->where('role', 'member')
                        ->where('banned', 0)
                        ->get();

        foreach ($users as $user) {
            $addresses = $user->addresses ?? [];

            if (
                !$addresses ||
                count($addresses) == 0 ||
                !$user->node_verified_at ||
                !$user->letter_verified_at ||
                !$user->signature_request_id
            ) {
                $user->node_status = null;
                $user->save();

                if ($user->profile) {
                    $user->profile->extra_status = null;
                    $user->profile->save();
                }

                if ($addresses && count($addresses) > 0) {
                    foreach ($addresses as $address) {
                        $address->node_status = null;
                        $address->extra_status = null;
                        $address->save();
                    }
                }
            } else {
                $hasOnline = $hasOnProbation = false;
                foreach ($addresses as $address) {
                    $public_address_node = strtolower($address->public_address_node);

                    $temp = AllNodeData2::select(['id', 'uptime'])
                                        ->where('public_key', $public_address_node)
                                        ->where('era_id', $current_era_id)
                                        ->where('bid_inactive', 0)
                                        ->where('in_auction', 1)
                                        ->where('in_current_era', 1)
                                        ->first();
                    if ($temp) {
                        $hasOnline = true;
                        $address->node_status = 'Online';
                        $address->extra_status = null;
                        $address->save();

                        // historical performance
                        if (
                            isset($settings['uptime_probation']) && 
                            (float) $settings['uptime_probation'] > 0
                        ) {
                            $uptime_calc_size = $settings['uptime_calc_size'] ?? 1;
                            $uptime_calc_size = (int) $uptime_calc_size;

                            $uptime = (float) $temp->uptime;
                            if ($uptime_calc_size > 0) {
                                $missed = 0;
                                $in_current_eras = DB::select("
                                    SELECT in_current_era
                                    FROM all_node_data2
                                    WHERE public_key = '$public_address_node'
                                    ORDER BY era_id DESC
                                    LIMIT $uptime_calc_size
                                ");
                                $in_current_eras = $in_current_eras ? $in_current_eras : [];
                                $window = $in_current_eras ? count($in_current_eras) : 0;
                                foreach ($in_current_eras as $c) {
                                    $in = (bool) ($c->in_current_era ?? 0);
                                    if (!$in) {
                                        $missed += 1;
                                    }
                                }
                                $uptime = round((float) (($uptime * ($window - $missed)) / $window), 2);
                            }

                            if ($uptime < (float) $settings['uptime_probation']) {
                                $address->extra_status = 'On Probation';
                                $address->save();
                                $hasOnProbation = true;
                            }
                        }

                        // redmarks
                        if (
                            isset($settings['redmarks_revoke']) &&
                            (float) $settings['redmarks_revoke'] > 0
                        ) {
                            $redmarks_revoke_calc_size = (int)($settings['redmarks_revoke_calc_size'] ?? 1);
                            $window = $current_era_id - $redmarks_revoke_calc_size;

                            if ($window < 0) {
                                $window = 0;
                            }

                            $bad_marks = DB::select("
                                SELECT count(era_id) AS bad_marks
                                FROM all_node_data2
                                WHERE public_key = '$public_address_node'
                                AND era_id > $window
                                AND (
                                    in_current_era = 0 OR
                                    bid_inactive   = 1
                                )
                            ");
                            $bad_marks = $bad_marks[0] ?? [];
                            $bad_marks = (int)($bad_marks->bad_marks ?? 0);

                            if ($bad_marks > (int)$settings['redmarks_revoke']) {
                                $address->extra_status = 'Suspended';
                                $address->save();
                            }
                        }
                    } else {
                        $address->node_status = 'Offline';
                        $address->extra_status = null;
                        $address->save();
                    }
                }

                if ($hasOnline) {
                    $user->node_status = 'Online';
                    $user->save();
                } else {
                    $user->node_status = 'Offline';
                    $user->save();
                }

                if ($user->profile) {
                    if ($hasOnProbation) {
                        $user->profile->extra_status = 'On Probation';
                        $user->profile->save();
                    } else {
                        $user->profile->extra_status = null;
                        $user->profile->save();
                    }
                }
            }
        }
    }

    public function handleOld()
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
            $updateResponsivenessTime = (float) $updateResponsiveness->given_to_correct_value * 7 * 24;
        } else if ($updateResponsiveness->given_to_correct_unit == 'Days') {
            $updateResponsivenessTime = (float) $updateResponsiveness->given_to_correct_value * 24;
        } else {
            $updateResponsivenessTime = (float) $updateResponsiveness->given_to_correct_value;
        }

        $now = Carbon::now('UTC');
        $users = User::with(['addresses', 'metric', 'nodeInfo', 'profile'])
                        ->where('role', 'member')
                        ->where('banned', 0)
                        ->get();

        foreach ($users as $user) {
            $user->node_status = 'Online';
            $user->save();

            $addresses = $user->addresses ?? [];
            $nodeInfo = $user->nodeInfo ? $user->nodeInfo : $user->metric;

            if (
                !$nodeInfo || 
                !$addresses || 
                count($addresses) == 0 || 
                !$user->node_verified_at || 
                !$user->letter_verified_at || 
                !$user->signature_request_id
            ) {
                $user->node_status = null;
                $user->save();
            } else {
                $hasNotFailNode = false;
                foreach ($addresses as $address) {
                    $public_address_node = strtolower($address->public_address_node);
                    $nodeInfoAddress = NodeInfo::where('node_address', $public_address_node)->first();
                    if (!$nodeInfoAddress) {
                        $address->node_status = null;
                        $address->save();
                    }
                    if ($address->is_fail_node != 1) {
                        $hasNotFailNode = true;
                    }
                }

                if (!$hasNotFailNode) {
                    $user->node_status = 'Offline';
                    $user->save();
                } else {
                    // Begin For Each
                    foreach ($addresses as $userAddress) {
                        $public_address_node = strtolower($userAddress->public_address_node);
                        $nodeInfoAddress = NodeInfo::where('node_address', $public_address_node)->first();

                        if ($nodeInfoAddress) {
                            $nodeInfoAddress->uptime = $nodeInfoAddress->uptime ? $nodeInfoAddress->uptime : 0;
                            $nodeInfoAddress->block_height_average = $nodeInfoAddress->block_height_average ? $nodeInfoAddress->block_height_average : 0;
                            $nodeInfoAddress->update_responsiveness = $nodeInfoAddress->update_responsiveness ? $nodeInfoAddress->update_responsiveness : 100;
                            if (
                                $nodeInfoAddress->uptime >= $uptimeProbationStart &&
                                $nodeInfoAddress->block_height_average >= $blockHeightProbationStart &&
                                $nodeInfoAddress->update_responsiveness >= $updateResponsivenessProbationStart
                            ) {
                                $userAddress->node_status = 'Online';
                                $userAddress->save();

                                $nodeInfoAddress->uptime_time_start = null;
                                $nodeInfoAddress->uptime_time_end = null;

                                $nodeInfoAddress->block_height_average_time_start = null;
                                $nodeInfoAddress->block_height_average_time_end = null;

                                $nodeInfoAddress->update_responsiveness_time_start = null;
                                $nodeInfoAddress->update_responsiveness_time_end = null;

                                $nodeInfoAddress->save();
                            }
                        }

                        if (
                            isset($user->profile) && 
                            $user->profile && 
                            isset($user->profile->status) && 
                            $user->profile->status == 'approved' && 
                            $nodeInfoAddress
                        ) {
                            $userAddress->extra_status = null;
                            $userAddress->save();

                            if ($nodeInfoAddress->uptime < $uptimeProbationStart) {
                                $userAddress->extra_status = 'On Probation';

                                if(!$nodeInfoAddress->uptime_time_start) {
                                    $nodeInfoAddress->uptime_time_start = now();
                                }

                                if(!$nodeInfoAddress->uptime_time_end) {
                                    $nodeInfoAddress->uptime_time_end = Carbon::now('UTC')->addHours($uptimeTime);
                                }
                            }

                            if ($nodeInfoAddress->block_height_average < $blockHeightProbationStart) {
                                $userAddress->extra_status = 'On Probation';

                                if(!$nodeInfoAddress->block_height_average_time_start) {
                                    $nodeInfoAddress->block_height_average_time_start = now();
                                }

                                if(!$nodeInfoAddress->block_height_average_time_end) {
                                    $nodeInfoAddress->block_height_average_time_end = Carbon::now('UTC')->addHours($blockHeightTime);
                                }
                            }

                            if ($nodeInfoAddress->update_responsiveness < $updateResponsivenessProbationStart) {
                                $userAddress->extra_status = 'On Probation';

                                if(!$nodeInfoAddress->update_responsiveness_time_start) {
                                    $nodeInfoAddress->update_responsiveness_time_start = now();
                                }

                                if(!$nodeInfoAddress->update_responsiveness_time_end) {
                                    $nodeInfoAddress->update_responsiveness_time_end = Carbon::now('UTC')->addHours($updateResponsivenessTime);
                                }
                            }

                            $userAddress->save();
                            $nodeInfoAddress->save();

                            if ($userAddress->extra_status == 'On Probation') {
                                if ($nodeInfoAddress->uptime_time_end <= $now && $nodeInfoAddress->uptime < $uptimeProbationStart) {
                                    $userAddress->extra_status = 'Suspended';
                                }
                                if ($nodeInfoAddress->block_height_average_time_end <= $now && $nodeInfoAddress->block_height_average < $blockHeightProbationStart) {
                                    $userAddress->extra_status = 'Suspended';
                                }
                                if ($nodeInfoAddress->update_responsiveness_time_end <= $now && $nodeInfoAddress->update_responsiveness < $updateResponsivenessProbationStart) {
                                    $userAddress->extra_status = 'Suspended';
                                }
                                $userAddress->save();
                            }
                        }

                        $inactive = (bool)($nodeInfoAddress->inactive ?? false);

                        if($inactive) {
                            $userAddress->node_status = 'Offline';
                            $userAddress->extra_status = 'Suspended';
                            $userAddress->save();
                        }
                    }
                    // End For Each

                    // Begin For Each
                    $hasNotOfflineStatus = $hasNotSuspendedStatus = false;
                    foreach ($addresses as $userAddress) {
                        if ($userAddress->node_status != 'Offline') {
                            $hasNotOfflineStatus = true;
                            break;
                        }
                    }
                    foreach ($addresses as $userAddress) {
                        if ($userAddress->extra_status != 'Suspended') {
                            $hasNotSuspendedStatus = true;
                            break;
                        }
                    }

                    if (!$hasNotOfflineStatus) {
                        $user->node_status = 'Offline';
                        $user->save();
                    } else {
                        foreach ($addresses as $userAddress) {
                            if ($userAddress->node_status == 'Online') {
                                $user->node_status = 'Online';
                                $user->save();
                                break;
                            }
                        }
                        if ($user->node_status != 'Online') {
                            $user->node_status = null;
                            $user->save();
                        }
                    }

                    if (
                        !$hasNotSuspendedStatus && 
                        isset($user->profile) && 
                        $user->profile
                    ) {
                        $user->profile->extra_status = 'Suspended';
                        $user->profile->save();
                    } else if (isset($user->profile) && $user->profile) {
                        foreach ($addresses as $userAddress) {
                            if ($userAddress->extra_status == 'On Probation') {
                                $user->profile->extra_status = 'On Probation';
                                $user->save();
                                break;
                            }
                        }
                        if ($user->profile->extra_status != 'On Probation') {
                            $user->profile->extra_status = null;
                            $user->profile->save();
                        }
                    }
                    // End For Each
                }
            }
        }
    }
}