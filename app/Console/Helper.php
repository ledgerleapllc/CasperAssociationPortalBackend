<?php

namespace App\Console;

use App\Models\Metric;
use App\Models\MonitoringCriteria;
use App\Models\Node;
use App\Models\NodeInfo;
use App\Models\Profile;
use App\Models\Shuftipro;
use App\Models\User;
use App\Models\Setting;
use App\Models\AllNodeData2;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

use Casper\Rpc\RpcClient;

use App\Services\Blake2b;

class Helper
{
	public static function generateString($strength = 16) {
		$seed = str_split('ABCDEFGHJKLMNPQRSTUVWXYZ' . '2345678923456789');
        
        shuffle($seed);
        $hash = '';

        foreach(array_rand($seed, $strength) as $k) {
            $hash .= $seed[$k];
        }

        return $hash;
	}

	public static function checkAddressValidity($public_address_node) {
		$current_era_id = self::getCurrentERAId();

		$temp = AllNodeData2::select(['id'])
	                        ->where('public_key', $public_address_node)
	                        ->where('era_id', $current_era_id)
	                        ->first();
		if ($temp) return true;
		return false;
	}

	public static function getSettings() {
		$items = Setting::get();
        $settings = [];
        if ($items) {
            foreach ($items as $item) {
                $settings[$item->name] = $item->value;
            }
        }
        if (!isset($settings['peers'])) $settings['peers'] = 0;
        if (!isset($settings['eras_look_back'])) $settings['eras_look_back'] = 1;
        if (!isset($settings['eras_to_be_stable'])) $settings['eras_to_be_stable'] = 1;
        if (!isset($settings['voting_eras_to_vote'])) $settings['voting_eras_to_vote'] = 1;
        if (!isset($settings['uptime_calc_size'])) $settings['uptime_calc_size'] = 1;
        if (!isset($settings['voting_eras_since_redmark'])) $settings['voting_eras_since_redmark'] = 1;
        if (!isset($settings['uptime_warning'])) $settings['uptime_warning'] = 1;
        if (!isset($settings['uptime_probation'])) $settings['uptime_probation'] = 1;
        if (!isset($settings['uptime_correction_unit'])) $settings['uptime_correction_unit'] = 'Weeks';
        if (!isset($settings['uptime_correction_value'])) $settings['uptime_correction_value'] = 1;
        if (!isset($settings['redmarks_revoke'])) $settings['redmarks_revoke'] = 1;
        if (!isset($settings['redmarks_revoke_calc_size'])) $settings['redmarks_revoke_calc_size'] = 1;
        if (!isset($settings['responsiveness_warning'])) $settings['responsiveness_warning'] = 1;
        if (!isset($settings['responsiveness_probation'])) $settings['responsiveness_probation'] = 1;

        $settings['current_era_id'] = self::getCurrentERAId();

        return $settings;
	}

	public function getActiveMembers() {
		$current_era_id = Helper::getCurrentERAId();
		$temp = DB::select("
            SELECT
            a.public_key,
            b.extra_status,
            c.id, c.email, c.pseudonym, c.node_status,
            d.status, d.extra_status as profile_extra_status
            FROM all_node_data2 AS a
            JOIN user_addresses AS b
            ON a.public_key = b.public_address_node
            JOIN users AS c
            ON b.user_id = c.id
            JOIN profile AS d
            ON c.id = d.user_id
            WHERE a.era_id = $current_era_id and c.email is not NULL and d.status = 'approved'
        ");
        return ($temp ?? []);
	}

	public static function calculateUptime($baseObject, $public_address_node, $settings = null) {
		if (!$settings) $settings = self::getSettings();
		
		$current_era_id = (int) ($settings['current_era_id'] ?? 0);
		$uptime_calc_size = (int) ($settings['uptime_calc_size'] ?? 1);
		
		$mbs = 0;
		if (isset($baseObject->mbs)) {
			$mbs = (float) ($baseObject->mbs ?? 0);
		} else {
			$temp = DB::select("
	            SELECT mbs
	            FROM mbs
	            WHERE era_id = $current_era_id
	        ");
	        $mbs = (float) ($temp[0]->mbs ?? 0);
    	}

		$temp = DB::select("
            SELECT in_current_era
            FROM all_node_data2
            WHERE public_key = '$public_address_node'
            AND bid_total_staked_amount > $mbs
            ORDER BY era_id DESC
            LIMIT $uptime_calc_size
        ");
        if (!$temp) $temp = [];

        $window = count($temp);
        $missed = 0;
        foreach ($temp as $c) {
            $in = (bool) ($c->in_current_era ?? 0);
            if (!$in) {
                $missed += 1;
            }
        }

        $uptime = (float) ($baseObject->uptime ?? 0);
        if ($window > 0) {
        	$uptime = (float) (($uptime * ($window - $missed)) / $window);
        }
        
        return round($uptime, 2);
	}

	public static function calculateVariables($identifier, $public_address_node, $settings = null) {
		if (!$settings) $settings = self::getSettings();

		$current_era_id = (int) ($settings['current_era_id'] ?? 0);

		if ($identifier == 'good_standing_eras') {
			$temp = DB::select("
                SELECT era_id 
                FROM all_node_data2
                WHERE public_key = '$public_address_node'
                AND (
                    in_current_era = 0 OR
                	bid_inactive   = 1
                )
                ORDER BY era_id DESC
                LIMIT 1
            ");
            $value = $current_era_id - (int) ($temp[0]->era_id ?? 0);
			if ($value > 0) return $value;
			return 0;
		} else if ($identifier == 'total_active_eras') {
			$temp = DB::select("
                SELECT count(id) as tCount
                FROM all_node_data2
                WHERE public_key = '$public_address_node'
                AND in_current_era = 1
                AND bid_inactive = 0
            ");
            return (int) ($temp[0]->tCount ?? 0);
		} else if ($identifier == 'bad_marks_info') {
			$temp = DB::select("
                SELECT era_id
                FROM all_node_data2
                WHERE public_key = '$public_address_node'
                AND (
                    in_current_era = 0 OR
                    bid_inactive   = 1
                )
                ORDER BY era_id DESC
            ");
            $total_bad_marks = count($temp ?? []);
            $eras_since_bad_mark = $current_era_id - (int) ($temp[0]->era_id ?? 0);
            return [
            	'total_bad_marks' => $total_bad_marks,
            	'eras_since_bad_mark' => $eras_since_bad_mark
            ];
		} else if ($identifier == 'min_era') {
			$temp = DB::select("
                SELECT era_id
                FROM all_node_data2
                WHERE public_key = '$public_address_node'
                ORDER BY era_id ASC
                LIMIT 1
            ");
            return (int) ($temp[0]->era_id ?? 0);
		}
		return 0;
	}

	public static function calculateBadMarksRevoke($baseObject, $public_address_node, $settings = null) {
		if (!$settings) $settings = self::getSettings();

		$current_era_id = (int) ($settings['current_era_id'] ?? 0);
		$redmarks_revoke_calc_size = (int) ($settings['redmarks_revoke_calc_size'] ?? 1);
		
		$window = $current_era_id - $redmarks_revoke_calc_size;
        if ($window < 0) $window = 0;
        
        $temp = DB::select("
            SELECT count(era_id) AS bad_marks
            FROM all_node_data2
            WHERE public_key = '$public_address_node'
            AND era_id > $window
            AND (
                in_current_era = 0 OR
                bid_inactive   = 1
            )
        ");
        if (!$temp) $temp = [];
        
        return (int) ($temp[0]->bad_marks ?? 0);
	}

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

	public static function getTotalScore($r, $max_delegators, $max_stake_amount) {
		$uptime_rate = $fee_rate = $count_rate = $stake_rate = 25;
		if (isset($r->uptime_rate)) $uptime_rate = (float) $r->uptime_rate;
		if (isset($r->fee_rate)) $fee_rate = (float) $r->fee_rate;
		if (isset($r->count_rate)) $count_rate = (float) $r->count_rate;
		if (isset($r->stake_rate)) $stake_rate = (float) $r->stake_rate;

		$uptime_score = (float) ($uptime_rate * (float) $r->uptime / 100);
        $uptime_score = $uptime_score < 0 ? 0 : $uptime_score;

        $fee_score = $fee_rate * (1 - (float) ((float) $r->bid_delegation_rate / 100));
        $fee_score = $fee_score < 0 ? 0 : $fee_score;

        $count_score = (float) ((float) $r->bid_delegators_count / $max_delegators) * $count_rate;
        $count_score = $count_score < 0 ? 0 : $count_score;

        $stake_score = (float) ((float) $r->bid_total_staked_amount / $max_stake_amount) * $stake_rate;
        $stake_score = $stake_score < 0 ? 0 : $stake_score;
        
        return $uptime_score + $fee_score + $count_score + $stake_score;
	}

	public static function getRanking($current_era_id) {
		$rankingData = [];

		$ranking = DB::select("
            SELECT
            public_key, uptime,
            bid_delegators_count,
            bid_delegation_rate,
            bid_total_staked_amount
            FROM all_node_data2
            WHERE era_id = $current_era_id
            AND in_current_era = 1
            AND in_next_era = 1
            AND in_auction = 1
        ");
        $max_delegators = $max_stake_amount = 0;

        foreach ($ranking as $r) {
            if ((int) $r->bid_delegators_count > $max_delegators) {
                $max_delegators = (int) $r->bid_delegators_count;
            }
            if ((int) $r->bid_total_staked_amount > $max_stake_amount) {
                $max_stake_amount = (int) $r->bid_total_staked_amount;
            }
        }

        foreach ($ranking as $r) {
        	$rankingData['ranking'][$r->public_key] = self::getTotalScore($r, $max_delegators, $max_stake_amount);
        }

        uasort($rankingData['ranking'], function($x, $y) {
            if ($x == $y) {
                return 0;
            }
            return ($x > $y) ? -1 : 1;
        });

        $sorted_ranking = [];
        $i = 1;
        foreach ($rankingData['ranking'] as $public_key => $score) {
            $sorted_ranking[$public_key] = $i;
            $i += 1;
        }
        $rankingData['ranking'] = $sorted_ranking;
        $rankingData['node_rank_total'] = count($sorted_ranking);

		return $rankingData;
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

	public static function publicKeyToAccountHash($public_key) {
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

	public static function getStateRootHash() {
		$get_block = 'casper-client get-block ';
		$node_arg  = '--node-address http://' . getenv('NODE_IP') . ':7777/rpc';

		$json = shell_exec($get_block . $node_arg);
		$json = json_decode($json);

		$state_root_hash = $json->result->block->header->state_root_hash ?? '';
		return $state_root_hash;
	}

	public static function getAccountInfoStandard($user) {
		$vid = strtolower($user->public_address_node ?? '');
		if (!$vid) return;
		
		try {
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
			
			$json_data = [
				'id' => (int) time(),
				'jsonrpc' => '2.0',
				'method' => 'state_get_dictionary_item',
				'params' => [
					'state_root_hash' => $state_root_hash,
					'dictionary_identifier' => [
						'URef' => [
							'seed_uref' => $account_info_urls_uref,
							'dictionary_item_key' => $account_hash,
						]
					]
				]
			];

			curl_setopt($curl, CURLOPT_URL, $node_ip . '/rpc');
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($json_data));
			curl_setopt($curl, CURLOPT_HTTPHEADER, [
				'Accept: application/json',
				'Content-type: application/json',
			]);

			$response = curl_exec($curl);
			$decodedResponse = [];

			if ($response) {
				$decodedResponse = json_decode($response, true);
			}

			$parsed = $decodedResponse['result']['stored_value']['CLValue']['parsed'] ?? '';
			$json = [];

			if($parsed) {
				curl_setopt(
					$curl, 
					CURLOPT_URL, 
					$parsed . '/.well-known/casper/account-info.casper.json'
				);
				curl_setopt($curl, CURLOPT_POST, false);
				$response = curl_exec($curl);

				try {
					$json = json_decode($response, true);
				} catch (\Exception $e) {
					$json = [];
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
		} catch (\Exception $ex) {
			//
		}
	}

	// Get Token Price
	public static function getTokenPrice() {
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
}