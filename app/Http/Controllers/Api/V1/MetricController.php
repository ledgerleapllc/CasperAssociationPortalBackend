<?php

namespace App\Http\Controllers\Api\V1;

use App\Console\Helper;

use App\Http\Controllers\Controller;

use App\Models\Metric;
use App\Models\MonitoringCriteria;
use App\Models\Node;
use App\Models\NodeInfo;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\Setting;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class MetricController extends Controller
{
    public function getMetric(Request $request)
    {
        $return_object       = array();
        $user = auth()->user()->load(['pagePermissions']);
        $user_id             = $user->id;
        
        $current_era_id = DB::select("
            SELECT era_id
            FROM all_node_data2
            ORDER BY era_id DESC
            LIMIT 1
        ");
        $current_era_id = (int)($current_era_id[0]->era_id ?? 0);

        $addresses = DB::select("
            SELECT 
            a.public_key, a.bid_delegators_count AS delegators,
            a.bid_total_staked_amount, a.bid_self_staked_amount,
            a.uptime, a.bid_inactive, a.in_current_era, a.in_auction,
            a.port8888_peers AS peers
            FROM all_node_data2 AS a
            JOIN user_addresses AS b
            ON b.public_address_node = a.public_key
            JOIN users AS c
            ON c.id = b.user_id
            WHERE a.era_id  = $current_era_id
            AND b.user_id    = $user_id
        ");
        
        if (!$addresses) {
            $addresses = array();
        }

        $return_object['addresses'] = $addresses;

        foreach ($addresses as &$address) {
            $a = $address->public_key;

            $total_bad_marks = DB::select("
                SELECT era_id
                FROM all_node_data2
                WHERE public_key = '$a'
                AND (
                    in_current_era = 0 OR
                    bid_inactive   = 1
                )
                ORDER BY era_id DESC
            ");

            $eras_since_bad_mark = $total_bad_marks[0] ?? array();
            $eras_since_bad_mark = $current_era_id - (int)($eras_since_bad_mark->era_id ?? 0);
            $total_bad_marks     = count((array)$total_bad_marks);

            $address->eras_since_bad_mark = $eras_since_bad_mark;
            $address->total_bad_marks     = $total_bad_marks;
        }

        $monitoring_criteria = DB::select("
            SELECT *
            FROM monitoring_criteria
        ");
        $return_object['monitoring_criteria'] = $monitoring_criteria;
        
        $items = Setting::get();
        $settings = [];
        if ($items) {
            foreach ($items as $item) {
                $settings[$item->name] = $item->value;
            }
        }
        $return_object['globalSettings'] = $settings;

        return $this->successResponse($return_object);
    }

    public function updateMetric(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'uptime' => 'nullable|numeric|between:0,100',
        ]);

        if ($validator->fails()) {
            return $this->validateResponse($validator->errors());
        }

        $user = User::where('id', $id)->where('role', 'member')->first();

        if (!$user) {
            return $this->errorResponse(
                'User not found', 
                Response::HTTP_BAD_REQUEST
            );
        }

        $metric = Metric::where('user_id', $id)->first();

        if (!$metric) {
            $metric = new Metric();
        }

        if (
            isset($request->uptime) && $request->uptime != null
        ) {
            $metric->uptime = $request->uptime;
        }

        if (
            isset($request->block_height_average) && 
            $request->block_height_average != null
        ) {
            $metric->block_height_average = $request->block_height_average;
        }

        if (
            isset($request->update_responsiveness) && 
            $request->update_responsiveness != null
        ) {
            $metric->update_responsiveness = $request->update_responsiveness;
        }

        if (
            isset($request->peers) &&
            $request->peers != null
        ) {
            $metric->peers = $request->peers;
        }

        $metric->user_id = $id;
        $metric->save();
        return $this->successResponse($metric);
    }

    public function getMetricUser($id)
    {
        $user = User::find($id);

        if (!$user) {
            return $this->successResponse([]);
        }

        $return_object = array();
        $user_id       = $user->id;

        $current_era_id = DB::select("
            SELECT era_id
            FROM all_node_data2
            ORDER BY era_id DESC
            LIMIT 1
        ");
        $current_era_id = (int)($current_era_id[0]->era_id ?? 0);

        $addresses = DB::select("
            SELECT 
            a.public_key, a.uptime,
            a.port8888_peers AS peers,
            a.bid_inactive, a.in_current_era
            FROM all_node_data2 AS a
            JOIN user_addresses AS b
            ON a.public_key = b.public_address_node
            WHERE a.era_id  = $current_era_id
            AND b.user_id   = $user_id
        ");
        $return_object['addresses'] = $addresses;

        foreach ($addresses as &$address) {
            $p = $address->public_key;

            $total_bad_marks = DB::select("
                SELECT era_id
                FROM all_node_data2
                WHERE public_key = '$p'
                AND (
                    in_current_era = 0 OR
                    bid_inactive   = 1
                )
                ORDER BY era_id DESC
            ");

            $eras_since_bad_mark = $total_bad_marks[0] ?? array();
            $eras_since_bad_mark = $current_era_id - (int)($eras_since_bad_mark->era_id ?? 0);
            $total_bad_marks     = count((array)$total_bad_marks);

            $address->eras_since_bad_mark = $eras_since_bad_mark;
            $address->total_bad_marks     = $total_bad_marks;
            $address->update_responsiveness = 100;
        }

        $monitoring_criteria = DB::select("
            SELECT *
            FROM monitoring_criteria
        ");
        $return_object['monitoring_criteria'] = $monitoring_criteria;
        // info($return_object);
        return $this->successResponse($return_object);
    }

    public function getMetricUserByNodeName($node)
    {
        $node           = strtolower($node);
        $return_object  = array();

        $current_era_id = DB::select("
            SELECT era_id
            FROM all_node_data2
            ORDER BY era_id DESC
            LIMIT 1
        ");
        $current_era_id = (int)($current_era_id[0]->era_id ?? 0);

        $addresses = DB::select("
            SELECT 
            a.public_key, a.uptime,
            a.bid_delegators_count AS delegators,
            a.port8888_peers AS peers,
            a.bid_inactive, a.in_current_era,
            a.bid_self_staked_amount, a.bid_total_staked_amount,
            c.node_status
            FROM all_node_data2 AS a
            JOIN user_addresses AS b
            ON a.public_key = b.public_address_node
            JOIN users AS c
            ON b.user_id = c.id
            WHERE a.era_id  = $current_era_id
            AND b.public_address_node = '$node'
        ");
        $return_object['addresses'] = $addresses;

        foreach ($addresses as &$address) {
            $p = $address->public_key;

            $total_bad_marks = DB::select("
                SELECT era_id
                FROM all_node_data2
                WHERE public_key = '$p'
                AND (
                    in_current_era = 0 OR
                    bid_inactive   = 1
                )
                ORDER BY era_id DESC
            ");

            $eras_since_bad_mark = $total_bad_marks[0] ?? array();
            $eras_since_bad_mark = $current_era_id - (int)($eras_since_bad_mark->era_id ?? 0);

            $address->eras_since_bad_mark = $eras_since_bad_mark;
        }

        $monitoring_criteria = DB::select("
            SELECT *
            FROM monitoring_criteria
        ");
        $return_object['monitoring_criteria'] = $monitoring_criteria;
        // info($return_object);
        return $this->successResponse($return_object);
    }
}