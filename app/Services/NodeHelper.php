<?php

namespace App\Services;

use App\Models\KeyPeer;
use App\Models\Node;
use App\Models\NodeInfo;
use App\Models\User;
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
        if($__peers && gettype($__peers) == 'array') {
            foreach($__peers as $__peer) {
                $address = $__peer['address'] ?? '';
                $address = explode(':', $address)[0];

                if($address) {
                    $decoded_peers[] = $address;
                }
            }
        }
        return $decoded_peers;
    }

    public function retrieveGlobalUptime($this_era_id)
    {
        $total_data = array();
        $event_store_url = 'https://event-store-api-clarity-mainnet.make.services/relative-average-validator-performances?limit=100&page=1&era_id='.(string)($this_era_id - 1);


        // make initial request, get response
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $event_store_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $json = curl_exec($ch);

        if(curl_errno($ch)) {
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
        $data = $object->data ?? array();
        $total_data = array_merge($total_data, $data);


        // iterate through remaining pages
        for($i = 0; $i < $page_count; $i++) {
            if($i != 0) {
                $j = $i + 1;
                $event_store_url = 'https://event-store-api-clarity-mainnet.make.services/relative-average-validator-performances?limit=100&page='.$j.'&era_id='.(string)($this_era_id - 1);
                curl_setopt($ch, CURLOPT_URL, $event_store_url);
                $json = curl_exec($ch);

                if(curl_errno($ch)) {
                    continue;
                }

                try {
                    $object = json_decode($json);
                } catch(Exception $e) {
                    $object = (object)[];
                }


                // update total data object
                $data = $object->data ?? array();
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
        $http_protocol = 'http://';
        $status_port = ':8888/status';


        // Get peers from trusted node, port 8888
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $http_protocol.getenv('NODE_IP').$status_port);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $json = curl_exec($curl);

        if(curl_errno($curl)) {
            return array();
        }

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

        if(!$peers || empty($peers)) {
            return array();
        }


        // save peer count for trusted node
        $port8888_responses[0]['peer_count'] = count($peers);


        // divide up chunks for multi curl handler
        if(count($peers) >= 20) {
            $divided = (int)(count($peers) / 20);
        } else {
            $divided = 1;
        }

        $remainder = count($peers) % 20;
        $real_index = 0;
        info('Requesting peers.. Total: '.count($peers));


        // multi curl handler each full chunk
        for($chunk = 0; $chunk < $divided; $chunk++) {
            $mh = curl_multi_init();
            $ch = array();

            for($i = 0; $i < 20; $i++) {
                $ch[$i] = curl_init();
                curl_setopt($ch[$i], CURLOPT_URL, $http_protocol.$peers[$real_index].$status_port);
                curl_setopt($ch[$i], CURLOPT_HEADER, 0);
                curl_setopt($ch[$i], CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch[$i], CURLOPT_CONNECTTIMEOUT, 3);
                curl_setopt($ch[$i], CURLOPT_TIMEOUT, 3);

                curl_multi_add_handle($mh, $ch[$i]);
                $real_index += 1;
            }


            //execute chunk multi handle for this chunk
            do {
                $status = curl_multi_exec($mh, $active);

                if($active) {
                    curl_multi_select($mh);
                }
            } while($active && $status == CURLM_OK);

            for($i = 0; $i < 20; $i++) {
                curl_multi_remove_handle($mh, $ch[$i]);
            }

            foreach($ch as $req) {
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
            curl_setopt($ch[$i], CURLOPT_CONNECTTIMEOUT, 4);
            curl_setopt($ch[$i], CURLOPT_TIMEOUT, 4);

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

        for($i = 0; $i < $remainder; $i++) {
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

    public function getValidatorStanding()
    {
        // get node ips from peers
        $port8888_responses = $this->discoverPeers();


        // get auction state from trusted node RPC
        $curl = curl_init();

        $json_data = array(
            'id' => (int)time(),
            'jsonrpc' => '2.0',
            'method' => 'state_get_auction_info',
            'params' => array()
        );

        curl_setopt($curl, CURLOPT_URL, 'http://'.getenv('NODE_IP').':7777/rpc');
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($json_data));
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Accept: application/json',
            'Content-type: application/json',
        ));


        // parse response for bids
        $response = curl_exec($curl);
        curl_close($curl);
        $decodedResponse = json_decode($response, true);
        $auction_state = $decodedResponse['result']['auction_state'] ?? array();
        $bids = $auction_state['bids'] ?? array();


        // get era ID
        $era_id = (int)($auction_state['era_validators'][0]['era_id'] ?? 0);


        // set MBS array. minimum bid slot amount
        $MBS_arr = array();


        // set default object
        $global_validator_standing = array(
            "global_block_height" => 0,
            "global_build_version" => '1.0.0',
            "global_chainspec_name" => 'casper',
            "validator_standing" => array()
        );


        // check each bid validator against existing platform validators
        if($bids) {
            // get global uptimes from MAKE
            $global_uptime = $this->retrieveGlobalUptime($era_id);

            foreach($bids as $bid) {
                $public_key = strtolower($bid['public_key'] ?? 'nill');
                $node_info = NodeInfo::where('node_address', $public_key)->first();


                // parse bid
                $b = $bid['bid'] ?? array();


                // get self stake amount
                $self_staked_amount = (int)($b['staked_amount'] ?? 0);


                // calculate total stake, delegators + self stake
                $delegators = (array)($b['delegators'] ?? array());
                $delegators_count = count($delegators);
                $total_staked_amount = 0 + $self_staked_amount;

                foreach($delegators as $delegator) {
                    $staked_amount = (int)($delegator['staked_amount'] ?? 0);
                    $total_staked_amount += $staked_amount;
                }

                $total_staked_amount = $total_staked_amount / 1000000000;
                $self_staked_amount = $self_staked_amount / 1000000000;


                // append to MBS array and pluck 100th place later
                $MBS_arr[$public_key] = $total_staked_amount;


                // node exists on platform, fetch/save info
                if($node_info) {
                    // get delegation rate
                    $delegation_rate = (float)($b['delegation_rate'] ?? 0);


                    // get active status (equivocation check)
                    $inactive = (bool)($b['inactive'] ?? false);


                    // save current stake amount to daily earnings table
                    $earning = new DailyEarning();
                    $earning->node_address = $public_key;
                    $earning->self_staked_amount = (int)$self_staked_amount;
                    $earning->created_at = Carbon::now('UTC');
                    $earning->save();


                    // get difference between current self stake and yesterdays self stake
                    $get_earning = DailyEarning::where('node_address', $public_key)
                        ->where('created_at', '>', Carbon::now('UTC')->subHours(24))
                        ->orderBy('created_at', 'asc')
                        ->first();
                    $yesterdays_self_staked_amount = (float)($get_earning->self_staked_amount ?? 0);
                    $daily_earning = $self_staked_amount - $yesterdays_self_staked_amount;


                    // get node uptime from MAKE object
                    $uptime = 0;

                    foreach($global_uptime as $uptime_array) {
                        $fvid = strtolower($uptime_array->public_key ?? '');

                        if($fvid == $public_key) {
                            $uptime = (float)($uptime_array->average_score ?? 1);
                            break;
                        }
                    }

                    $float_uptime = (float)($uptime / 100.0);


                    // build individual validator_standing object
                    $global_validator_standing
                    ['validator_standing']
                    [$public_key]
                    ["delegators_count"] = $delegators_count;

                    $global_validator_standing
                    ['validator_standing']
                    [$public_key]
                    ["total_staked_amount"] = $total_staked_amount;

                    $global_validator_standing
                    ['validator_standing']
                    [$public_key]
                    ["self_staked_amount"] = $self_staked_amount;

                    $global_validator_standing
                    ['validator_standing']
                    [$public_key]
                    ["delegation_rate"] = $delegation_rate;

                    $global_validator_standing
                    ['validator_standing']
                    [$public_key]
                    ["uptime"] = $float_uptime;

                    $global_validator_standing
                    ['validator_standing']
                    [$public_key]
                    ["update_responsiveness"] = 1;

                    $global_validator_standing
                    ['validator_standing']
                    [$public_key]
                    ["daily_earning"] = $daily_earning;

                    $global_validator_standing
                    ['validator_standing']
                    [$public_key]
                    ["inactive"] = $inactive;


                    // look for existing peer by public key for port 8888 data
                    foreach($port8888_responses as $port8888data) {
                        $our_public_signing_key = strtolower($port8888data['our_public_signing_key'] ?? '');

                        if($our_public_signing_key == $public_key) {
                            // found in peers
                            $peer_count = (int)($port8888data['peer_count'] ?? 0);
                            $block_height = (int)($port8888data['last_added_block_info']['height'] ?? 0);
                            $build_version = $port8888data['build_version'] ?? '1.0.0';
                            $chainspec_name = $port8888data['chainspec_name'] ?? 'casper';

                            if($block_height > $global_validator_standing["global_block_height"]) {
                                $global_validator_standing["global_block_height"] = $block_height;
                            }

                            if($build_version > $global_validator_standing["global_build_version"]) {
                                $global_validator_standing["global_build_version"] = $build_version;
                            }


                            // apply to global_validator_standing
                            $global_validator_standing
                            ['validator_standing']
                            [$public_key]
                            ["block_height"] = $block_height;

                            $global_validator_standing
                            ['validator_standing']
                            [$public_key]
                            ["build_version"] = $build_version;

                            $global_validator_standing
                            ['validator_standing']
                            [$public_key]
                            ["chainspec_name"] = $chainspec_name;

                            $global_validator_standing
                            ['validator_standing']
                            [$public_key]
                            ["peer_count"] = $peer_count;

                            break;
                        }
                    }
                }
            }
        }

        // find MBS
        rsort($MBS_arr);
        $MBS = $MBS_arr[99] ?? $MBS_arr[count($MBS_arr) - 1];
        $global_validator_standing['MBS'] = $MBS;


        // DailyEarning garbage cleanup
        DailyEarning::where('created_at', '<', Carbon::now('UTC')->subDays(90))->delete();


        return $global_validator_standing;
    }

    public function getTotalRewards($validatorId)
    {
        $response = Http::withOptions([
            'verify' => false,
        ])->get("https://api.CSPR.live/validators/$validatorId/total-rewards");
        return $response->json();
    }

    public function updateStats()
    {
        $data = $this->getValidatorStanding();
        $mbs = $data['MBS'] ?? 0;
        $peers = $data['peers'] ?? 0;
        $users = User::with('addresses')->whereNotNull('public_address_node')->get();
        $validator_standing = $data['validator_standing'] ?? null;
        $setting = Setting::where('name', 'peers')->first();

        if (!$setting) {
            $setting = new Setting;
            $setting->name = 'peers';
            $setting->value = '';
            $setting->save();
        }

        $setting->value = $peers;
        $setting->save();

        if ($validator_standing) {
            // Refresh Validator Standing
            foreach ($validator_standing as $key => $value) {
                $validator_standing[strtolower($key)] = $value;
            }

            foreach ($users as $user) {
                if (isset($user->addresses) && count($user->addresses) > 0) {
                    $addresses = $user->addresses;
                    $userAddress = strtolower($user->public_address_node);
                    foreach ($addresses as $address) {
                        $validatorid = strtolower($address->public_address_node);

                        if (isset($validator_standing[$validatorid])) {
                            $info = $validator_standing[$validatorid];
                            $fee = (float) $info['delegation_rate'];

                            if ($userAddress == $validatorid) {
                                $user->pending_node = 0;
                                $user->validator_fee = round($fee, 2);
                                $user->save();
                            }

                            $address->pending_node = 0;
                            $address->validator_fee = round($fee, 2);
                            $address->save();

                            $totalRewards = $this->getTotalRewards($validatorid);

                            $build_version = $info['build_version'] ?? null;

                            if ($build_version) {
                                $build_version = explode('-', $build_version);
                                $build_version = $build_version[0];
                            }

                            if(
                                isset($info['block_height']) &&
                                isset($info['peer_count'])
                            ) {
                                $is_open_port = 1;
                            } else {
                                $is_open_port = 0;
                            }

                            $inactive = (bool)($info['inactive'] ?? false);
                            $inactive = $inactive ? 1 : 0;

                            NodeInfo::updateOrCreate(
                                [
                                    'node_address' => $validatorid
                                ],
                                [
                                    'delegators_count' => $info['delegators_count'] ?? 0,
                                    'total_staked_amount' => $info['total_staked_amount'],
                                    'delegation_rate' => $info['delegation_rate'],
                                    'daily_earning' => $info['daily_earning'] ?? 0,
                                    'self_staked_amount' => $info['self_staked_amount'] ?? 0,
                                    'total_earning' => isset($totalRewards['data']) &&  $totalRewards['data']  > 0 ? $totalRewards['data'] / 1000000000 : 0,
                                    'is_open_port' => $is_open_port,
                                    'mbs' => $mbs,
                                    'update_responsiveness' => isset($info['update_responsiveness']) ? $info['update_responsiveness'] * 100 : 0,
                                    'uptime' => isset($info['uptime']) ? $info['uptime'] * 100 : 0,
                                    'block_height' => $info['block_height'] ?? 0,
                                    'peers' => $info['peer_count'] ?? 0,
                                    'inactive' => $inactive
                                ]
                            );

                            Node::updateOrCreate(
                                [
                                    'node_address' => $validatorid
                                ],
                                [
                                    'block_height' => $info['block_height'] ?? null,
                                    'protocol_version' => $build_version,
                                    'update_responsiveness' => isset($info['update_responsiveness']) ? $info['update_responsiveness'] * 100 : null,
                                    'uptime' => isset($info['uptime']) ? $info['uptime'] : null,
                                    'weight' => $info['daily_earnings'] ?? 0,
                                    'peers' => $info['peer_count'] ?? 0,
                                ]
                            );
                        }
                    }
                }
            }
        }
    }

    public function getValidatorRewards($validatorId, $_range)
    {
        $nowtime = (int)time();
        $range = 0;
        $days = 30;
        $leap_year = (int)date('Y');
        $leap_days = $leap_year % 4 == 0 ? 29 : 28;
        $month = (int)date('m');

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

        $timestamp = Carbon::createFromTimestamp($range, 'UTC')->toDateTimeString();
        $total_records = DailyEarning::where('node_address', $validatorId)
            ->where('created_at', '>', $timestamp)
            ->get();

        $new_array = array();
        $display_record_count = 100;

        if($total_records) {
            $modded = count($total_records) % $display_record_count;
            $numerator = count($total_records) - $modded;
            $modulo = $numerator / $display_record_count;
            $new_array = array();
            $i = $modulo;

            if($modulo == 0) {
                $modulo = 1;
            }

            foreach($total_records as $record) {
                if($i % $modulo == 0) {
                    $new_array[(string)strtotime($record->created_at.' UTC')] = (string)$record->self_staked_amount;
                }
                $i++;
            }
        }

        return $new_array;
    }
}
