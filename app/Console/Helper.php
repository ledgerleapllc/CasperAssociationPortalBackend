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

use Casper\Rpc\RpcClient;

use App\Services\Blake2b;

class Helper
{
	public static function getCurrentERAId() {
		$record = DB::select("
            SELECT era_id
            FROM all_node_data2
            ORDER BY era_id DESC
            LIMIT 1
        ");
        if ($record && count($record) > 0) {
        	$current_era_id = (int) ($record[0]->era_id ?? 0);
        	return $current_era_id;
        }
        return 0;
	}

	public static function isAccessBlocked($user, $page) {
		if ($user->role == 'admin') return false;
		$flag = false;
		if (isset($user->pagePermissions) && $user->pagePermissions) {
			foreach ($user->pagePermissions as $item) {
				if ($item->name == $page && !$item->is_permission) {
					$flag = true;
					break;
				}
			}
		}
		return $flag;
	}

	public static function publicKeyToAccountHash($public_key)
	{
		$public_key = (string)$public_key;
		$first_byte = substr($public_key, 0, 2);

		if($first_byte === '01') {
			$algo = unpack('H*', 'ed25519');
		} else {
			$algo = unpack('H*', 'secp256k1');
		}

		$algo = $algo[1] ?? '';

		$blake2b = new Blake2b();
		$account_hash = bin2hex($blake2b->hash(hex2bin($algo.'00'.substr($public_key, 2))));

		return $account_hash;
	}

	public static function getAccountInfoStandard($user)
	{
		$vid = strtolower($user->public_address_node ?? '');

		if (!$vid) return;

		// convert to account hash
		$account_hash = self::publicKeyToAccountHash($vid);

		$uid = $user->id ?? 0;
		$pseudonym = $user->pseudonym ?? null;

		$account_info_urls_uref = getenv('ACCOUNT_INFO_STANDARD_URLS_UREF');
		$node_ip = 'http://' . getenv('NODE_IP') . ':7777';
		$casper_client = new RpcClient($node_ip);
		$latest_block = $casper_client->getLatestBlock();
		$block_hash = $latest_block->getHash();
		$state_root_hash = $casper_client->getStateRootHash($block_hash);
		$curl = curl_init();

		$json_data = array(
			'id' => (int) time(),
			'jsonrpc' => '2.0',
			'method' => 'state_get_dictionary_item',
			'params' => array(
				'state_root_hash' => $state_root_hash,
				'dictionary_identifier' => array(
					'URef' => array(
						'seed_uref' => $account_info_urls_uref,
						'dictionary_item_key' => $account_hash,
					)
				)
			)
		);

		curl_setopt($curl, CURLOPT_URL, $node_ip . '/rpc');
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($json_data));
		curl_setopt($curl, CURLOPT_HTTPHEADER, array(
			'Accept: application/json',
			'Content-type: application/json',
		));

		$response = curl_exec($curl);
		$decodedResponse = [];

		if ($response) {
			$decodedResponse = json_decode($response, true);
		}

		$parsed = $decodedResponse['result']['stored_value']['CLValue']['parsed'] ?? '';
		$json = array();

		if($parsed) {
			curl_setopt(
				$curl, 
				CURLOPT_URL, 
				$parsed.'/.well-known/casper/account-info.casper.json'
			);
			curl_setopt($curl, CURLOPT_POST, false);
			$response = curl_exec($curl);

			try {
				$json = json_decode($response, true);
			} catch (\Exception $e) {
				$json = array();
			}
		}

		curl_close($curl);

		$blockchain_name = $json['owner']['name'] ?? null;
		$blockchain_desc = $json['owner']['description'] ?? null;
		$blockchain_logo = $json['owner']['branding']['logo']['png_256'] ?? null;
		$profile = Profile::where('user_id', $uid)->first();

		if ($profile && $json) {
			if ($blockchain_name) {
				$profile->blockchain_name = $blockchain_name;
			}

			if ($blockchain_desc) {
				$profile->blockchain_desc = $blockchain_desc;
			}

			if ($blockchain_logo && $user->avatar == null) {
				$user->avatar = $blockchain_logo;
				$user->save();
			}

			$profile->save();
			$shufti_profile = Shuftipro::where('user_id', $uid)->first();

			if (
				$shufti_profile && 
				$shufti_profile->status == 'approved' && 
				$pseudonym
			) {
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