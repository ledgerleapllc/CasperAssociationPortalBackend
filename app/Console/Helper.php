<?php

namespace App\Console;

use App\Models\Metric;
use App\Models\MonitoringCriteria;
use App\Models\Node;
use App\Models\NodeInfo;
use App\Models\Profile;
use App\Models\Shuftipro;
use App\Models\User;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class Helper
{
	public static function getAccountInfoStandard($user)
	{
		$vid = $user->public_address_node ?? '';
		$uid = $user->id ?? 0;
		$pseudonym = $user->pseudonym ?? null;

		$THIS_SEENA_API_KEY = getenv('SEENA_API_KEY');
		
		$response = Http::timeout(5)->withHeaders([
			'Authorization' => "token $THIS_SEENA_API_KEY",
		])->withOptions([
			'verify' => false,
		])->get('https://seena.ledgerleap.com/account-info-standard?validator_id=' . $vid);

		try {
			$json = json_decode($response);
		} catch (\Exception $e) {
			$json = array();
		}

		$blockchain_name = $json->message->owner->name ?? null;
		$blockchain_desc = $json->message->owner->description ?? null;
		$blockchain_logo = $json->message->owner->branding->logo->png_256 ?? null;

		$profile = Profile::where('user_id', $uid)->first();
		
		if ($profile && $json) {
			if ($blockchain_name) $profile->blockchain_name = $blockchain_name;
			if ($blockchain_desc) $profile->blockchain_desc = $blockchain_desc;
			if ($blockchain_logo && $user->avatar == null) {
				$user->avatar = $blockchain_logo;
				$user->save();
			}
			
			$profile->save();
			$shufti_profile = Shuftipro::where('user_id', $uid)->first();

			if ($shufti_profile && $shufti_profile->status == 'approved' && $pseudonym) {
				$shuft_status = $shufti_profile->status;
				$reference_id = $shufti_profile->reference_id;
				$hash = md5($pseudonym . $reference_id . $shuft_status);
				$profile->casper_association_kyc_hash = $hash;
				$profile->save();
			}
		}
	}

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

	public static function getNodeInfo($user, $public_address_node = null)
	{
		if (!$public_address_node) $public_address_node = $user->public_address_node;

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
		$max_uptime = $max_uptime[0]->max_uptime ?? 0;

		$latest = Node::where('node_address', strtolower($public_address_node))
			->whereNotnull('protocol_version')
			->orderBy('created_at', 'desc')
			->first();

		if (!$latest) {
			$latest = new Node();
		}

		$metric = Metric::where('user_id', $user->id)->first();

		if (!$metric) {
			$metric = new Metric();
		}

		$metric_block_height = $metric->block_height_average ? ($max_block_height - $metric->block_height_average)  : null;

		$nodeInfo = NodeInfo::where('node_address', strtolower($public_address_node))->first();

		if (!$nodeInfo) {
			$nodeInfo = new NodeInfo();
		}

		$metric->avg_uptime = $nodeInfo->uptime ?? $metric->uptime ?? null;
		$metric->avg_block_height_average = $nodeInfo->block_height ?? $metric_block_height;
		$metric->avg_update_responsiveness = $nodeInfo->update_responsiveness ?? $metric->update_responsiveness ?? null;
		$metric->avg_peers = $nodeInfo->peers ?? $metric->peers ?? null;

		$metric->max_peers = $max_peers;
		$metric->max_update_responsiveness = $max_update_responsiveness;
		$metric->max_block_height_average = $max_block_height;
		$metric->max_uptime = $max_uptime;

		$metric->peers = $latest->peers ?? $metric->peers ?? null;
		$metric->update_responsiveness = $latest->update_responsiveness ?? $metric->update_responsiveness ?? null;
		$metric->block_height_average = $latest->block_height ?? $metric_block_height;
		$metric->uptime = $nodeInfo->uptime ?? $metric->uptime ?? null;

		$monitoringCriteria = MonitoringCriteria::get();
		$nodeInfo = NodeInfo::where('node_address', strtolower($public_address_node))->first();
		$rank = $user->rank;
		$delegators = 0;
		$stake_amount = 0;
		$self_staked_amount = 0;
		$is_open_port = 0;

		if ($nodeInfo) {
			$delegators = $nodeInfo->delegators_count;
			$stake_amount = $nodeInfo->total_staked_amount;
			$self_staked_amount = $nodeInfo->self_staked_amount;
			$is_open_port = $nodeInfo->is_open_port;
		}

		$mbs = NodeInfo::max('mbs');
		$metric->mbs = $mbs;
		$metric->rank = $rank;
		$metric->is_open_port = $is_open_port;
		$metric->delegators = $delegators;
		$metric->stake_amount = $stake_amount;
		$metric->self_staked_amount = $self_staked_amount;
		$metric['node_status'] = $user->node_status;
		$metric['monitoring_criteria'] = $monitoringCriteria;
		return $metric;
	}

	public static function paginate($items, $perPage = 5, $page = null, $options = [])
	{
		$page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
		$items = $items instanceof Collection ? $items : Collection::make($items);
		return new LengthAwarePaginator($items->forPage($page, $perPage), $items->count(), $perPage, $page, $options);
	}
}