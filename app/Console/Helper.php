<?php

namespace App\Console;

use App\Models\Metric;
use App\Models\MonitoringCriteria;
use App\Models\Node;
use App\Models\NodeInfo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class Helper
{
	// Get Token Price
	public static function getTokenPrice()
	{
		$url = 'https://pro-api.coinmarketcap.com/v1/tools/price-conversion';

		$apiKey = config('services.token_price.api_key');
		$response = Http::withHeaders([
			'X-CMC_PRO_API_KEY' => $apiKey
		])->get($url, [
			'amount' => 1,
			'symbol' => 'CSPR',
			'convert' => 'USD'
		]);

		return $response->json();
	}

	public static function getNodeInfo($user)
	{
		$max_update_responsiveness = DB::select("SELECT max(update_responsiveness) as max_update_responsiveness FROM
            (
            SELECT MAX(update_responsiveness) as update_responsiveness FROM metric
            UNION
            SELECT MAX(update_responsiveness) as update_responsiveness FROM node_info
            ) AS results
            ;");
		$max_update_responsiveness =  $max_update_responsiveness[0]->max_update_responsiveness ?? 0;

		$max_peers = DB::select("SELECT max(peers) as max_peers FROM
        (
        SELECT MAX(peers) as peers FROM metric
        UNION
        SELECT MAX(peers) as peers FROM node_info
        ) AS results
        ;");
		$max_peers =  $max_peers[0]->max_peers ?? 0;
		$max_block_height = Node::max('block_height');
		$max_uptime = DB::select("SELECT max(uptime) as max_uptime FROM
			(
			SELECT MAX(uptime) as uptime FROM metric
			UNION
			SELECT MAX(uptime) as uptime FROM node_info
			) AS results
			;");
        $max_uptime =  $max_uptime[0]->max_uptime ?? 0;

		$latest = Node::where('node_address', $user->public_address_node)->whereNotnull('protocol_version')->orderBy('created_at', 'desc')->first();
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

		$nodeInfo = NodeInfo::where('node_address', $user->public_address_node)->first();
		if (!$nodeInfo) {
			$nodeInfo = new NodeInfo();
		}
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
		$metric->block_height_average = $latest_block_height ??  $metric_block_height;
		$metric->uptime = $latest_uptime  ? $latest_uptime : $metric_uptime;

		$monitoringCriteria = MonitoringCriteria::get();
		$nodeInfo = NodeInfo::where('node_address', $user->public_address_node)->first();
		$rank = 5; // dummy
		$delegators = 0;
		$stake_amount = 0;
		$self_staked_amount = 0;
		if ($nodeInfo) {
			$delegators = $nodeInfo->delegators_count;
			$stake_amount = $nodeInfo->total_staked_amount;
			$self_staked_amount = $nodeInfo->self_staked_amount;
		}
		$metric->rank = $rank;
		$metric->delegators = $delegators;
		$metric->stake_amount = $stake_amount;
		$metric->self_staked_amount = $self_staked_amount;
		$metric['node_status'] = $user->node_status;
		$metric['monitoring_criteria'] = $monitoringCriteria;
		return $metric;
	}

	/**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    public static function paginate($items, $perPage = 5, $page = null, $options = [])
    {
        $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
        $items = $items instanceof Collection ? $items : Collection::make($items);
        return new LengthAwarePaginator($items->forPage($page, $perPage), $items->count(), $perPage, $page, $options);
    }
}
