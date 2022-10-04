<?php

namespace App\Services;

use App\Models\KeyPeer;
use App\Models\Node;
use App\Models\NodeInfo;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\Setting;
use App\Models\DailyEarning;

use Carbon\Carbon;

use Exception;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\BadResponseException;

use Casper\Rpc\RpcClient;

class NodeHelper
{
    public function __construct()
    {
        // do nothing
    }

    public function decodePeers($__peers)
    {
        $decoded_peers = array();

        if ($__peers && gettype($__peers) == 'array') {
            foreach ($__peers as $__peer) {
                $address = $__peer['address'] ?? '';
                $address = explode(':', $address)[0];

                if ($address) {
                    $decoded_peers[] = $address;
                }
            }
        }
        return $decoded_peers;
    }

    public function retrieveGlobalUptime($this_era_id)
    {
        $total_data      = array();
        $event_store_url = 'https://event-store-api-clarity-mainnet.make.services/relative-average-validator-performances?limit=100&page=1&era_id='.(string)($this_era_id - 1);


        // make initial request, get response
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $event_store_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $json = curl_exec($ch);

        if (curl_errno($ch)) {
            return array();
        }

        try {
            $object = json_decode($json);
        } catch(Exception $e) {
            $object = (object)[];
        }


        // get total pages
        $page_count = (int)($object->pageCount ?? 0);


        // update total data object
        $data       = $object->data ?? array();
        $total_data = array_merge($total_data, $data);


        // iterate through remaining pages
        for ($i = 0; $i < $page_count; $i++) {
            if ($i != 0) {
                $j = $i + 1;
                $event_store_url = 'https://event-store-api-clarity-mainnet.make.services/relative-average-validator-performances?limit=100&page='.$j.'&era_id='.(string)($this_era_id - 1);
                curl_setopt($ch, CURLOPT_URL, $event_store_url);
                $json = curl_exec($ch);

                if (curl_errno($ch)) {
                    continue;
                }

                try {
                    $object = json_decode($json);
                } catch(Exception $e) {
                    $object = (object)[];
                }


                // update total data object
                $data       = $object->data ?? array();
                $total_data = array_merge($total_data, $data);
            }
            sleep(1);
        }

        curl_close($ch);

        return $total_data;
    }

    public function discoverPeers()
    {
        $port8888_responses = array();
        $http_protocol      = 'http://';
        $status_port        = ':8888/status';


        // Get peers from trusted node, port 8888
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $http_protocol.getenv('NODE_IP').$status_port);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($curl, CURLOPT_TIMEOUT, 15);

        // try once with main NODE_IP
        $json = curl_exec($curl);

        if (curl_errno($curl) && getenv('BACKUP_NODE_IP')) {
            curl_setopt($curl, CURLOPT_URL, $http_protocol.getenv('BACKUP_NODE_IP').$status_port);

            // try twice with main BACKUP_NODE_IP
            $json = curl_exec($curl);

            if (curl_errno($curl)) {
                return array();
            }
        }

        curl_close($curl);

        try {
            $object = json_decode($json, true);
        } catch(Exception $e) {
            $object = array();
        }


        // configure peers object
        $peers = $object['peers'] ?? array();
        unset($object['peers']);
        $port8888_responses[] = $object;
        $peers = $this->decodePeers($peers);
        $peers = array_unique($peers);
        $peers = array_values($peers);

        if (!$peers || empty($peers)) {
            return array();
        }


        // save peer count for trusted node
        $port8888_responses[0]['peer_count'] = count($peers);


        // divide up chunks for multi curl handler
        if (count($peers) >= 20) {
            $divided = (int)(count($peers) / 20);
        } else {
            $divided = 1;
        }

        $remainder   = count($peers) % 20;
        $real_index  = 0;
        info('Requesting peers.. Total: '.count($peers));


        // multi curl handler each full chunk
        for ($chunk = 0; $chunk < $divided; $chunk++) {
            $mh = curl_multi_init();
            $ch = array();

            for ($i = 0; $i < 20; $i++) {
                $ch[$i] = curl_init();
                curl_setopt($ch[$i], CURLOPT_URL, $http_protocol.$peers[$real_index].$status_port);
                curl_setopt($ch[$i], CURLOPT_HEADER, 0);
                curl_setopt($ch[$i], CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch[$i], CURLOPT_CONNECTTIMEOUT, 15);
                curl_setopt($ch[$i], CURLOPT_TIMEOUT, 15);

                curl_multi_add_handle($mh, $ch[$i]);
                $real_index += 1;
            }


            //execute chunk multi handle for this chunk
            do {
                $status = curl_multi_exec($mh, $active);

                if ($active) {
                    curl_multi_select($mh);
                }
            } while ($active && $status == CURLM_OK);

            for ($i = 0; $i < 20; $i++) {
                curl_multi_remove_handle($mh, $ch[$i]);
            }

            foreach ($ch as $req) {
                $content = curl_multi_getcontent($req);

                if($content) {
                    $object = json_decode($content, true);
                    $peer_count = isset($object['peers']) ? count($object['peers']) : 0;
                    unset($object['peers']);
                    $object['peer_count'] = $peer_count;
                    $port8888_responses[] = $object;
                }
            }

            curl_multi_close($mh);
            $mh = null;
            usleep(50000);
        }


        // do last chunk
        $mh = curl_multi_init();
        $ch = array();

        for ($i = 0; $i < $remainder; $i++) {
            $ch[$i] = curl_init();
            curl_setopt($ch[$i], CURLOPT_URL, $http_protocol.$peers[$real_index].$status_port);
            curl_setopt($ch[$i], CURLOPT_HEADER, 0);
            curl_setopt($ch[$i], CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch[$i], CURLOPT_CONNECTTIMEOUT, 15);
            curl_setopt($ch[$i], CURLOPT_TIMEOUT, 15);

            curl_multi_add_handle($mh, $ch[$i]);
            $real_index += 1;
        }


        //execute chunk multi handle for last chunk
        do {
            $status = curl_multi_exec($mh, $active);

            if ($active) {
                curl_multi_select($mh);
            }
        } while ($active && $status == CURLM_OK);

        for ($i = 0; $i < $remainder; $i++) {
            curl_multi_remove_handle($mh, $ch[$i]);
        }

        foreach ($ch as $req) {
            $content = curl_multi_getcontent($req);

            if ($content) {
                $object = json_decode($content, true);
                $peer_count = isset($object['peers']) ? count($object['peers']) : 0;
                unset($object['peers']);
                $object['peer_count'] = $peer_count;
                $port8888_responses[] = $object;
            }
        }

        info('Closing multi handle for the last chunk..done');
        curl_multi_close($mh);
        $mh = null;

        return $port8888_responses;
    }

    public function getValidAddresses()
    {
        $curl      = curl_init();
        $json_data = [
            'id'      => (int) time(),
            'jsonrpc' => '2.0',
            'method'  => 'state_get_auction_info',
            'params'  => array()
        ];

        curl_setopt($curl, CURLOPT_URL, 'http://' . getenv('NODE_IP') . ':7777/rpc');
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($curl, CURLOPT_TIMEOUT, 15);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($json_data));
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-type: application/json',
        ]);

        // parse response for bids from NODE_IP
        $response = curl_exec($curl);

        if (
            curl_errno($curl) &&
            getenv('BACKUP_NODE_IP')
        ) {
            curl_setopt(
                $curl,
                CURLOPT_URL, 
                'http://' . getenv('BACKUP_NODE_IP') . ':7777/rpc'
            );

            // try twice with BACKUP_NODE_IP
            $response = curl_exec($curl);
        }

        curl_close($curl);
        $decoded_response = json_decode($response, true);
        $auction_state    = $decoded_response['result']['auction_state'] ?? [];
        $bids             = $auction_state['bids'] ?? [];

        $addresses = [];

        if ($bids) {
            foreach ($bids as $bid) {
                if (isset($bid['public_key']) && $bid['public_key']) {
                    $public_key  = strtolower($bid['public_key']);
                    $addresses[] = $public_key;
                }
            }
        }
        return $addresses;
    }

    public function getValidatorStanding()
    {
        // get node ips from peers
        // $port8888_responses = $this->discoverPeers();
        $port8888_responses = array();

        // get auction state from trusted node RPC
        $curl = curl_init();

        $json_data = array(
            'id'      => (int)time(),
            'jsonrpc' => '2.0',
            'method'  => 'state_get_auction_info',
            'params'  => array()
        );

        curl_setopt($curl, CURLOPT_URL, 'http://' . getenv('NODE_IP') . ':7777/rpc');
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($curl, CURLOPT_TIMEOUT, 15);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($json_data));
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Accept: application/json',
            'Content-type: application/json',
        ));

        // parse response for bids
        $auction_response = curl_exec($curl);

        if (curl_errno($curl) && getenv('BACKUP_NODE_IP')) {
            curl_setopt($curl, CURLOPT_URL, 'http://' . getenv('BACKUP_NODE_IP') . ':7777/rpc');

            // try twice with BACKUP_NODE_IP
            $auction_response = curl_exec($curl);
        }

        curl_close($curl);

        // very large object. aprx 10MB
        $decoded_response       = json_decode($auction_response, true);
        $auction_state          = $decoded_response['result']['auction_state'] ?? array();
        $bids                   = $auction_state['bids'] ?? array();

        // get era ID
        $era_validators         = $auction_state['era_validators'] ?? array();
        $current_era_validators = $era_validators[0] ?? array();
        $next_era_validators    = $era_validators[1] ?? array();
        $current_era_id         = (int)($current_era_validators['era_id'] ?? 0);
        $next_era_id            = (int)($next_era_validators['era_id'] ?? 0);

        // loop current era
        $current_validator_weights = $current_era_validators['validator_weights'] ?? array();

        foreach ($current_validator_weights as $v) {
            $public_key = $v['public_key'] ?? '';
            $weight     = (int)($v['weight'] / 1000000000 ?? 0);

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
        $next_validator_weights = $next_era_validators['validator_weights'] ?? array();

        foreach ($next_validator_weights as $v) {
            $public_key = $v['public_key'] ?? '';
            $weight     = (int)($v['weight'] / 1000000000 ?? 0);

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
                        'public_key'      => $public_key,
                        'era_id'          => $current_era_id,
                        'next_era_weight' => $weight,
                        'in_next_era'     => 1
                    )
                );
            } else {
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
        $global_uptime = $this->retrieveGlobalUptime($current_era_id);

        // loop auction era
        foreach ($bids as $b) {
            $public_key               = strtolower($b['public_key'] ?? 'nill');
            $bid                      = $b['bid'] ?? array();

            // get self
            $self_staked_amount       = (int)($bid['staked_amount'] ?? 0);
            $delegation_rate          = (int)($bid['delegation_rate'] ?? 0);
            $bid_inactive             = (int)($bid['inactive'] ?? false);

            // calculate total stake, delegators + self stake
            $delegators               = (array)($bid['delegators'] ?? array());
            $delegators_count         = count($delegators);
            $delegators_staked_amount = 0;

            foreach ($delegators as $delegator) {
                $delegators_staked_amount += (int)($delegator['staked_amount'] ?? 0);
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
        $eras_to_be_stable = DB::select("
            SELECT value
            FROM settings
            WHERE name = 'eras_to_be_stable'
        ");
        $a = (array)($eras_to_be_stable[0] ?? array());
        $eras_to_be_stable = (int)($a['value'] ?? 0);

        // Create setting if not exist
        if (!$eras_to_be_stable) {
            $eras_to_be_stable = 100;

            DB::table('settings')->insert(
                array(
                    'name'  => 'eras_to_be_stable',
                    'value' => '100'
                )
            );
        }

        // Discover black marks - validator has sufficient stake, but failed to win slot.
        $eras_look_back = DB::select("
            SELECT value
            FROM settings
            WHERE name = 'eras_look_back'
        ");
        $a = (array)($eras_look_back[0] ?? array());
        $eras_look_back = (int)($a['value'] ?? 0);

        // Create setting if not exist
        if (!$eras_look_back) {
            $eras_look_back = 360;

            DB::table('settings')->insert(
                array(
                    'name'  => 'eras_look_back',
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
        $previous_mbs    = (float)($previous_mbs[0]['mbs'] ?? 0);

        foreach ($bids as $b) {
            $public_key  = strtolower($b['public_key'] ?? 'nill');

            // last era
            $previous_stake = DB::select("
                SELECT bid_total_staked_amount
                FROM all_node_data
                WHERE public_key = '$public_key'
                AND era_id = $previous_era_id
            ");
            $a = (array)($previous_stake[0] ?? array());
            $previous_stake = (int)($a['bid_total_staked_amount'] ?? 0);

            // current era
            $next_era_weight = DB::select("
                SELECT next_era_weight
                FROM all_node_data
                WHERE public_key = '$public_key'
                AND era_id = $current_era_id
            ");
            $a = (array)($next_era_weight[0] ?? array());
            $next_era_weight = (int)($a['next_era_weight'] ?? 0);

            // Calculate historical_performance from past $eras_look_back eras
            $missed = 0;
            $in_curent_eras = DB::select("
                SELECT in_curent_era
                FROM all_node_data
                WHERE public_key = '$public_key'
                ORDER BY era_id DESC
                LIMIT $eras_look_back
            ");

            $in_curent_eras = $in_curent_eras ? $in_curent_eras : array();
            $window         = $in_curent_eras ? count($in_curent_eras) : 0;

            foreach ($in_curent_eras as $c) {
                $a  = (array)$c;
                $in = (bool)($a['in_curent_era'] ?? 0);

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
            $past_era               = $current_era_id - $eras_to_be_stable;
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
                SELECT public_key
                FROM all_node_data
                WHERE public_key = '$public_key'
                AND era_id > $past_era
                AND (
                    bad_mark     = 1 OR
                    bid_inactive = 1
                )
            ");

            $stability_count = DB::select("
                SELECT public_key
                FROM all_node_data
                WHERE public_key = '$public_key'
                AND era_id > $past_era
            ");

            $unstable        = (bool)($unstable);
            $stable          = (int)(!$unstable);
            $stability_count = $stability_count ? count($stability_count) : 0;

            if ($stability_count < $eras_to_be_stable) {
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
        DailyEarning::where('created_at', '<', Carbon::now('UTC')->subDays(90))->delete();

        return true;
    }

    public function getTotalRewards($validatorId)
    {
        $response = Http::withOptions([
            'verify' => false,
        ])->get("https://api.CSPR.live/validators/$validatorId/total-rewards");
        return $response->json();
    }

    public function getValidatorRewards($validatorId, $_range)
    {
        $nowtime   = (int)time();
        $range     = 0;
        $days      = 30;
        $leap_year = (int)date('Y');
        $leap_days = $leap_year % 4 == 0 ? 29 : 28;
        $month     = (int)date('m');

        switch($month) {
            case 1:
                $days = 31;
                break;
            case 2:
                $days = $leap_days;
                break;
            case 3:
                $days = 31;
                break;
            case 4:
                $days = 30;
                break;
            case 5:
                $days = 31;
                break;
            case 6:
                $days = 30;
                break;
            case 7:
                $days = 31;
                break;
            case 8:
                $days = 31;
                break;
            case 9:
                $days = 30;
                break;
            case 10:
                $days = 31;
                break;
            case 11:
                $days = 30;
                break;
            case 12:
                $days = 31;
                break;
            default:
                break;
        }

        switch($_range) {
            case 'day':
                $range = $nowtime - 86400;
                break;
            case 'week':
                $range = $nowtime - (86400 * 7);
                break;
            case 'month':
                $range = $nowtime - (86400 * $days);
                break;
            case 'year':
                $range = $nowtime - (86400 * (365 + ($leap_days % 2)));
                break;
            default:
                return false;
                break;
        }

        $timestamp     = Carbon::createFromTimestamp($range, 'UTC')->toDateTimeString();
        $total_records = DailyEarning::where('node_address', $validatorId)
            ->where('created_at', '>', $timestamp)
            ->get();

        $new_array            = array();
        $display_record_count = 100;

        if ($total_records) {
            $modded    = count($total_records) % $display_record_count;
            $numerator = count($total_records) - $modded;
            $modulo    = $numerator / $display_record_count;
            $new_array = array();
            $i         = $modulo;

            if ($modulo == 0) {
                $modulo = 1;
            }

            foreach ($total_records as $record) {
                if ($i % $modulo == 0) {
                    $new_array[(string)strtotime($record->created_at.' UTC')] = (string)$record->self_staked_amount;
                }
                $i++;
            }
        }

        return $new_array;
    }
}
