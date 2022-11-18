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

    /*
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
    */

    
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
    

    /*
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
    */

    /*
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
    */

    public function getValidatorRewards($validatorId, $_range)
    {
        $nowtime = (int) time();
        $range = 0;
        $days = 30;
        $leap_year = (int) date('Y');
        $leap_days = $leap_year % 4 == 0 ? 29 : 28;
        $month = (int) date('m');

        switch ($month) {
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

        switch ($_range) {
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
        // $total_records = DailyEarning::where('node_address', $validatorId)
        //     ->where('created_at', '>', $timestamp)
        //     ->get();

        $total_records = DB::select("
            SELECT bid_self_staked_amount, created_at
            FROM all_node_data2
            WHERE created_at > '$timestamp'
            AND public_key = '$validatorId'
        ");

        $new_array = [];
        $display_record_count = 100;

        if ($total_records) {
            $modded = count($total_records) % $display_record_count;
            $numerator = count($total_records) - $modded;
            $modulo = $numerator / $display_record_count;
            $new_array = [];
            $i = $modulo;

            if ((int)$modulo == 0) {
                $modulo = 1;
            }

            foreach ($total_records as $record) {
                if ($i % $modulo == 0) {
                    $new_array[(string) strtotime($record->created_at.' UTC')] = (string) $record->bid_self_staked_amount;
                }
                $i++;
            }
        }

        return $new_array;
    }
}
