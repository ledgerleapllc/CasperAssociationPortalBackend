<?php

namespace App\Console\Commands;

use App\Services\NodeHelper;

use App\Models\Setting;
use App\Models\DailyEarning;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;

/*
*** New Updates
Function that have also changed:
UserController::getVerifiedMembers()
UserController::getMembers()
UserController::getMemberDetail()
UserController::getListNodes()
UserController::getEarningByNode()
MetricController::getMetric()
MetricController::getMetricUser()
MetricController::getMetricUserByNodeName()
*/

class HistoricalData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'historical-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'get historical node data';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $nodeHelper         = new NodeHelper();
        $port8888_responses = array();

        /*
        casper-client get-auction-info --node-address http://18.219.70.138:7777/rpc -b e549a8703cb3bdf2c8e11a7cc72592c3043fe4490737064c6fd9da30cc0d4f36 | jq .result.auction_state.era_validators | jq .[0].era_id

        011117189c666f81c5160cd610ee383dc9b2d0361f004934754d39752eedc64957

        34  seconds per era
        136 seconds per era

        ~100 block per era
        */

        $get_block      = 'casper-client get-block ';
        $get_auction    = 'casper-client get-auction-info ';
        $node_arg       = '--node-address http://18.219.70.138:7777/rpc ';

        $json           = shell_exec($get_block.$node_arg);
        $json           = json_decode($json);
        $current_era    = (int)($json->result->block->header->era_id ?? 0);
        // $historic_era   = 4135; // pre-calculated
        // $historic_block = 614408; // pre-calculated

        $historic_era   = DB::select("
            SELECT era_id
            FROM all_node_data2
            ORDER BY era_id DESC
            LIMIT 1
        ");
        $historic_era   = (int)($historic_era[0]->era_id ?? 0);
        info('historic_era: '.$historic_era);
        $blocks_per_era = 100;
        $historic_block = $blocks_per_era * $historic_era;
        info('historic_block: '.$historic_block);
        $test_era       = 0;

        while ($test_era < $historic_era) {
            $json       = shell_exec($get_block.$node_arg.'-b '.$historic_block);
            $json       = json_decode($json);
            $test_era   = (int)($json->result->block->header->era_id ?? 0);

            if ($test_era < $historic_era) {
                $era_diff = $historic_era - $test_era;

                if ($era_diff < 0) {
                    $blocks_per_era = (int)($blocks_per_era / 2);
                }

                $historic_block += ($blocks_per_era * $era_diff);
                info('Using historic_block: '.$historic_block);
            }
        }

        while ($current_era > $historic_era) {
            // first see if we have this era's auction info
            $node_data = DB::select("
                SELECT era_id
                FROM all_node_data2
                WHERE era_id = $historic_era
            ");
            $node_data = (int)($node_data[0]->era_id ?? 0);

            if ($node_data) {
                // era's auction info exists. do not need to fetch.
                info('Already have era '.$historic_era.' data. skipping');
                $historic_era += 1;
            } else {
                // decrease block height and check for new switch block
                info('Checking block '.$historic_block.' for era '.$historic_era);
                $command      = $get_block.$node_arg.'-b '.$historic_block;
                $switch_block = shell_exec($command);
                $json         = json_decode($switch_block);
                $era_id       = $json->result->block->header->era_id ?? 0;
                $block_hash   = $json->result->block->hash ?? '';

                if ($era_id == $historic_era) {
                    // start timer
                    $start_time = (int)time();

                    // get auction info for this new detected era switch
                    info($era_id.' '.$block_hash);
                    $historic_era   += 1;
                    $command         = $get_auction.$node_arg.'-b '.$block_hash;
                    $auction_info    = shell_exec($command);


                    // very large object. aprx 10MB
                    $decoded_response       = json_decode($auction_info);
                    $auction_state          = $decoded_response->result->auction_state ?? array();
                    $bids                   = $auction_state->bids ?? array();

                    // get era ID
                    $era_validators         = $auction_state->era_validators ?? array();
                    $current_era_validators = $era_validators[0] ?? array();
                    $next_era_validators    = $era_validators[1] ?? array();
                    $current_era_id         = (int)($current_era_validators->era_id ?? 0);
                    $next_era_id            = (int)($next_era_validators->era_id ?? 0);

                    info('Data aquired for era: '.$current_era_id);

                    // set MBS array. minimum bid slot amount
                    $MBS_arr = array();

                    // get global uptimes from MAKE
                    $global_uptime = $nodeHelper->retrieveGlobalUptime($current_era_id);


                    // Set validator key object
                    $data = array(
                        "era_id"     => $era_id,
                        "validators" => array()
                    );

                    // loop auction era
                    info('Looping auction era - Appending uptime, bid, and daily earnings data');
                    foreach ($bids as $b) {
                        $public_key               = strtolower($b->public_key ?? 'nill');
                        $bid                      = $b->bid ?? array();

                        // get self
                        $self_staked_amount       = (int)($bid->staked_amount ?? 0);
                        $delegation_rate          = (int)($bid->delegation_rate ?? 0);
                        $bid_inactive             = (int)($bid->inactive ?? false);

                        // calculate total stake, delegators + self stake
                        $delegators               = (array)($bid->delegators ?? array());
                        $delegators_count         = count($delegators);
                        $delegators_staked_amount = 0;

                        foreach ($delegators as $delegator) {
                            $delegators_staked_amount += (int)($delegator->staked_amount ?? 0);
                        }

                        // convert and calculate stake amounts
                        $delegators_staked_amount = (int)($delegators_staked_amount / 1000000000);
                        $self_staked_amount       = (int)($self_staked_amount / 1000000000);
                        $total_staked_amount      = $delegators_staked_amount + $self_staked_amount;

                        // append to MBS array and pluck 100th place later
                        $MBS_arr[$public_key]     = $total_staked_amount;

                        // get node uptime from MAKE object
                        $uptime = 0;

                        foreach ($global_uptime as $uptime_array) {
                            $fvid = strtolower($uptime_array->public_key ?? '');

                            if($fvid == $public_key) {
                                $uptime = (float)($uptime_array->average_score ?? 0);
                                break;
                            }
                        }

                        // define DB insert object for all public keys in this era
                        $data["validators"][$public_key]   = array(
                            "public_key"                   => $public_key,
                            "uptime"                       => $uptime,
                            "current_era_weight"           => 0,
                            "next_era_weight"              => 0,
                            "in_current_era"               => 0,
                            "in_next_era"                  => 0,
                            "in_auction"                   => 1,
                            "bid_delegators_count"         => $delegators_count,
                            "bid_delegation_rate"          => $delegation_rate,
                            "bid_inactive"                 => $bid_inactive,
                            "bid_self_staked_amount"       => $self_staked_amount,
                            "bid_delegators_staked_amount" => $delegators_staked_amount,
                            "bid_total_staked_amount"      => $total_staked_amount,
                            "port8888_peers"               => 0,
                            "port8888_build_version"       => "",
                            "port8888_next_upgrade"        => ""
                        );

                        // look for existing peer by public key for port 8888 data
                        foreach ($port8888_responses as $port8888data) {
                            $our_public_signing_key = strtolower($port8888data['our_public_signing_key'] ?? '');

                            if ($our_public_signing_key == $public_key) {
                                // found in peers
                                $peer_count     = (int)($port8888data['peer_count'] ?? 0);
                                $era_id         = (int)($port8888data['last_added_block_info']['era_id'] ?? 0);
                                $block_height   = (int)($port8888data['last_added_block_info']['height'] ?? 0);
                                $build_version  = $port8888data['build_version'] ?? '1.0.0';
                                $build_version  = explode('-', $build_version)[0];
                                $chainspec_name = $port8888data['chainspec_name'] ?? 'casper';
                                $next_upgrade   = $port8888data['next_upgrade']['protocol_version'] ?? '';

                                break;
                            }
                        }
                    }

                    // loop current era
                    $current_validator_weights = $current_era_validators->validator_weights ?? array();
                    info('Appending current era validator weights');

                    foreach ($current_validator_weights as $v) {
                        $public_key = strtolower($v->public_key ?? '');
                        $weight     = (int)($v->weight / 1000000000 ?? 0);

                        if (isset($data["validators"][$public_key])) {
                            $data
                                ["validators"]
                                [$public_key]
                                ["current_era_weight"] = $weight;

                            $data
                                ["validators"]
                                [$public_key]
                                ["in_current_era"] = 1;
                        } else {
                            $data["validators"][$public_key] = array(
                                "public_key"                   => $public_key,
                                "uptime"                       => 0,
                                "current_era_weight"           => $weight,
                                "next_era_weight"              => 0,
                                "in_current_era"               => 1,
                                "in_next_era"                  => 0,
                                "in_auction"                   => 0,
                                "bid_delegators_count"         => 0,
                                "bid_delegation_rate"          => 0,
                                "bid_inactive"                 => 1,
                                "bid_self_staked_amount"       => 0,
                                "bid_delegators_staked_amount" => 0,
                                "bid_total_staked_amount"      => 0,
                                "port8888_peers"               => 0,
                                "port8888_build_version"       => "",
                                "port8888_next_upgrade"        => ""
                            );
                        }
                    }

                    // loop next era
                    $next_validator_weights = $next_era_validators->validator_weights ?? array();
                    info('Appending next era validator weights');

                    foreach ($next_validator_weights as $v) {
                        $public_key = $v->public_key ?? '';
                        $weight     = (int)($v->weight / 1000000000 ?? 0);

                        if (isset($data["validators"][$public_key])) {
                            $data
                                ["validators"]
                                [$public_key]
                                ["next_era_weight"] = $weight;

                            $data
                                ["validators"]
                                [$public_key]
                                ["in_next_era"] = 1;
                        } else {
                            $data["validators"][$public_key] = array(
                                "public_key"                   => $public_key,
                                "uptime"                       => 0,
                                "current_era_weight"           => $weight,
                                "next_era_weight"              => 0,
                                "in_current_era"               => 1,
                                "in_next_era"                  => 0,
                                "in_auction"                   => 0,
                                "bid_delegators_count"         => 0,
                                "bid_delegation_rate"          => 0,
                                "bid_inactive"                 => 1,
                                "bid_self_staked_amount"       => 0,
                                "bid_delegators_staked_amount" => 0,
                                "bid_total_staked_amount"      => 0,
                                "port8888_peers"               => 0,
                                "port8888_build_version"       => "",
                                "port8888_next_upgrade"        => ""
                            );
                        }
                    }

                    // Primary DB insertion (time consuming)
                    info('Saving validator objects to DB...');
                    $created_at = Carbon::now('UTC');

                    foreach ($data["validators"] as $v) {
                        DB::table('all_node_data2')->insert(
                            array(
                                'public_key'                   => $v["public_key"],
                                'era_id'                       => $data["era_id"],
                                'uptime'                       => $v["uptime"],
                                'current_era_weight'           => $v["current_era_weight"],
                                'next_era_weight'              => $v["next_era_weight"],
                                'in_current_era'               => $v["in_current_era"],
                                'in_next_era'                  => $v["in_next_era"],
                                'in_auction'                   => $v["in_auction"],
                                'bid_delegators_count'         => $v["bid_delegators_count"],
                                'bid_delegation_rate'          => $v["bid_delegation_rate"],
                                'bid_inactive'                 => $v["bid_inactive"],
                                'bid_self_staked_amount'       => $v["bid_self_staked_amount"],
                                'bid_delegators_staked_amount' => $v["bid_delegators_staked_amount"],
                                'bid_total_staked_amount'      => $v["bid_total_staked_amount"],
                                'port8888_peers'               => $v["port8888_peers"],
                                'port8888_build_version'       => $v["port8888_build_version"],
                                'port8888_next_upgrade'        => $v["port8888_next_upgrade"],
                                'created_at'                   => $created_at
                            )
                        );

                        // save current stake amount to daily earnings table
                        $earning = new DailyEarning();
                        $earning->node_address       = $public_key;
                        $earning->self_staked_amount = (int)$self_staked_amount;
                        $earning->created_at         = Carbon::now('UTC');
                        $earning->save();

                        // get difference between current self stake and yesterdays self stake
                        // $get_earning = DailyEarning::where('node_address', $public_key)
                        //     ->where('created_at', '>', Carbon::now('UTC')->subHours(24))
                        //     ->orderBy('created_at', 'asc')
                        //     ->first();

                        // $yesterdays_self_staked_amount = (int)($get_earning->self_staked_amount ?? 0);
                        // $daily_earning = $self_staked_amount - $yesterdays_self_staked_amount;

                        info($v["public_key"].' - done');
                    }

                    // find MBS
                    info('Finding MBS for this era');
                    rsort($MBS_arr);
                    $MBS = 0;

                    if (count($MBS_arr) > 0) {
                        $MBS = (float)($MBS_arr[99] ?? $MBS_arr[count($MBS_arr) - 1]);
                    }

                    // save MBS in new table by current_era
                    $check = DB::select("
                        SELECT mbs
                        FROM mbs
                        WHERE era_id = $current_era_id
                    ");
                    $check = $check[0] ?? null;

                    if (!$check) {
                        DB::table('mbs')->insert(
                            array(
                                'era_id'     => $current_era_id,
                                'mbs'        => $MBS,
                                'created_at' => $created_at
                            )
                        );
                    } else {
                        DB::table('mbs')
                            ->where('era_id', $current_era_id)
                            ->update(
                            array(
                                'mbs'        => $MBS,
                                'updated_at' => $created_at
                            )
                        );
                    }

                    // DailyEarning garbage cleanup
                    DailyEarning::where(
                        'created_at', 
                        '<', 
                        Carbon::now('UTC')->subDays(90)
                    )->delete();

                    // end timer
                    $end_time = (int)time();

                    info("Time spent on era: ".($end_time - $start_time));
                }

                $historic_block += 1;
            }
        }


















        
        /*
        OLD



        $get_block      = 'casper-client get-block ';
        $get_auction    = 'casper-client get-auction-info ';
        $node_arg       = '--node-address http://18.219.70.138:7777/rpc ';

        $json           = shell_exec($get_block.$node_arg);
        $json           = json_decode($json);
        $current_era    = (int)($json->result->block->header->era_id ?? 0);
        // $historic_era   = 4135; // pre-calculated
        // $historic_block = 614408; // pre-calculated
        $historic_era   = 4874; // bookmark
        $historic_block = 775014; // bookmark
        // $historic_era   = 5701; // bookmark
        // $historic_block = 955831; // bookmark

        // current seconds per era: 296

        $i = 0;

        while ($current_era > $historic_era) {
        // while ($i < 5000) {
            // first see if we have this era's auction info
            $node_data = DB::select("
                SELECT era_id
                FROM all_node_data
                WHERE era_id = $historic_era
            ");
            $node_data = (int)($node_data[0]->era_id ?? 0);

            if ($node_data) {
                // era's auction info exists. do not need to fetch.
                info('Already have era '.$historic_era.' data. skipping');
                $historic_era += 1;
            } else {
                // decrease block height and check for new switch block
                info('Checking block '.$historic_block.' for era '.$historic_era);
                $command      = $get_block.$node_arg.'-b '.$historic_block;
                $switch_block = shell_exec($command);
                $json         = json_decode($switch_block);
                $era_id       = $json->result->block->header->era_id ?? 0;
                $block_hash   = $json->result->block->hash ?? '';

                if ($era_id == $historic_era) {
                    // start timer
                    $start_time = (int)time();

                    // get auction info for this new detected era switch
                    info($era_id.' '.$block_hash);
                    $historic_era   += 1;
                    $command         = $get_auction.$node_arg.'-b '.$block_hash;
                    $auction_info    = shell_exec($command);


                    // very large object. aprx 10MB
                    $decoded_response       = json_decode($auction_info);
                    $auction_state          = $decoded_response->result->auction_state ?? array();
                    $bids                   = $auction_state->bids ?? array();

                    // get era ID
                    $era_validators         = $auction_state->era_validators ?? array();
                    $current_era_validators = $era_validators[0] ?? array();
                    $next_era_validators    = $era_validators[1] ?? array();
                    $current_era_id         = (int)($current_era_validators->era_id ?? 0);
                    $next_era_id            = (int)($next_era_validators->era_id ?? 0);

                    info('Data aquired for era: '.$current_era_id);

                    // loop current era
                    $current_validator_weights = $current_era_validators->validator_weights ?? array();
                    info('Saving current era validator weights');

                    foreach ($current_validator_weights as $v) {
                        $public_key = $v->public_key ?? '';
                        $weight     = (int)($v->weight / 1000000000 ?? 0);

                        $check = DB::select("
                            SELECT public_key, era_id
                            FROM all_node_data
                            WHERE public_key = '$public_key'
                            AND era_id = $current_era_id
                        ");
                        $check = $check[0] ?? null;

                        if (!$check) {
                            DB::table('all_node_data')->insert(
                                array(
                                    'public_key'         => $public_key,
                                    'era_id'             => $current_era_id,
                                    'current_era_weight' => $weight,
                                    'in_curent_era'      => 1,
                                    'created_at'         => Carbon::now('UTC')
                                )
                            );
                        }
                    }

                    // loop next era
                    $next_validator_weights = $next_era_validators->validator_weights ?? array();
                    info('Saving next era validator weights');

                    foreach ($next_validator_weights as $v) {
                        $public_key = $v->public_key ?? '';
                        $weight     = (int)($v->weight / 1000000000 ?? 0);

                        $check = DB::select("
                            SELECT public_key, era_id
                            FROM all_node_data
                            WHERE public_key = '$public_key'
                            AND era_id = $current_era_id
                        ");
                        $check = $check[0] ?? null;

                        if (!$check) {
                            // info('Saved: '.$public_key);
                            DB::table('all_node_data')->insert(
                                array(
                                    'public_key'      => $public_key,
                                    'era_id'          => $current_era_id,
                                    'next_era_weight' => $weight,
                                    'in_next_era'     => 1
                                )
                            );
                        } else {
                            // info('Updated: '.$public_key);
                            DB::table('all_node_data')
                                ->where('public_key',    $public_key)
                                ->where('era_id',        $current_era_id)
                                ->update(
                                array(
                                    'next_era_weight' => $weight,
                                    'in_next_era'     => 1
                                )
                            );
                        }
                    }

                    // set MBS array. minimum bid slot amount
                    $MBS_arr = array();

                    // get global uptimes from MAKE
                    $global_uptime = $nodeHelper->retrieveGlobalUptime($current_era_id);

                    // loop auction era
                    info('Looping auction era - Saving uptime, bid, and daily earnings data');
                    foreach ($bids as $b) {
                        $public_key               = strtolower($b->public_key ?? 'nill');
                        $bid                      = $b->bid ?? array();

                        // get self
                        $self_staked_amount       = (int)($bid->staked_amount ?? 0);
                        $delegation_rate          = (int)($bid->delegation_rate ?? 0);
                        $bid_inactive             = (int)($bid->inactive ?? false);

                        // calculate total stake, delegators + self stake
                        $delegators               = (array)($bid->delegators ?? array());
                        $delegators_count         = count($delegators);
                        $delegators_staked_amount = 0;

                        foreach ($delegators as $delegator) {
                            $delegators_staked_amount += (int)($delegator->staked_amount ?? 0);
                        }

                        // convert and calculate stake amounts
                        $delegators_staked_amount = (int)($delegators_staked_amount / 1000000000);
                        $self_staked_amount       = (int)($self_staked_amount / 1000000000);
                        $total_staked_amount      = $delegators_staked_amount + $self_staked_amount;

                        // append to MBS array and pluck 100th place later
                        $MBS_arr[$public_key]     = $total_staked_amount;

                        // get node uptime from MAKE object
                        $uptime = 0;

                        foreach ($global_uptime as $uptime_array) {
                            $fvid = strtolower($uptime_array->public_key ?? '');

                            if($fvid == $public_key) {
                                $uptime = (float)($uptime_array->average_score ?? 0);
                                break;
                            }
                        }

                        // record auction bid data for this node
                        $check = DB::select("
                            SELECT public_key, era_id
                            FROM all_node_data
                            WHERE public_key = '$public_key'
                            AND era_id = $current_era_id
                        ");
                        $check = $check[0] ?? null;

                        if (!$check) {
                            // info('Saved: '.$public_key);
                            DB::table('all_node_data')->insert(
                                array(
                                    'public_key'                   => $public_key,
                                    'era_id'                       => $current_era_id,
                                    'uptime'                       => $uptime,
                                    'in_auction'                   => 1,
                                    'bid_delegators_count'         => $delegators_count,
                                    'bid_delegation_rate'          => $delegation_rate,
                                    'bid_inactive'                 => $bid_inactive,
                                    'bid_self_staked_amount'       => $self_staked_amount,
                                    'bid_delegators_staked_amount' => $delegators_staked_amount,
                                    'bid_total_staked_amount'      => $total_staked_amount
                                )
                            );
                        } else {
                            // info('Updated: '.$public_key);
                            DB::table('all_node_data')
                                ->where('public_key',                 $public_key)
                                ->where('era_id',                     $current_era_id)
                                ->update(
                                array(
                                    'uptime'                       => $uptime,
                                    'in_auction'                   => 1,
                                    'bid_delegators_count'         => $delegators_count,
                                    'bid_delegation_rate'          => $delegation_rate,
                                    'bid_inactive'                 => $bid_inactive,
                                    'bid_self_staked_amount'       => $self_staked_amount,
                                    'bid_delegators_staked_amount' => $delegators_staked_amount,
                                    'bid_total_staked_amount'      => $total_staked_amount
                                )
                            );
                        }

                        // save current stake amount to daily earnings table
                        $earning = new DailyEarning();
                        $earning->node_address       = $public_key;
                        $earning->self_staked_amount = (int)$self_staked_amount;
                        $earning->created_at         = Carbon::now('UTC');
                        $earning->save();

                        // get difference between current self stake and yesterdays self stake
                        $get_earning = DailyEarning::where('node_address', $public_key)
                            ->where('created_at', '>', Carbon::now('UTC')->subHours(24))
                            ->orderBy('created_at', 'asc')
                            ->first();

                        $yesterdays_self_staked_amount = (int)($get_earning->self_staked_amount ?? 0);
                        $daily_earning = $self_staked_amount - $yesterdays_self_staked_amount;

                        // look for existing peer by public key for port 8888 data
                        foreach ($port8888_responses as $port8888data) {
                            $our_public_signing_key = strtolower($port8888data['our_public_signing_key'] ?? '');

                            if ($our_public_signing_key == $public_key) {
                                // found in peers
                                $peer_count     = (int)($port8888data['peer_count'] ?? 0);
                                $era_id         = (int)($port8888data['last_added_block_info']['era_id'] ?? 0);
                                $block_height   = (int)($port8888data['last_added_block_info']['height'] ?? 0);
                                $build_version  = $port8888data['build_version'] ?? '1.0.0';
                                $build_version  = explode('-', $build_version)[0];
                                $chainspec_name = $port8888data['chainspec_name'] ?? 'casper';
                                $next_upgrade   = $port8888data['next_upgrade']['protocol_version'] ?? '';

                                // record port 8888 data if available
                                $check = DB::select("
                                    SELECT public_key, era_id
                                    FROM all_node_data
                                    WHERE public_key = '$public_key'
                                    AND era_id = $current_era_id
                                ");
                                $check = $check[0] ?? null;

                                if (!$check) {
                                    DB::table('all_node_data')->insert(
                                        array(
                                            'public_key'             => $public_key,
                                            'era_id'                 => $current_era_id,
                                            'port8888_peers'         => $peer_count,
                                            'port8888_era_id'        => $era_id,
                                            'port8888_block_height'  => $block_height,
                                            'port8888_build_version' => $build_version,
                                            'port8888_next_upgrade'  => $next_upgrade
                                        )
                                    );
                                } else {
                                    DB::table('all_node_data')
                                        ->where('public_key',           $public_key)
                                        ->where('era_id',               $current_era_id)
                                        ->update(
                                        array(
                                            'port8888_peers'         => $peer_count,
                                            'port8888_era_id'        => $era_id,
                                            'port8888_block_height'  => $block_height,
                                            'port8888_build_version' => $build_version,
                                            'port8888_next_upgrade'  => $next_upgrade
                                        )
                                    );
                                }

                                break;
                            }
                        }
                    }

                    // find MBS
                    info('Finding MBS for this era');
                    rsort($MBS_arr);
                    $MBS = 0;

                    if (count($MBS_arr) > 0) {
                        $MBS = (float)($MBS_arr[99] ?? $MBS_arr[count($MBS_arr) - 1]);
                    }

                    // save MBS in new table by current_era
                    $check = DB::select("
                        SELECT mbs
                        FROM mbs
                        WHERE era_id = $current_era_id
                    ");
                    $check = $check[0] ?? null;

                    if (!$check) {
                        DB::table('mbs')->insert(
                            array(
                                'era_id'     => $current_era_id,
                                'mbs'        => $MBS,
                                'created_at' => Carbon::now('UTC')
                            )
                        );
                    } else {
                        DB::table('mbs')
                            ->where('era_id', $current_era_id)
                            ->update(
                            array(
                                'mbs'      => $MBS,
                                'updated_at' => Carbon::now('UTC')
                            )
                        );
                    }

                    // Get settings for stable eras requirement
                    info('Getting settings');
                    $voting_eras_to_vote = DB::select("
                        SELECT value
                        FROM settings
                        WHERE name = 'voting_eras_to_vote'
                    ");
                    $a = (array)($voting_eras_to_vote[0] ?? array());
                    $voting_eras_to_vote = (int)($a['value'] ?? 0);

                    // Create setting if not exist
                    if (!$voting_eras_to_vote) {
                        $voting_eras_to_vote = 100;

                        DB::table('settings')->insert(
                            array(
                                'name'  => 'voting_eras_to_vote',
                                'value' => '100'
                            )
                        );
                    }

                    // Discover black marks - validator has sufficient stake, but failed to win slot.
                    $uptime_calc_size = DB::select("
                        SELECT value
                        FROM settings
                        WHERE name = 'uptime_calc_size'
                    ");
                    $a = (array)($uptime_calc_size[0] ?? array());
                    $uptime_calc_size = (int)($a['value'] ?? 0);

                    // Create setting if not exist
                    if (!$uptime_calc_size) {
                        $uptime_calc_size = 360;

                        DB::table('settings')->insert(
                            array(
                                'name'  => 'uptime_calc_size',
                                'value' => '360'
                            )
                        );
                    }

                    $previous_era_id = $current_era_id - 1;
                    $previous_mbs    = DB::select("
                        SELECT mbs
                        FROM mbs
                        WHERE era_id = $previous_era_id
                    ");
                    $previous_mbs    = (float)($previous_mbs[0]->mbs ?? 0);

                    info('Looping auction era object again to find bad marks and historical performance');
                    foreach ($bids as $b) {
                        $public_key  = strtolower($b->public_key ?? 'nill');
                        info('Complete: '.$public_key);

                        // last era
                        // $previous_stake = DB::select("
                        //     SELECT bid_total_staked_amount
                        //     FROM all_node_data
                        //     WHERE public_key = '$public_key'
                        //     AND era_id = $previous_era_id
                        // ");
                        // $a = (array)($previous_stake[0] ?? array());
                        // $previous_stake = (int)($a['bid_total_staked_amount'] ?? 0);

                        // current era
                        // $next_era_weight = DB::select("
                        //     SELECT next_era_weight
                        //     FROM all_node_data
                        //     WHERE public_key = '$public_key'
                        //     AND era_id = $current_era_id
                        // ");
                        // $a = (array)($next_era_weight[0] ?? array());
                        // $next_era_weight = (int)($a['next_era_weight'] ?? 0);


                        // last era and current era
                        $multi_query = DB::select("
                            SELECT 
                            era_id, bid_total_staked_amount, next_era_weight
                            FROM all_node_data
                            WHERE public_key = '$public_key'
                            AND (
                                era_id = $previous_era_id OR
                                era_id = $current_era_id
                            )
                        ");

                        $previous_stake = 0;
                        $next_era_weight = 0;

                        if ($multi_query) {
                            foreach ($multi_query as $result) {
                                if ($result->era_id == $previous_era_id) {
                                    $previous_stake = (int)($result->bid_total_staked_amount);
                                }

                                if ($result->era_id == $current_era_id) {
                                    $next_era_weight = (int)($result->next_era_weight);
                                }
                            }
                        }


                        // Calculate historical_performance from past $uptime_calc_size eras
                        $missed = 0;
                        $in_curent_eras = DB::select("
                            SELECT in_curent_era
                            FROM all_node_data
                            WHERE public_key = '$public_key'
                            ORDER BY era_id DESC
                            LIMIT $uptime_calc_size
                        ");

                        $in_curent_eras = $in_curent_eras ? $in_curent_eras : array();
                        $window         = $in_curent_eras ? count($in_curent_eras) : 0;

                        foreach ($in_curent_eras as $c) {
                            $in = (bool)($c->in_curent_era ?? 0);

                            if (!$in) {
                                $missed += 1;
                            }
                        }

                        // get node uptime from MAKE object
                        $uptime = 0;

                        foreach ($global_uptime as $uptime_array) {
                            $fvid = strtolower($uptime_array->public_key ?? '');

                            if($fvid == $public_key) {
                                $uptime = (float)($uptime_array->average_score ?? 0);
                                break;
                            }
                        }

                        $historical_performance = ($uptime * ($window - $missed)) / $window;
                        $past_era               = $current_era_id - $voting_eras_to_vote;
                        $bad_mark               = 0;

                        if ($past_era < 0) {
                            $past_era = 0;
                        }

                        if (
                            $previous_stake > $previous_mbs &&
                            !$next_era_weight
                        ) {
                            // black mark
                            $bad_mark = 1;
                        }

                        // Update stability
                        $unstable = DB::select("
                            SELECT era_id
                            FROM all_node_data
                            WHERE public_key = '$public_key'
                            AND era_id > $past_era
                            AND (
                                bad_mark     = 1 OR
                                bid_inactive = 1
                            )
                        ");

                        $stability_count = DB::select("
                            SELECT era_id
                            FROM all_node_data
                            WHERE public_key = '$public_key'
                            AND era_id > $past_era
                        ");

                        $unstable        = (bool)($unstable);
                        $stable          = (int)(!$unstable);
                        $stability_count = $stability_count ? count($stability_count) : 0;

                        if ($stability_count < $voting_eras_to_vote) {
                            $stable = 0;
                        }

                        DB::table('all_node_data')
                            ->where('public_key', $public_key)
                            ->where('era_id',     $current_era_id)
                            ->update(
                            array(
                                'bad_mark'               => $bad_mark,
                                'stable'                 => $stable,
                                'historical_performance' => $historical_performance
                            )
                        );
                    }

                    // DailyEarning garbage cleanup
                    DailyEarning::where(
                        'created_at', 
                        '<', 
                        Carbon::now('UTC')->subDays(90)
                    )->delete();

                    // end timer
                    $end_time = (int)time();

                    info("Time spent on era: ".($end_time - $start_time));
                }

                $historic_block += 1;
            }

            $i += 1;
        }

        */
        info('done');
    }
}