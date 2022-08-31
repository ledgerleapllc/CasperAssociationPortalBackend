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
        $user = auth()->user()->load(['pagePermissions']);
        if (Helper::isAccessBlocked($user, 'nodes'))
            return $this->successResponse([]);

        $public_address_node = $request->get('public_address_node');

        if (!$public_address_node) {
            $public_address_node = $user->public_address_node;
        }

        $isTotal = (int) $request->get('isTotal');

        $max_update_responsiveness = DB::select("
            SELECT max(update_responsiveness) as max_update_responsiveness FROM
            (
                SELECT MAX(update_responsiveness) as update_responsiveness FROM metric
                UNION
                SELECT MAX(update_responsiveness) as update_responsiveness FROM node_info
            ) AS results;"
        );
        $max_update_responsiveness = $max_update_responsiveness[0]->max_update_responsiveness ?? 0;

        $max_peers = DB::select("
            SELECT max(peers) as max_peers FROM (
                SELECT MAX(peers) as peers FROM metric
                UNION
                SELECT MAX(peers) as peers FROM node_info
            ) AS results;"
        );
        $max_peers =  $max_peers[0]->max_peers ?? 0;
        $max_block_height = Node::max('block_height');
        $max_uptime = DB::select("
            SELECT max(uptime) as max_uptime FROM (
                SELECT MAX(uptime) as uptime FROM metric
                UNION
                SELECT MAX(uptime) as uptime FROM node_info
            ) AS results;"
        );
        $max_uptime = $max_uptime[0]->max_uptime ?? 0;
        $latest = Node::where('node_address', strtolower($public_address_node))->whereNotnull('protocol_version')->orderBy('created_at', 'desc')->first();

        if (!$latest) {
            $latest = new Node();
        }

        $latest_block_height = $latest->block_height ?? null;
        $latest_update_responsiveness = $latest->update_responsiveness ?? null;
        $latest_peers = $latest->peers ?? null;

        $metric = Metric::where('user_id', $user->id)->first();

        if (!$metric) {
            $metric = new Metric();
        }

        $metric_uptime = $metric->uptime ?? null;
        $metric_block_height = $metric->block_height_average  ?  ($max_block_height - $metric->block_height_average)  : null;
        $metric_update_responsiveness = $metric->update_responsiveness ?? null;
        $metric_peers = $metric->peers ?? null;

        $nodeInfo = NodeInfo::where('node_address', strtolower($public_address_node))->first();

        if (!$nodeInfo) {
            $nodeInfo = new NodeInfo();
        }

        $latest_uptime = $nodeInfo->uptime ?? null;
        $nodeInfo_uptime = $nodeInfo->uptime ?? null;
        $nodeInfo_block_height = $nodeInfo->block_height ?? null;
        $nodeInfo_update_responsiveness = $nodeInfo->update_responsiveness ?? null;
        $nodeInfo_peers = $nodeInfo->peers ?? null;

        $metric->avg_uptime = $nodeInfo_uptime ?? $metric_uptime;
        $metric->avg_block_height_average = $nodeInfo_block_height ?? $metric_block_height;
        $metric->avg_update_responsiveness = $nodeInfo_update_responsiveness ?? $metric_update_responsiveness;
        $metric->avg_peers = $nodeInfo_peers ?? $metric_peers;
        
        $metric->max_peers = $max_peers;
        $metric->max_update_responsiveness = $max_update_responsiveness;
        $metric->max_block_height_average = $max_block_height;
        $metric->max_uptime = $max_uptime;

        $metric->peers = $latest_peers ?? $metric_peers;
        $metric->update_responsiveness = $latest_update_responsiveness ?? $metric_update_responsiveness;
        $metric->block_height_average = $latest_block_height ?? $metric_block_height;
        $metric->uptime = $latest_uptime ?? $metric_uptime;

        $monitoringCriteria = MonitoringCriteria::get();
        $nodeInfo = NodeInfo::where('node_address', strtolower($public_address_node))->first();
        
        $userAddress = UserAddress::where('public_address_node', strtolower($public_address_node))
            ->where('user_id', $user->id)
            ->first();

        $rank = $user->rank;

        if ($userAddress) {
            $rank = $userAddress->rank;
        }

        $totalCount = UserAddress::select([
            'users.id as user_id',
            'user_addresses.public_address_node',
            'user_addresses.is_fail_node',
            'user_addresses.rank'
        ])
        ->join('users', 'users.id', '=', 'user_addresses.user_id')
        ->where('users.banned', 0)
        ->whereNotNull('user_addresses.public_address_node')
        ->get()
        ->count();

        $delegators = $stake_amount = $self_stake_amount = 0;
        if ($nodeInfo) {
            $delegators = $nodeInfo->delegators_count;
            $stake_amount = $nodeInfo->total_staked_amount;
            $self_stake_amount = $nodeInfo->self_staked_amount;
        }

        $mbs = NodeInfo::max('mbs');
        $metric->mbs = $mbs;
        $metric->rank = $rank;
        $metric->totalCount = $totalCount;
        $metric->delegators = $delegators;
        $metric->stake_amount = $stake_amount;
        $metric->self_stake_amount = $self_stake_amount;
        $metric['node_status'] = $user->node_status;
        $metric['monitoring_criteria'] = $monitoringCriteria;

        $setting = Setting::where('name', 'peers')->first();

        if ($setting) {
            $metric['peers_setting'] = (int) $setting->value;
        } else {
            $metric['peers_setting'] = 0;
        }

        $addresses = UserAddress::where('user_id', $user->id)->get();
        $metric['addresses'] = $addresses;

        if ($isTotal) {
            $tempRank = $stake_amount = $self_stake_amount = $delegators = 0;
            if ($addresses && count($addresses) > 0) {
                $tempRank = UserAddress::get()->count();
                foreach ($addresses as $address) {
                    if ($tempRank > (int) $address->rank) {
                        $tempRank = (int) $address->rank;
                    }
                }
            }

            if ($tempRank > 0) {
                $metric->rank = $tempRank;
            }

            if ($addresses && count($addresses) > 0) {
                foreach ($addresses as $address) {
                    $nodeInfo = NodeInfo::where('node_address', strtolower($address->public_address_node))->first();
                    if ($nodeInfo) {
                        $stake_amount += $nodeInfo->total_staked_amount;
                        $delegators += $nodeInfo->delegators_count;
                        $self_stake_amount += $nodeInfo->self_staked_amount;
                    }
                }
            }
            $metric->delegators = $delegators;
            $metric->stake_amount = $stake_amount;
            $metric->self_stake_amount = $self_stake_amount;
        }

        return $this->successResponse($metric);
    }

    public function updateMetric(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'uptime' => 'nullable|numeric|between:0,100',
        ]);
        if ($validator->fails()) return $this->validateResponse($validator->errors());

        $user = User::where('id', $id)->where('role', 'member')->first();
        if (!$user) return $this->errorResponse('User not found', Response::HTTP_BAD_REQUEST);
        
        $metric = Metric::where('user_id', $id)->first();
        if (!$metric) $metric = new Metric();
        if (isset($request->uptime) && $request->uptime != null)
            $metric->uptime = $request->uptime;
        if (isset($request->block_height_average) && $request->block_height_average != null)
            $metric->block_height_average = $request->block_height_average;
        if (isset($request->update_responsiveness) && $request->update_responsiveness != null)
            $metric->update_responsiveness = $request->update_responsiveness;
        if (isset($request->peers) && $request->peers != null)
            $metric->peers = $request->peers;
        $metric->user_id = $id;
        $metric->save();
        return $this->successResponse($metric);
    }

    public function getMetricUser($id)
    {
        $user = User::find($id);
        if (!$user) return $this->successResponse([]);

        $max_update_responsiveness = DB::select("SELECT max(update_responsiveness) as max_update_responsiveness FROM
            (
            SELECT MAX(update_responsiveness) as update_responsiveness FROM metric
            UNION
            SELECT MAX(update_responsiveness) as update_responsiveness FROM node_info
            ) AS results;
        ");
        $max_update_responsiveness = $max_update_responsiveness[0]->max_update_responsiveness ?? 0;

        $max_peers = DB::select("
            SELECT max(peers) as max_peers FROM
            (
            SELECT MAX(peers) as peers FROM metric
            UNION
            SELECT MAX(peers) as peers FROM node_info
            ) AS results;
        ");
        $max_peers = $max_peers[0]->max_peers ?? 0;
        $max_block_height = Node::max('block_height');
        $max_uptime = DB::select("
            SELECT max(uptime) as max_uptime FROM
            (
            SELECT MAX(uptime) as uptime FROM metric
            UNION
            SELECT MAX(uptime) as uptime FROM node_info
            ) AS results;
        ");
        $max_uptime = $max_uptime[0]->max_uptime ?? 0;
        $latest = Node::where('node_address', strtolower($user->public_address_node))
            ->whereNotnull('protocol_version')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$latest) $latest = new Node();
        $latest_block_height = $latest->block_height ?? null;
        $latest_update_responsiveness = $latest->update_responsiveness ?? null;
        $latest_peers = $latest->peers ?? null;

        $metric = Metric::where('user_id', $user->id)->first();
        if (!$metric) $metric = new Metric();

        $metric_uptime = $metric->uptime ?? null;
        $metric_block_height = $metric->block_height_average  ?  ($max_block_height - $metric->block_height_average)  : null;
        $metric_update_responsiveness = $metric->update_responsiveness ?? null;
        $metric_peers = $metric->peers ?? null;

        $nodeInfo = NodeInfo::where('node_address', strtolower($user->public_address_node))->first();
        if (!$nodeInfo) $nodeInfo = new NodeInfo();
        
        $latest_uptime = $nodeInfo->uptime ?? null;
        $nodeInfo_uptime = $nodeInfo->uptime ?? null;
        $nodeInfo_block_height = $nodeInfo->block_height ?? null;
        $nodeInfo_update_responsiveness = $nodeInfo->update_responsiveness ?? null;
        $nodeInfo_peers = $nodeInfo->peers ?? null;

        $metric->avg_uptime = $nodeInfo_uptime ?? $metric_uptime;
        $metric->avg_block_height_average = $nodeInfo_block_height ?? $metric_block_height;
        $metric->avg_update_responsiveness = $nodeInfo_update_responsiveness ?? $metric_update_responsiveness;
        $metric->avg_peers = $nodeInfo_peers ?? $metric_peers;

        $metric->max_peers = $max_peers;
        $metric->max_update_responsiveness = $max_update_responsiveness;
        $metric->max_block_height_average = $max_block_height;
        $metric->max_uptime = $max_uptime;

        $metric->peers = $latest_peers ?? $metric_peers;
        $metric->update_responsiveness = $latest_update_responsiveness ?? $metric_update_responsiveness;
        $metric->block_height_average = $latest_block_height ?? $metric_block_height;
        $metric->uptime = $latest_uptime  ?? $metric_uptime;

        $monitoringCriteria = MonitoringCriteria::get();
        $nodeInfo = NodeInfo::where('node_address', strtolower($user->public_address_node))->first();
        
        $rank = $user->rank;

        $totalCount = User::select([
            'id as user_id',
            'public_address_node',
            'is_fail_node',
            'rank',
        ])
            ->where('banned', 0)
            ->whereNotNull('public_address_node')
            ->get()
            ->count();
        
        $delegators = $stake_amount = $self_stake_amount = 0;
        if ($nodeInfo) {
            $delegators = $nodeInfo->delegators_count;
            $stake_amount = $nodeInfo->total_staked_amount;
            $self_stake_amount = $nodeInfo->self_staked_amount;
        }
        $mbs = NodeInfo::max('mbs');
        $metric->mbs = $mbs;
        $metric->rank = $rank;
        $metric->totalCount = $totalCount;
        $metric->delegators = $delegators;
        $metric->stake_amount = $stake_amount;
        $metric->self_stake_amount = $self_stake_amount;
        $metric['node_status'] = $user->node_status;
        $metric['monitoring_criteria'] = $monitoringCriteria;

        return $this->successResponse($metric);
    }

    public function getMetricUserByNodeName($node)
    {
        $node = strtolower($node);
        $userAddress = UserAddress::with('user')
            ->has('user')
            ->where('public_address_node', $node)
            ->first();

        if ($userAddress && isset($userAddress->user) && $userAddress->user) {
            $user = $userAddress->user;
            $public_address_node = strtolower($userAddress->public_address_node);

            $max_update_responsiveness = DB::select("
                SELECT max(update_responsiveness) as max_update_responsiveness FROM
                (
                SELECT MAX(update_responsiveness) as update_responsiveness FROM metric
                UNION
                SELECT MAX(update_responsiveness) as update_responsiveness FROM node_info
                ) AS results;"
            );
            $max_update_responsiveness =  $max_update_responsiveness[0]->max_update_responsiveness ?? 0;

            $max_peers = DB::select("
                SELECT max(peers) as max_peers FROM
                (
                SELECT MAX(peers) as peers FROM metric
                UNION
                SELECT MAX(peers) as peers FROM node_info
                ) AS results;"
            );
            $max_peers =  $max_peers[0]->max_peers ?? 0;
            $max_block_height = Node::max('block_height');
            $max_uptime = DB::select("
                SELECT max(uptime) as max_uptime FROM
                (
                SELECT MAX(uptime) as uptime FROM metric
                UNION
                SELECT MAX(uptime) as uptime FROM node_info
                ) AS results;"
            );
            $max_uptime =  $max_uptime[0]->max_uptime ?? 0;

            $latest = Node::where('node_address', strtolower($public_address_node))->whereNotnull('protocol_version')->orderBy('created_at', 'desc')->first();
            if (!$latest) $latest = new Node();

            $latest_block_height = $latest->block_height ?? null;
            $latest_update_responsiveness = $latest->update_responsiveness ?? null;
            $latest_peers = $latest->peers ?? null;
            
            $metric = Metric::where('user_id', $user->id)->first();
            if (!$metric) $metric = new Metric();
            $metric_uptime = $metric->uptime ?? null;
            $metric_block_height = $metric->block_height_average  ?  ($max_block_height - $metric->block_height_average)  : null;
            $metric_update_responsiveness = $metric->update_responsiveness ?? null;
            $metric_peers = $metric->peers ?? null;

            $nodeInfo = NodeInfo::where('node_address', strtolower($public_address_node))->first();
            if (!$nodeInfo) $nodeInfo = new NodeInfo();

            $latest_uptime = $nodeInfo->uptime ?? null;
            $nodeInfo_uptime = $nodeInfo->uptime ?? null;
            $nodeInfo_block_height = $nodeInfo->block_height ?? null;
            $nodeInfo_peers = $nodeInfo->peers ?? null;
            $nodeInfo_update_responsiveness = $nodeInfo->update_responsiveness ?? null;

            $metric->avg_uptime = $nodeInfo_uptime ?? $metric_uptime;
            $metric->avg_block_height_average = $nodeInfo_block_height ?? $metric_block_height;
            $metric->avg_update_responsiveness = $nodeInfo_update_responsiveness ?? $metric_update_responsiveness;
            $metric->avg_peers = $nodeInfo_peers ?? $metric_peers;
    
            $metric->max_peers = $max_peers;
            $metric->max_update_responsiveness = $max_update_responsiveness;
            $metric->max_block_height_average = $max_block_height;
            $metric->max_uptime = $max_uptime;
    
            $metric->peers = $latest_peers ?? $metric_peers;
            $metric->update_responsiveness = $latest_update_responsiveness ?? $metric_update_responsiveness;
            $metric->block_height_average = $latest_block_height ?? $metric_block_height;
            $metric->uptime = $latest_uptime  ?? $metric_uptime;

            $monitoringCriteria = MonitoringCriteria::get();
            $nodeInfo = NodeInfo::where('node_address', strtolower($public_address_node))->first();
            $rank = $userAddress->rank;
            $delegators = $stake_amount = $self_stake_amount = $is_open_port = 0;
            if ($nodeInfo) {
                $delegators = $nodeInfo->delegators_count;
                $stake_amount = $nodeInfo->total_staked_amount;
                $self_stake_amount = $nodeInfo->self_staked_amount;
                $is_open_port = $nodeInfo->is_open_port;
            }
            $mbs = NodeInfo::max('mbs');
            $metric->mbs = $mbs;
            $metric->rank = $rank;
            $metric->is_open_port = $is_open_port;
            $metric->delegators = $delegators;
            $metric->self_stake_amount = $self_stake_amount;
            $metric['node_status'] = $user->node_status;
            $metric['monitoring_criteria'] = $monitoringCriteria;
            return $this->successResponse($metric);
        }
        return $this->successResponse([]);
    }
}