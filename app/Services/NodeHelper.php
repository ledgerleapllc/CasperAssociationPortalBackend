<?php

namespace App\Services;

use App\Models\KeyPeer;
use App\Models\Node;
use App\Models\NodeInfo;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\BadResponseException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;


class NodeHelper
{
	private $one_hour_ago;
	private $six_hours_ago;
	private $twelve_hours_ago;
	private $one_day_ago;
	private $three_days_ago;
	private $ten_days_ago;
	private $two_weeks_ago;
	private $one_month_ago;
	private $two_months_ago;
	private $three_months_ago;
	private $six_months_ago;
	private $one_year_ago;
	private $passme;
	private $all_peers;
	private $fail_peers;
	private $keyed_peers;
	private $default_ip = '18.219.70.138';
	private $default_validatorid = '011117189c666f81c5160cd610ee383dc9b2d0361f004934754d39752eedc64957';

	public function __construct(
		$passme = null
	) {
		$time = (int)time();
		$this->one_hour_ago = $time - (3599);
		$this->six_hours_ago = $time - (3600 * 6);
		$this->twelve_hours_ago = $time - (3600 * 12);
		$this->one_day_ago = $time - (86400);
		$this->three_days_ago = $time - (86400 * 3);
		$this->ten_days_ago = $time - (86400 * 10);
		$this->two_weeks_ago = $time - (86400 * 14);
		$this->one_month_ago = $time - (2629800);
		$this->two_months_ago = $time - (2629800 * 2);
		$this->three_months_ago = $time - (2629800 * 3);
		$this->six_months_ago = $time - (2629800 * 6);
		$this->one_year_ago = $time - (2629800 * 12);
		$this->passme = $passme;
		$this->all_peers = array();
		$this->fail_peers = array();

		// GET PEERS KEY/VAL OBJECT FROM DATABASE THAT HAS BEEN SAVED,
		// SHOULD LOOK SOMETHING LIKE THIS:

		// $this->keyed_peers = array(
		// 	"01026ca707c348ed8012ac6a1f28db031fadd6eb67203501a353b867a08c8b9a80" => "69.30.219.234",
		// 	"01031cdce87d5fe53246492f9262932f9eb7421ea54b30da1eca06874fd2a7df60" => "14.224.155.176",

		// );

		$this->keyed_peers = array();
	}

	public function __destruct()
	{
		//
	}

	private function seenaToken()
	{
		$SEENA_API_KEY = '48454d73487700a29f50719d69be47d4';
		$SEENA_SALT = '07c2e029b7ea7c03';
		/*
		SAVE THESE KEYS IN .ENV AS:
		SEENA_API_KEY=48454d73487700a29f50719d69be47d4
		SEENA_SALT=07c2e029b7ea7c03
		*/
		$t = (int)time();
		$k = array(
			(string)($t - 1),
			(string)($t),
			(string)($t + 1)
		);

		return (md5($k[0] . $SEENA_SALT . $SEENA_API_KEY) . 'h' .
			md5($k[1] . $SEENA_SALT . $SEENA_API_KEY) . 'h' .
			md5($k[2] . $SEENA_SALT . $SEENA_API_KEY));
	}

	private function seenaDecrypt(
		$ciphertext,
		$password,
		$iv
	) {
		str_replace('\\', '', $ciphertext);
		return rtrim(trim(openssl_decrypt(
			$ciphertext,
			"aes-128-cbc",
			$password,
			OPENSSL_ZERO_PADDING,
			$iv
		)), "\0..\32");
	}

	private function decodePeers($peers)
	{
		$decoded_peers = array();
		if ($peers && gettype($peers) == 'array') {
			foreach ($peers as $peer) {
				$address = $peer->address;

				$address = explode(':', $address)[0];
				$decoded_peers[] = $address;
			}
		}
		return $decoded_peers;
	}

	private function validateValidatorId($vid)
	{
		// Log::info($this->ten_days_ago);
		if (
			gettype($vid) != 'string' || (substr($vid, 0, 2) != '01' &&
				substr($vid, 0, 2) != '02') ||
			strlen($vid) % 2 != 0 ||
			!preg_match("/^[0-9a-fA-F]*$/", $vid)
		) {
			return [
				'error' => 'Invalid validator ID type'
			];
		}

		if (substr($vid, 0, 2) == '01') {
			if (strlen($vid) != 66) {
				return [
					'error' => 'Invalid ED25519 validator ID'
				];
			}
		} else {
			if (strlen($vid) != 68) {
				return [
					'error' => 'Invalid SECP256K1 validator ID'
				];
			}
		}

		return true;
	}

	public function getNodeAuctionData()
	{
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, 'http://' . $this->default_ip . ':8089/auction');
		curl_setopt($curl, CURLOPT_POSTFIELDS, '{"full_auction": true}');
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		$headers = array();
		$headers[] = 'Content-Type: application/json';
		// $headers[] = 'Authorization: Token ' . $this->seenaToken();
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		$result = curl_exec($curl);

		if (curl_errno($curl)) {
			return [
				'error' => 'There was a problem getting validator node auction data at this time'
			];
		}

		$json = json_decode($result);
		return $json;
		$encdetail = $json->detail ?? null;
		// $detail = $this->seenaDecrypt($encdetail, $api_key);
		$full_auction = $encdetail->full_auction ?? null;
		Log::info(print_r($full_auction, true));
		return $full_auction;
	}

	public function getNode8888Status(
		$validatorid = null,
		$ip = null
	) {
		if (!$validatorid) {
			return [
				'error' => 'Please specify a validator ID'
			];
		}

		if (!$ip) {
			if (array_key_exists($validatorid, $this->keyed_peers)) {
				$ip = $this->keyed_peers[$validatorid];
			} else {
				foreach ($this->all_peers as $p) {
					if (!array_search($p, $this->keyed_peers) && !in_array($p, $this->fail_peers)) {
						$ip = $p;
						break;
					}
				}
			}
		}

		if (!$ip) {
			$ip = $this->default_ip;
		}

		$check = $this->validateValidatorId($validatorid);

		if (!$check) {
			return $check;
		}

		$validatorid = strtolower($validatorid);
		try {
			$url = 	'http://' . $ip . ':8888/status';
			$response = Http::timeout(3)->get($url);
			$result = $response->getBody()->getContents();
			$json = json_decode($result);
		} catch (Exception $e) {
			array_push($this->fail_peers, $ip);
			throw ($e);
		}

		// Log::info(print_r($json,true));
		$last_block = $json->last_added_block_info ?? null;
		$state_root_hash = $last_block->state_root_hash ?? null;
		$block_height = $last_block->height ?? null;
		$block_hash = $last_block->hash ?? null;
		$timestamp = $last_block->timestamp ?? null;
		$era_id = $last_block->era_id ?? null;
		$public_key = $json->our_public_signing_key ?? null;
		$build_version = $json->build_version ?? null;
		$chainspec_name = $json->chainspec_name ?? null;
		$peers = $json->peers ?? null;
		$peercount = $peers ? count($peers) : 0;
		$decoded_peers = $this->decodePeers($peers);

		if ($chainspec_name != 'casper') {
			return [
				'error' => 'This validator is not running mainnet software'
			];
		}

		$this->all_peers = array_unique(
			array_merge(
				$this->all_peers,
				$decoded_peers
			),
			SORT_REGULAR
		);

		$this->keyed_peers = array_merge(
			$this->keyed_peers,
			array($public_key => $ip)
		);
		// SAVE $block_height TO DATABASE BY VALIDATOR ID
		// SAVE $block_hash TO DATABASE BY VALIDATOR ID
		// SAVE $block_hash IN TABLE OF BLOCK HASHES TO CALCULATE UPTIME
		// SAVE $decoded_peers TO DATABASE BY VALIDATOR ID
		// SAVE $this->keyed_peers KEY/VAL OBJECT TO DATABASE
		if($ip && $public_key) {
			KeyPeer::firstOrCreate([
				'ip' =>  $ip,
				'public_key' => $public_key,
			]);	
		}
		
		$next_upgrade = $json->next_upgrade ?? null;
		$activation_point = null;
		$protocol_version = null;
		$time_remaining = null;

		if ($next_upgrade) {
			$activation_point = $next_upgrade->activation_point ?? null;
			$protocol_version = $next_upgrade->protocol_version ?? null;
			$time_remaining = ($activation_point - $era_id);
			/*
			SAVE $activation_point TO DATABASE

			$activation_point IS era_id OF DEADLINE TO UPDATE SOFTWARE.
			EACH ERA IS APPROXIMATELY 2 HOURS.
			SO IF $activation_point = 1234, AND
			$era_id = 1230, THEN
			WE HAVE 4 ERAS UNTIL THE ACTIVATION POINT,
			WHICH IS APPROXIMATELY ((1234 - 1230) * 2 = 8) 8 HOURS.

			SAVE $protocol_version TO DATABASE BY VALIDATOR ID
			THIS IS THE NEWEST SOFTWARE VERSION. THIS IS HOW WE WILL KNOW WHEN SOMEONE HAS OLD SOFTWARE. IF A NODE HAS OLD SOFTWARE VERSION AFTER THE ACTIVATION POINT HAS OCCURED, THEN THAT VALIDATOR IS RUNNING OLD SOFTWARE AND IS NOW A FAILING NODE.

			WHEN A VALIDATOR FINALY HAS UPGRADED SOFTWARE, THEN THE AMOUNT OF ERAS FROM THAT TIME UNTIL THE ACTIVATION POINT WILL DETERMINE THE RESPONSIVENESS OF THE VALIDATOR.

			WHOEVER HAS THE GREATEST AVERAGE RESULT FROM:
			($activation_point - $era_id) && $protocol_version == lastest;
			IS THE LEADER OF RESPONSIVENESS.
			*/
			$node =  $public_key ? $public_key : $validatorid; 
			$user = User::where('public_address_node', $node)->first();
			if($user) {
				Node::create(
					[
						'node_address' => $node,
						'block_hash' => $block_hash,
						'block_height' => $block_height,
						'protocol_version' => $protocol_version,
						'activation_point' => $activation_point,
						'era_id' => $era_id,
						'update_responsiveness' => $time_remaining,
						'peers' => $peercount,
					]
				);
			}
			
			Log::info("success:  $validatorid");
			return 'success';
		}

		return 'fail';
	}

	public function updateStats()
	{
		// ONLY RUN ONCE PER HALF HOUR
		$key_peer_array = array();
		$key_peers = KeyPeer::get();
		foreach ($key_peers as $value) {
			$key_peer_array[$value->public_key] = $value->ip;
		}
		$this->keyed_peers = $key_peer_array;
		$auction_data = $this->getNodeAuctionData();
		$full_auction = $auction_data->detail->full_auction ?? null;
		$auction_state = $full_auction->auction_state ?? null;

		$bids = $auction_state->bids ?? null;

		if ($bids && gettype($bids) == 'array') {
			foreach ($bids as $b) {
				$validatorid = $b->public_key ?? null;
				try {
					$validatorid = $b->public_key ?? null;
					$bid = $b->bid ?? null;
					$delegation_rate = $bid->delegation_rate ?? null;
					$delegators = $bid->delegators ?? array();
					$delegators_count = count($delegators);
					// SAVE $delegators_count TO DATABASE BY $validatorid
					$total_staked_amount = 0;

					foreach ($delegators as $delegator) {
						$staked_amount = $delegator->staked_amount ?? 0;
						$total_staked_amount += (int)$staked_amount;
					}

					$self_staked_amount = $bid->staked_amount ?? 0;
					$total_staked_amount += (int)$self_staked_amount;
					$user = User::where('public_address_node', $validatorid)->first();

					// SAVE $total_staked_amount TO DATABASE BY $validatorid
					// $total_staked_amount CHANGE DIFFERENCE OVER TIME DETERMINES THE "Validator Rewards" GRAPH ON "Nodes" PAGE.

					$this->getNode8888Status($validatorid);
					if ($user) {
						NodeInfo::updateOrCreate(
							['node_address' => $validatorid],
							[
								'delegators_count' => $delegators_count,
								'self_staked_amount' => $self_staked_amount,
								'total_staked_amount' => $total_staked_amount,
								'delegation_rate' => $delegation_rate,
							]
						);
					}
					// 
				} catch (Exception $e) {
					Log::info("fail:  $validatorid " . $e->getMessage());
					continue;
				}
			}
		}
	}
}
