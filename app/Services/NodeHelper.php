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
