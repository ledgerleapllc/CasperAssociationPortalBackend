<?php
/**
 * Helper class contains vital methods for the functionality of the portal.
 * Made to be static for PHPUnit tests.
 *
 * @static cipher            Crytographic algorithm choosen.
 * @static company_bytes     Ledgerleap company bytes "LL"
 * @static $countries        Valid countries and country codes
 *
 * @method array   self_curl()
 * @method string  kebab_case()
 * @method string  string_from_regex()
 * @method array   get_contact_recipients()
 * @method array   get_emailer_admins()
 * @method string  format_hash()
 * @method bool    can_claim_validator_id()
 * @method array   get_block()
 * @method string  get_state_root_hash()
 * @method string  public_key_to_account_hash()
 * @method array   get_account_info_standard()
 * @method array   get_era_data()
 * @method array   get_validator_rewards()
 * @method array   retrieve_global_uptime()
 * @method int     get_current_era_id()
 * @method string  fetch_setting()
 * @method bool    apply_setting()
 * @method array   get_user()
 * @method array   get_user_entities()
 * @method string  check_authorized_devices()
 * @method null    add_authorized_device()
 * @method null    log_login()
 * @method null    sanitize_input()
 * @method string  generate_guid()
 * @method bool    verify_guid()
 * @method bool    guid_available()
 * @method string  generate_session_token()
 * @method string  generate_hash()
 * @method string  get_timedelta()
 * @method string  get_datetime()
 * @method string  get_filing_year()
 * @method bool    schedule_email()
 * @method bool    instant_email()
 * @method bool    send_mfa()
 * @method string  verify_mfa()             String returned is a success/error reason message.
 * @method bool    create_mfa_allowance()
 * @method bool    consume_mfa_allowance()
 * @method string  b_encode()
 * @method string  b_decode()
 * @method string  aes_encrypt()
 * @method string  aes_decrypt()
 * @method string  encrypt_pii()
 * @method array   decrypt_pii()
 * @method array   get_dir_contents()
 * @method string  get_real_ip()
 * @method bool    in_CIDR_range()
 * @method bool    ISO3166_country()
 *
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Helper {
	private const cipher       = "AES-256-CBC";
	public const company_bytes = "4c4c"; // LL (LedgerLeap)

	function __construct() {
		// do nothing yet
	}

	function __destruct() {
		// do nothing yet
	}

	/**
	 *
	 * Curl to api macro for integration testing
	 *
	 * @param  string $method
	 * @param  string $endpoint
	 * @param  array  $fields
	 * @param  array  $headers
	 * @return array  $response
	 *
	 */
	public static function self_curl(
		string $method,
		string $endpoint,
		array  $fields  = array(),
		array  $headers = array()
	) {
		$method = strtolower($method);
		$ch     = curl_init();

		curl_setopt(
			$ch,
			CURLOPT_RETURNTRANSFER,
			1
		);

		if ($method == 'get') {
			$arg_string = '';

			if (!empty($fields)) {
				$arg_string .= '?';
			}

			foreach ($fields as $key => $val) {
				$arg_string .= $key.'='.$val.'&';
			}

			$arg_string = rtrim($arg_string, '&');

			curl_setopt(
				$ch,
				CURLOPT_URL,
				PROTOCOL.'://'.CORS_SITE.$endpoint.$arg_string
			);
		} else

		if ($method == 'post') {
			curl_setopt(
				$ch,
				CURLOPT_URL,
				PROTOCOL.'://'.CORS_SITE.$endpoint
			);

			curl_setopt(
				$ch,
				CURLOPT_POST,
				1
			);

			curl_setopt(
				$ch,
				CURLOPT_POSTFIELDS,
				json_encode($fields)
			);
		} else

		if ($method == 'put') {
			curl_setopt(
				$ch,
				CURLOPT_URL,
				PROTOCOL.'://'.CORS_SITE.$endpoint
			);

			curl_setopt(
				$ch,
				CURLOPT_CUSTOMREQUEST,
				"PUT"
			);

			curl_setopt(
				$ch,
				CURLOPT_POSTFIELDS,
				json_encode($fields)
			);
		}

		else {
			return array();
		}

		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			$headers
		);

		$response = curl_exec($ch);
		$json     = json_decode($response, true);

		curl_close($ch);

		if ($json) {
			$response = $json;
		}

		return $response;
	}

	/**
	 *
	 * Takes a camel case string and converts it to kebab string
	 *
	 * @param  string $string
	 * @return string $string
	 *
	 */
	public static function kebab_case(string $string) {
		$string = lcfirst($string);
		$string = strtolower(
			preg_replace(
				'/(?<!^)[A-Z]/',
				'-$0',
				$string
			)
		);

		return $string;
	}

	/**
	 *
	 * Generate a fake data string that matches provided regex
	 *
	 * @param  string $pattern
	 * @param  int    $length
	 * @return string $string
	 *
	 */
	public static function string_from_regex(
		string $pattern = '',
		int    $length
	) {
		$pattern_hex = unpack('H*', $pattern);
		$pattern_hex = $pattern_hex[1] ?? '';
		$string      = trim(shell_exec("python3 ".BASE_DIR."/classes/string_from_regex.py $pattern_hex"));

		if (
			ctype_xdigit($string) &&
			strlen($string) % 2 != 0
		) {
			$string = $string.'0';
		}

		return $string;
	}

	/**
	 *
	 * Fetch all contact_recipients
	 *
	 * @return array $recipients
	 *
	 */
	public static function get_contact_recipients() {
		global $db;

		$selection = $db->do_select("
			SELECT email
			FROM contact_recipients
		");

		$selection  = $selection ?? array();
		$recipients = array();

		foreach ($selection as $e) {
			$email = $e['email'] ?? '';

			if ($email) {
				$recipients[] = $email;
			}
		}

		return $recipients;
	}

	/**
	 *
	 * Fetch all emailer_admins
	 *
	 * @return array $emailer_admins
	 *
	 */
	public static function get_emailer_admins() {
		global $db;

		$selection = $db->do_select("
			SELECT email
			FROM emailer_admins
		");

		$selection      = $selection ?? array();
		$emailer_admins = array();

		foreach ($selection as $e) {
			$email = $e['email'] ?? '';

			if ($email) {
				$emailer_admins[] = $email;
			}
		}

		return $emailer_admins;
	}

	/**
	 *
	 * Format a long hash by places "..." between start and end chars
	 *
	 * @param  string $hash
	 * @param  int    $length
	 * @return string $formatted_hash
	 *
	 */
	public static function format_hash(
		string $hash,
		int    $length = 10
	) {
		if (strlen($hash) <= $length) {
			return $hash;
		}

		$b    = 1;
		$dots = '...';

		if ($length % 2 == 0) {
			$b    = 0;
			$dots = '..';
		}

		$split = (($length - $b) / 2) - 1;
		$first = substr($hash, 0, $split);
		$last  = substr($hash, strlen($hash) - $split);

		$formatted_hash = $first.$dots.$last;

		return $formatted_hash;
	}

	/**
	 *
	 * Discover if a validator_id is available to claim
	 *
	 * @param  string $validator_id
	 * @return string $can_claim
	 *
	 */
	public static function can_claim_validator_id(
		string $validator_id = ''
	) {
		global $db;

		$can_claim = true;
		$era_id    = self::get_current_era_id();

		$in_pool   = $db->do_select("
			SELECT public_key
			FROM all_node_data
			WHERE public_key = '$validator_id'
			AND   era_id     = $era_id
			AND (
				in_current_era = 1 OR
				in_auction     = 1
			)
			LIMIT 1
		");

		if (!$in_pool) {
			$can_claim = false;
		}

		$already_claimed = $db->do_select("
			SELECT verified
			FROM user_nodes
			WHERE public_key = '$validator_id'
		");

		$already_claimed = (array)($already_claimed ?? array());

		foreach ($already_claimed as $v) {
			if (
				!$v['verified'] ||
				$v['verified'] == ''
			) {
				// pass
			} else {
				$can_claim = false;
			}
		}

		return $can_claim;
	}

	/**
	 *
	 * Fetch current block from Casper blockchain
	 *
	 * @param  string $height
	 * @return array  $block
	 *
	 */
	public static function get_block($height = 0) {
		$node_ip = 'http://'.NODE_IP.':7777/rpc';
		$params  = array();
		$height  = (int)$height;
		$curl    = curl_init();

		if ($height) {
			$params = array(
				'block_identifier' => array(
					'Height' => $height
				)
			);
		}

		$json_data = [
			'id' => (int) time(),
			'jsonrpc' => '2.0',
			'method'  => 'chain_get_block',
			'params'  => $params
		];

		curl_setopt(
			$curl,
			CURLOPT_URL,
			$node_ip.'/rpc'
		);

		curl_setopt(
			$curl,
			CURLOPT_POST,
			true
		);

		curl_setopt(
			$curl,
			CURLOPT_RETURNTRANSFER,
			true
		);

		curl_setopt(
			$curl,
			CURLOPT_POSTFIELDS,
			json_encode($json_data)
		);

		curl_setopt(
			$curl,
			CURLOPT_HTTPHEADER,
			array(
				'Accept: application/json',
				'Content-type: application/json',
			)
		);

		$response = curl_exec($curl);

		try {
			$json = json_decode($response);
		} catch (Exception $e) {
			$json = json_decode('{}');
		}

		$block = array(
			'block_hash'      => $json->result->block->hash ?? '',
			'state_root_hash' => $json->result->block->header->state_root_hash ?? '',
			'era_id'          => (int)($json->result->block->header->era_id ?? 0),
			'timestamp'       => $json->result->block->header->timestamp ?? '',
			'height'          => (int)($json->result->block->header->height ?? 0)
		);

		return $block;
	}

	/**
	 *
	 * Fetch auction state by block hash from Casper blockchain
	 *
	 * @return string $auction_state
	 *
	 */
	public static function get_auction($block_hash) {
		$node_ip = 'http://'.NODE_IP.':7777/rpc';
		$params  = array();
		$curl    = curl_init();

		$json_data = [
			'id' => (int) time(),
			'jsonrpc' => '2.0',
			'method'  => 'state_get_auction_info',
			'params'  => array(
				'block_identifier' => array(
					'Hash' => $block_hash
				)
			)
		];

		curl_setopt(
			$curl,
			CURLOPT_URL,
			$node_ip.'/rpc'
		);

		curl_setopt(
			$curl,
			CURLOPT_POST,
			true
		);

		curl_setopt(
			$curl,
			CURLOPT_RETURNTRANSFER,
			true
		);

		curl_setopt(
			$curl,
			CURLOPT_POSTFIELDS,
			json_encode($json_data)
		);

		curl_setopt(
			$curl,
			CURLOPT_HTTPHEADER,
			array(
				'Accept: application/json',
				'Content-type: application/json',
			)
		);

		$response = curl_exec($curl);

		try {
			$json = json_decode($response);
		} catch (Exception $e) {
			$json = json_decode('{}');
		}

		$auction_state = $json->result->auction_state ?? array();

		return $auction_state;
	}

	/**
	 *
	 * Fetch current state root hash from Casper blockchain
	 *
	 * @return string $state_root_hash
	 *
	 */
	public static function get_state_root_hash() {
		$curl = curl_init();
		$node_ip = 'http://'.NODE_IP.':7777/rpc';

		$json_data = [
			'id' => (int) time(),
			'jsonrpc' => '2.0',
			'method'  => 'chain_get_state_root_hash',
			'params'  => []
		];

		curl_setopt(
			$curl,
			CURLOPT_URL,
			$node_ip.'/rpc'
		);

		curl_setopt(
			$curl,
			CURLOPT_POST,
			true
		);

		curl_setopt(
			$curl,
			CURLOPT_RETURNTRANSFER,
			true
		);

		curl_setopt(
			$curl,
			CURLOPT_POSTFIELDS,
			json_encode($json_data)
		);

		curl_setopt(
			$curl,
			CURLOPT_HTTPHEADER,
			array(
				'Accept: application/json',
				'Content-type: application/json',
			)
		);

		$response = curl_exec($curl);
		curl_close($curl);

		try {
			$json = json_decode($response);
		} catch (Exception $e) {
			$json = json_decode('{}');
		}

		$state_root_hash = $json->result->state_root_hash ?? '';

		return $state_root_hash;
	}

	/**
	 *
	 * Derive Casper account hash from public key
	 *
	 * @param  string $public_key
	 * @return string $account_hash
	 *
	 */
	public static function public_key_to_account_hash(
		string $public_key = ''
	) {
		global $blake2b;

		$public_key = (string)$public_key;
		$first_byte = substr($public_key, 0, 2);

		if ($first_byte === '01') {
			$algo = unpack('H*', 'ed25519');
		} else {
			$algo = unpack('H*', 'secp256k1');
		}

		$algo = $algo[1] ?? '';

		$account_hash = bin2hex(
			$blake2b->hash(
				hex2bin(
					$algo.
					'00'.
					substr($public_key, 2)
				)
			)
		);

		return $account_hash;
	}

	/**
	 *
	 * Gather account info standard about a validator node
	 *
	 * @param  string $validator_id
	 * @return array  $account_info
	 *
	 */
	public static function get_account_info_standard(
		string $validator_id = ''
	) {
		global $db;

		$validator_id = strtolower($validator_id);

		if (!$validator_id) {
			return array(
				"blockchain_name"  => "",
				"blockchain_desc"  => "",
				"blockchain_logo"  => "",
				"associated_nodes" => array()
			);
		}

		// convert to account hash
		$account_hash = self::public_key_to_account_hash($validator_id);

		$account_info_urls_uref = getenv('ACCOUNT_INFO_STANDARD_URLS_UREF');
		$node_ip = 'http://'.NODE_IP.':7777';
		$state_root_hash = self::get_state_root_hash();
		$curl = curl_init();

		$json_data = [
			'id' => (int) time(),
			'jsonrpc' => '2.0',
			'method'  => 'state_get_dictionary_item',
			'params'  => [
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
		curl_setopt($curl, CURLOPT_HTTPHEADER, array(
			'Accept: application/json',
			'Content-type: application/json',
		));

		$response        = curl_exec($curl);
		$decodedResponse = [];

		if ($response) {
			$decodedResponse = json_decode($response, true);
		}

		$parsed = $decodedResponse['result']['stored_value']['CLValue']['parsed'] ?? '';
		$json   = array();

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

		$blockchain_name   = $json['owner']['name'] ?? "";
		$blockchain_desc   = $json['owner']['description'] ?? "";
		$blockchain_logo   = $json['owner']['branding']['logo']['png_256'] ?? "";
		$associated_nodes  = $json['owner']['affiliated_accounts'] ?? array();
		$associated_nodes  = (array)$associated_nodes;
		$associated_nodes2 = array();

		foreach ($associated_nodes as $node) {
			$public_key = $node['public_key'] ?? '';

			if ($public_key) {
				$associated_nodes2[] = $public_key;
			}
		}

		$account_info = array(
			"blockchain_name"  => $blockchain_name,
			"blockchain_desc"  => $blockchain_desc,
			"blockchain_logo"  => $blockchain_logo,
			"associated_nodes" => $associated_nodes2
		);

		// Save contract logo on the fly
		$u = $db->do_select("
			SELECT
			a.guid,
			b.avatar_url
			FROM user_nodes AS a
			JOIN users AS b
			ON a.guid = b.guid
			WHERE a.public_key = '$validator_id'
		");

		$user_guid  = $u[0]['guid'] ?? '';
		$avatar_url = $u[0]['avatar_url'] ?? '';

		if (
			$user_guid &&
			!$avatar_url
		) {
			$db->do_query("
				UPDATE users
				SET   avatar_url = '$blockchain_logo'
				WHERE guid       = '$user_guid'
			");
		}

		return $account_info;
	}

	/**
	 *
	 * Gather era information about a node
	 *
	 * @param  string $validator_id
	 * @return array  $era_info
	 *
	 */
	public static function get_era_data(
		string $validator_id = '',
		int    $window       = 99999999
	) {
		global $db;

		$current_era_id = self::get_current_era_id();

		$total_eras         = 0;
		$total_redmarks     = 0;
		$eras_since_redmark = 0;

		$first_era = (int)($db->do_select("
			SELECT min(era_id) AS first_era
			FROM  all_node_data
			WHERE public_key = '$validator_id'
		")[0]['first_era'] ?? $current_era_id);

		$mbs = (int)($db->do_select("
			SELECT mbs
			FROM  mbs
			WHERE era_id = $first_era
		")[0]['mbs'] ?? 0);

		$total_eras = $current_era_id - $first_era;
		$total_eras = $total_eras < 0 ? 0 : $total_eras;

		$historic_era = $current_era_id - $window;
		$historic_era = $historic_era < 0 ? 0 : $historic_era;

		$total_redmarks = $db->do_select("
			SELECT count(era_id) AS total_redmarks
			FROM  all_node_data
			WHERE public_key = '$validator_id'
			AND   era_id     > $historic_era
			AND (
				in_current_era = 0 OR
				bid_inactive   = 1
			)
			AND current_era_weight > $mbs
		");

		$total_redmarks = $total_redmarks[0]['total_redmarks'] ?? 0;

		$eras_since_redmark = (int)($db->do_select("
			SELECT era_id
			FROM all_node_data
			WHERE public_key = '$validator_id'
			AND (
				in_current_era = 0 OR
				bid_inactive   = 1
			)
			AND current_era_weight > $mbs
			ORDER BY era_id DESC
			LIMIT 1
		")[0]['era_id'] ?? 0);

		$eras_since_redmark = $current_era_id - $eras_since_redmark;
		$eras_since_redmark = (
			$eras_since_redmark > $total_eras ?
			$total_eras :
			$eras_since_redmark
		);

		return array(
			"total_eras"         => $total_eras,
			"total_redmarks"     => $total_redmarks,
			"eras_since_redmark" => $eras_since_redmark,
		);
	}

	/**
	 *
	 * Calculate earnings by validator ID
	 *
	 * @param  string $validator_id
	 * @param  string $measure       enum('day', 'week', 'month', 'year')
	 * @return array  $rewards
	 *
	 */
	public static function get_validator_rewards(
		string $validator_id,
		string $measure = 'day'
	) {
		global $db;

		date_default_timezone_set('UTC');

		$nowtime   = (int)time();
		$range     = 0;
		$days      = 30;
		$leap_year = (int)date('Y');
		$leap_days = $leap_year % 4 == 0 ? 29 : 28;
		$month     = (int)date('m');

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

		switch ($measure) {
			case 'day':
				$range = 86400;
			break;
			case 'week':
				$range = 86400 * 7;
			break;
			case 'month':
				$range = 86400 * $days;
			break;
			case 'year':
				$range = 86400 * (365 + ($leap_days % 2));
			break;
			default:
				return false;
			break;
		}

		$timestamp = self::get_datetime($range * (-1));

		$total_records = $db->do_select("
			SELECT bid_self_staked_amount, created_at
			FROM   all_node_data
			WHERE  created_at > '$timestamp'
			AND    public_key = '$validator_id'
		");

		$rewards              = [];
		$display_record_count = 100;

		if ($total_records) {
			$modded    = count($total_records) % $display_record_count;
			$numerator = count($total_records) - $modded;
			$modulo    = $numerator / $display_record_count;
			$rewards   = [];
			$i         = $modulo;

			if ((int)$modulo == 0) {
				$modulo = 1;
			}

			foreach ($total_records as $record) {
				if ($i % $modulo == 0) {
					$key   = strtotime($record['created_at'].' UTC') * 1000;
					$value = (string)$record['bid_self_staked_amount'];
					// $rewards[$key] = $value;

					$rewards[] = array(
						$key,
						$value
					);
				}
				$i++;
			}
		}

		return $rewards;
	}

	/**
	 *
	 * Retrieve global uptime array from make services api
	 *
	 * @param  int   $era_id
	 * @return array $uptime_array
	 *
	 */
	public static function retrieve_global_uptime(int $era_id) {
		$uptime_array    = array();
		$event_store_url = 'https://event-store-api-clarity-mainnet.make.services/relative-average-validator-performances?limit=100&page=1&era_id='.(string)($era_id - 1);

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
		$data         = $object->data ?? array();
		$uptime_array = array_merge($uptime_array, $data);


		// iterate through remaining pages
		for ($i = 0; $i < $page_count; $i++) {
			if ($i != 0) {
				$j = $i + 1;
				$event_store_url = 'https://event-store-api-clarity-mainnet.make.services/relative-average-validator-performances?limit=100&page='.$j.'&era_id='.(string)($era_id - 1);
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
				$data         = $object->data ?? array();
				$uptime_array = array_merge($uptime_array, $data);
			}
			sleep(1);
		}

		curl_close($ch);

		// validate uptime array by checking avg uptime.
		$avg   = 0;
		$count = count($uptime_array);
		$count = $count == 0 ? 1 : $count;

		foreach ($uptime_array as $node) {
			$avg += (float)($node->average_score ?? 0);
		}

		$avg = (float)($avg / $count);

		/*
		If uptime is SIGNIFICANTLY lower than what it should be,
		(like zero) then we need to pass back an older object.
		Otherwise everyone will get suspended simultaneously.
		*/
		if ($avg < 20) {
			// BAD.. something has happened to MAKEs endpoint
			elog('Problem with MAKE services uptime endpoint...using last known uptime variables');
			$uptime_array = self::fetch_setting('global_uptime_object');

			try {
				$uptime_array = json_decode($uptime_array);
			} catch (Exception $e) {
				elog('Uptime array from DB backup is not decoding properly');
				$uptime_array = array();
			}
		}

		// write/overwrite trustworthy uptime object
		else {
			elog('Stable global uptime object - Saving backup');
			self::apply_setting(
				'global_uptime_object',
				$uptime_array
			);
		}

		return $uptime_array;
	}

	/**
	 *
	 * Get latest era known from DB
	 *
	 * @return int $era_id
	 *
	 */
	public static function get_current_era_id() {
		global $db;

		$query = "
			SELECT era_id
			FROM all_node_data
			ORDER BY era_id DESC
			LIMIT 1
		";
		$era_id = $db->do_select($query);
		$era_id = (int)($era_id[0]['era_id'] ?? 0);

		return $era_id;
	}

	/**
	 *
	 * Fetches and decrypts settings by name
	 *
	 * @param  string       $name
	 * @return string|array $value
	 *
	 */
	public static function fetch_setting(
		string $name = '',
		bool   $json = false
	) {
		global $db;

		if (
			!$name ||
			!preg_match(Regex::$db_setting['pattern'], $name)
		) {
			return '';
		}

		$query = "
			SELECT value
			FROM settings
			WHERE name = '$name'
		";
		$value = $db->do_select($query);

		if ($value) {
			$value = $value[0]['value'] ?? '';
		} else {
			// create 'name' if not exist
			$query = "
				INSERT INTO settings (
					name,
					value
				) VALUES (
					'$name',
					''
				)
			";
			$db->do_query($query);
			$value = '';
		}

		$value = self::aes_decrypt($value);

		if ($json) {
			try {
				$value = json_decode($value);
			} catch (Exception $e) {}
		}

		return $value;
	}

	/**
	 *
	 * Encrypts and applies a settings by name/value
	 *
	 * @param  string  $name
	 * @param  any     $value
	 * @return bool
	 *
	 */
	public static function apply_setting(
		string $name = '',
			   $value
	) {
		global $db;

		if (
			!$name ||
			!preg_match(Regex::$db_setting['pattern'], $name)
		) {
			return false;
		}

		if (
			gettype($value) == 'object' ||
			gettype($value) == 'array'
		) {
			$value = json_encode($value);
		} else {
			$value = (string)$value;
		}

		// check if exists already
		$query = "
			SELECT value
			FROM settings
			WHERE name = '$name'
		";
		$check = $db->do_select($query);

		if (!$check) {
			$query = "
				INSERT INTO settings (
					name,
					value
				) VALUES (
					'$name',
					''
				)
			";
			$db->do_query($query);
		}

		// update
		$value = self::aes_encrypt($value);

		$query = "
			UPDATE settings
			SET value = '$value'
			WHERE name = '$name'
		";
		$result = $db->do_query($query);

		return $result;
	}

	/**
	 *
	 * Fetches and decrypts user array and PII and returns simple object for front end
	 *
	 * @param  string  $guid
	 * @return array   $user_array
	 *
	 */
	public static function get_user(string $guid) {
		global $db, $suspensions, $pagelock;

		if (!$guid) {
			return array();
		}

		$query = "
			SELECT
			guid,
			role,
			email,
			pseudonym,
			telegram,
			account_type,
			pii_data,
			verified,
			created_at,
			admin_approved,
			twofa,
			totp,
			badge_partner_link,
			avatar_url,
			letter,
			esigned
			FROM users
			WHERE guid = '$guid'
		";

		$user_array = $db->do_select($query);
		$user_array = $user_array[0] ?? null;

		if (!$user_array) {
			return array();
		}

		$pii_data_enc = $user_array['pii_data'] ?? '';
		$pii_data = self::decrypt_pii($pii_data_enc);

		if (isset($user_array['pii_data'])) {
			unset($user_array['pii_data']);
		}

		if (!$pii_data) {
			$pii_data = Structs::user_info;
		}

		$account_type = $user_array['account_type'] ?? '';

		if ($account_type == 'entity') {
			$entity_guid = $db->do_select("
				SELECT entity_guid
				FROM  user_entity_relations
				WHERE user_guid = '$guid'
			")[0]['entity_guid'] ?? '';

			if ($entity_guid) {
				$entity_pii = $db->do_select("
					SELECT pii_data
					FROM  entities
					WHERE entity_guid = '$entity_guid'
				")[0]['pii_data'] ?? '';

				$entity_pii = self::decrypt_pii($entity_pii);

				$pii_data['entity_name']       = $entity_pii['entity_name'] ?? '';
				$pii_data['entity_type']       = $entity_pii['entity_type'] ?? '';
				$pii_data['entity_reg_number'] = $entity_pii['registration_number'] ?? '';
				$pii_data['entity_vat_number'] = $entity_pii['tax_id'] ?? '';
			}
		}

		$user_array['pii_data'] = $pii_data;

		// attach special warning message
		$query = "
			SELECT
			message,
			type,
			created_at
			FROM warnings
			WHERE guid = '$guid'
			AND dismissed_at IS NULL
		";
		$warning = $db->do_select($query);
		$warning = $warning[0] ?? null;
		$user_array['warning'] = $warning;

		// suspension control
		if ($suspensions->is_suspended($guid)) {
			$user_array['suspension']   = true;
			$user_array['reinstatable'] = $suspensions->can_reinstate($guid);
			$user_array['suspension_reason'] = $suspensions->suspension_reason($guid);

			// attach suspension settings for reference
			$user_array['settings'] =  array(
				"uptime_probation"  => (float)self::fetch_setting('uptime_probation'),
				"redmark_revoke"    => (int)self::fetch_setting('redmark_revoke'),
				"redmark_calc_size" => (int)self::fetch_setting('redmark_calc_size')
			);

			$user_array['sus_letter'] = $suspensions->reinstatement_letter($guid);
			$user_array['sus_decision'] = $suspensions->reinstatement_decision($guid);
			$user_array['reinstatement_contact'] = self::fetch_setting('reinstatement_contact');
		}

		// attach notifications
		$now = self::get_datetime();
		$notifications = $db->do_select("
			SELECT
			b.id AS notification_id,
			b.title,
			b.message,
			b.type,
			b.dismissable,
			b.priority,
			b.cta
			FROM user_notifications AS a
			JOIN notifications AS b
			ON a.notification_id = b.id
			WHERE a.guid = '$guid'
			AND a.dismissed_at IS NULL
			AND b.visible = 1
			AND
			(
				(
					b.activate_at   < '$now' AND
					b.deactivate_at > '$now'
				) OR (
					b.activate_at IS NULL
				) OR (
					b.activate_at   < '$now' AND
					b.deactivate_at IS NULL
				)
			)
		");
		$notifications = $notifications ?? array();
		$user_array['notifications'] = $notifications;

		// attach unverified nodes
		$nodes = $db->do_select("
			SELECT public_key, verified
			FROM user_nodes
			WHERE guid = '$guid'
		");

		$nodes = $nodes ?? array();

		foreach ($nodes as $node) {
			$verified   = $node['verified'] ?? '';
			$public_key = $node['public_key'] ?? '';

			if (!$verified && count($nodes) == 1) {
				$user_array['unverified_node'] = $public_key;
			}
		}

		// add permissions
		$permissions = $db->do_select("
			SELECT
			membership,
			nodes,
			eras,
			discussions,
			ballots,
			perks,
			intake,
			users,
			teams,
			global_settings
			FROM permissions
			WHERE guid = '$guid'
		");

		$user_array['permissions'] = $permissions[0] ?? array(
			"membership"      => false,
			"nodes"           => false,
			"eras"            => false,
			"discussions"     => false,
			"ballots"         => false,
			"perks"           => false,
			"intake"          => false,
			"users"           => false,
			"teams"           => false,
			"global_settings" => false
		);

		// add kyc status
		$kyc_status = $db->do_select("
			SELECT
			status          AS kyc_status,
			declined_reason AS kyc_denied_reason
			FROM shufti
			WHERE guid = '$guid'
		");

		$user_array['kyc_status'] = $kyc_status[0]['kyc_status'] ?? '';
		$user_array['kyc_denied_reason'] = $kyc_status[0]['kyc_denied_reason'] ?? '';

		// add page_lock look-ahead
		$user_array['page_locks'] = $pagelock->analyze($guid);

		return $user_array;
	}

	/**
	 *
	 * Decrypts and returns a guid keyed entity PII array by user guid
	 *
	 * @param  string  $guid
	 * @return array   $entity_array
	 *
	 */
	public static function get_user_entities(string $guid) {
		global $db;

		if (!$guid) {
			return array();
		}

		$entity_array = array();

		$entity_guids = $db->do_select("
			SELECT entity_guid
			FROM user_entity_relations
			WHERE user_guid = '$guid'
		");

		$entity_guids = $entity_guids ?? array();

		foreach ($entity_guids as $e) {
			$entity_guid = $e['entity_guid'] ?? '';

			$entity_pii_enc = $db->do_select("
				SELECT pii_data
				FROM entities
				WHERE entity_guid = '$entity_guid'
				ORDER BY updated_at DESC
			");

			$entity_pii_enc = $entity_pii_enc[0]['pii_data'] ?? '';
			$entity_pii     = self::decrypt_pii($entity_pii_enc);

			if (!$entity_pii) {
				$entity_pii = Structs::entity_info;
			}

			$entity_array[$entity_guid] = $entity_pii;
		}

		return $entity_array;
	}

	/**
	 *
	 * Check user security parameters for red flags upon login
	 *
	 * Returns true|false if user needs to pass a 2FA check before authenticating.
	 *
	 * @param  string  $guid
	 * @param  string  $ip
	 * @param  string  $user_agent
	 * @param  string  $cookie
	 * @return bool    Reason the login attempt is being flagged
	 *
	 */
	public static function check_authorized_devices(
		string $guid,
		string $ip         = '',
		string $user_agent = '',
			   $cookie     = ''
	) {
		global $db;

		if (!$cookie) {
			$cookie = 'nill';
		}

		// check agent/IP/cookie
		$expired_time = self::get_datetime(-2629800); // 1 month ago avg
		$query = "
			SELECT *
			FROM authorized_devices
			WHERE guid = '$guid'
			AND created_at > '$expired_time'
			AND (
				(
					cookie     = '$cookie' AND
					user_agent = '$user_agent'
				) OR (
					ip = '$ip'
				)
			)
		";
		$result = $db->do_select($query);

		if(!$result) {
			return false;
		}

		return true;
	}

	/**
	 *
	 * Add user authorized device to avoid 2FA
	 *
	 * @param  string  $guid
	 * @param  string  $ip
	 * @param  string  $user_agent
	 * @param  string  $cookie
	 * @return null
	 *
	 */
	public static function add_authorized_device(
		string $guid,
		string $ip         = '',
		string $user_agent = '',
			   $cookie     = ''
	) {
		global $db;

		if (!$cookie) {
			$cookie = '';
		}

		// fetch similar first
		$query = "
			SELECT guid
			FROM authorized_devices
			WHERE guid       = '$guid'
			AND   ip         = '$ip'
			AND   user_agent = '$user_agent'
			AND   cookie     = '$cookie'
		";

		$similar = $db->do_select($query);
		$now     = self::get_datetime();

		if ($similar) {
			// update instead of insert
			$query = "
				UPDATE authorized_devices
				SET   created_at = '$now'
				WHERE guid       = '$guid'
				AND   ip         = '$ip'
				AND   user_agent = '$user_agent'
				AND   cookie     = '$cookie'
			";
		} else {
			// generate new cookie
			$cookie = self::generate_hash(32);

			// insert new record
			$query = "
				INSERT INTO authorized_devices (
					guid,
					ip,
					user_agent,
					cookie,
					created_at
				) VALUES (
					'$guid',
					'$ip',
					'$user_agent',
					'$cookie',
					'$now'
				)
			";
		}

		$db->do_query($query);

		return $cookie;
	}

	/**
	 *
	 * Logs login attempts for security auditing
	 *
	 * @param  string  $guid
	 * @param  string  $email
	 * @param  int     $successful
	 * @param  string  $detail
	 * @param  string  $ip
	 * @param  string  $user_agent
	 * @return null
	 *
	 */
	public static function log_login(
		string   $guid,
		string   $email,
		int|bool $successful,
		string   $detail     = '',
		string   $ip         = '',
		string   $user_agent = ''
	) {
		global $db;

		$logged_in_at = self::get_datetime();
		$successful   = (bool)$successful;
		$successful   = (int)$successful;
		$source       = strtolower($_SERVER['HTTP_ORIGIN'] ?? '');

		$query = "
			INSERT INTO login_attempts (
				guid,
				email,
				logged_in_at,
				successful,
				detail,
				ip,
				user_agent,
				source
			) VALUES (
				'$guid',
				'$email',
				'$logged_in_at',
				$successful,
				'$detail',
				'$ip',
				'$user_agent',
				'$source'
			)
		";
		$db->do_query($query);
	}

	/**
	 *
	 * Sanitize required GET/POST/PUT parameter inputs
	 *
	 * Handles string length, regex, format check of all parameter arguments.
	 * _exits's with proper error handling if a problem is encountered.
	 *
	 * @param  string  $parameter      Parameter to be sanitized/checked.
	 * @param  bool    $required       Check if parameter is required.
	 * @param  int     $min_length     Minimum length of the parameter.
	 * @param  int     $max_length     Maximum length of the parameter.
	 * @param  string  $regex_pattern  Regex pattern to be used.
	 * @param  string  $name           Name of parameter to used in error returns and logging.
	 * @return null
	 *
	 */
	public static function sanitize_input(
		$parameter      = null,
		bool $required  = true,
		int $min_length = 0,
		int $max_length = 256,
		$regex_pattern  = null,
		string $name    = ''
	) {
		$extra_parameter = 'parameter ';

		if (!$name) {
			$name = 'Parameter';
			$extra_parameter = '';
		}

		if ($required) {
			if (!$parameter) {
				_exit(
					'error',
					'Please provide required '.$extra_parameter.$name,
					400,
					'Failed to provide required '.$extra_parameter.$name
				);
			}
		}

		if (gettype($parameter) == 'string') {
			if ($min_length == $max_length) {
				if (strlen($parameter) != $max_length) {
					if($max_length == 1) {
						$chars = 'character';
					} else {
						$chars = 'characters';
					}

					$error_msg = $name.' must be exactly '.$max_length.' '.$chars.' in length.';

					_exit(
						'error',
						$error_msg,
						400,
						$error_msg
					);
				}
			}

			if (
				strlen($parameter) > $max_length ||
				strlen($parameter) < $min_length
			) {
				if($min_length == 1) {
					$chars_min = 'character';
				} else {
					$chars_min = 'characters';
				}

				if($max_length == 1) {
					$chars_max = 'character';
				} else {
					$chars_max = 'characters';
				}

				$error_msg = $name.' must be ';

				if ($min_length > 0) {
					$error_msg .= 'at least '.$min_length.' '.$chars_min.' and ';
				}

				$error_msg .= 'at most '.$max_length.' '.$chars_max.' in length.';

				_exit(
					'error',
					$error_msg,
					400,
					$error_msg
				);
			}

			if(
				gettype($regex_pattern) == 'string' &&
				strlen($parameter) > 0
			) {
				if (!preg_match($regex_pattern, $parameter)) {
					_exit(
						'error',
						'Invalid '.$name.'. Contains forbidden special characters or is not in the correct format.',
						400,
						'Invalid '.$name.'. Contains forbidden special characters or is not in the correct format.'
					);
				}
			}
		}

		if (
			gettype($parameter) == 'integer' ||
			gettype($parameter) == 'double' ||
			gettype($parameter) == 'float'
		) {
			if (
				$parameter > $max_length ||
				$parameter < $min_length
			) {
				_exit(
					'error',
					$name.' must be less than '.$max_length.', and greater than '.$min_length.'.',
					400,
					$name.' must be less than '.$max_length.', and greater than '.$min_length.'.'
				);
			}
		}
	}

	/**
	 *
	 * Generate GUID
	 *
	 * Crypto safe.
	 * 4th set is always self::company_bytes
	 *
	 * Example: eaa4a536-3ab3-35aa-4c4c-c4fe30ab200c
	 *
	 * @return string
	 *
	 */
	public static function generate_guid() {
		return (
			bin2hex(openssl_random_pseudo_bytes(4)).'-'.
			chunk_split(bin2hex(openssl_random_pseudo_bytes(4)), 4, '-').
			self::company_bytes.'-'.
			bin2hex(openssl_random_pseudo_bytes(6))
		);
	}

	/**
	 *
	 * Verify a GUID against our backend standard
	 *
	 * 4th set should always be self::company_bytes
	 *
	 * Example: eaa4a536-3ab3-35aa-4c4c-c4fe30ab200c
	 *
	 * @return bool
	 *
	 */
	public static function verify_guid(string $guid) {
		$split = explode('-', $guid);
		$set0  = $split[0];
		$set1  = $split[1] ?? '';
		$set2  = $split[2] ?? '';
		$set3  = $split[3] ?? '';
		$set4  = $split[4] ?? '';

		if (
			strlen($set0) != 8 ||
			strlen($set1) != 4 ||
			strlen($set2) != 4 ||
			strlen($set3) != 4 ||
			strlen($set4) != 12 ||
			!ctype_xdigit($set0) ||
			!ctype_xdigit($set1) ||
			!ctype_xdigit($set2) ||
			!ctype_xdigit($set3) ||
			!ctype_xdigit($set4)
		) {
			return false;
		}

		if ($set3 != self::company_bytes) {
			return false;
		}

		return true;
	}

	/**
	 *
	 * Verify a GUID against our backend standard
	 *
	 * 4th set should always be self::company_bytes
	 *
	 * Example: eaa4a536-3ab3-35aa-4c4c-c4fe30ab200c
	 *
	 * @return bool
	 *
	 */
	public static function guid_available(string $guid) {
		global $db;

		$format = str_replace('-', '', $guid);

		if (!ctype_xdigit($format)) {
			return false;
		}

		$query = "
			SELECT guid
			FROM users
			WHERE guid = '$guid'
		";
		$check = $db->do_select($query);

		if ($check) {
			return false;
		}

		return true;
	}

	/**
	 *
	 * Generate Session token
	 *
	 * 128 bytes, crypto safe
	 *
	 * @return string
	 *
	 */
	public static function generate_session_token() {
		return bin2hex(openssl_random_pseudo_bytes(128));
	}

	/**
	 *
	 * Generate User friedly hash. For MFA, confirmation codes, etc
	 *
	 * Default 10 char length
	 *
	 * @param  int    $length
	 * @return string
	 *
	 */
	public static function generate_hash(int $length = 10) {
		$seed = str_split(
			'ABCDEFGHJKLMNPQRSTUVWXYZ'.
			'2345678923456789'
		);
		// dont use 0, 1, o, O, l, I
		shuffle($seed);
		$hash = '';

		foreach(array_rand($seed, $length) as $k) {
			$hash .= $seed[$k];
		}

		return $hash;
	}

	/**
	 *
	 * Get date/time delta from seconds
	 *
	 * Outputs format days:hours:minutes:seconds, accurate up to 365 days.
	 *
	 * @param  int $seconds Seconds converted to modulated time delta
	 * @return string
	 *
	 */
	public static function get_timedelta($seconds) {
		date_default_timezone_set('UTC');
		return(gmdate('z:H:i:s', $seconds));
	}

	/**
	 *
	 * Get standard format date/time
	 *
	 * Behaves similar to epoch timestamp when compared with <=> operators
	 *
	 * @param  int $future  Can be positive for future timestamp, negative for past timestamp
	 * @return string
	 *
	 */
	public static function get_datetime($future = 0) {
		$time = time();
		date_default_timezone_set('UTC');
		return(date('Y-m-d H:i:s', $time + $future));
	}

	/**
	 *
	 * Get current filing year
	 *
	 * Collects only 'Y' from date().
	 *
	 * @return string $filing_year
	 *
	 */
	public static function get_filing_year() {
		date_default_timezone_set('UTC');
		return date('Y', time());
	}

	/**
	 *
	 * Schedule an email
	 *
	 * Instead of sending emails immediately, this feeds a cron job that scheduled sends mail every 60 seconds
	 *
	 * @param  string  $template_id
	 * @param  string  $recipient
	 * @param  string  $subject
	 * @param  string  $body
	 * @param  string  $link
	 * @return bool    $return   Indicating if queue was successful
	 *
	 */
	public static function schedule_email(
		string $template_id,
		string $recipient,
		string $subject,
		string $body,
		string $link = ''
	) {
		global $db;

		/*
		Template IDs

		 - twofa
		 - reset-password
		 - contact-us
		 - invitation
		 - verify-registration
		 - admin-alert
		 - user-alert

		*/

		/* Check exploitable endpoints for similar mail requests */
		$partial_email  = explode('+', $recipient);
		$partial_email1 = $partial_email[0];
		$partial_email2 = $partial_email[1] ?? '';
		$partial_email2 = explode('@', $partial_email2);
		$partial_email2 = $partial_email2[1] ?? '';
		$similarity_check = $db->do_select("
			SELECT *
			FROM  schedule
			WHERE template_id = '$template_id'
			AND   complete    = 0
			AND (
				email = '$recipient' OR (
					email LIKE '%$partial_email1%' AND
					email LIKE '%$partial_email2%'
				)
			)
			ORDER BY id DESC
		");
		$similarity_check = $similarity_check ?? array();

		foreach ($similarity_check as $item) {
			$sid = $item['id'] ?? 0;
			$db->do_query("
				UPDATE schedule
				SET    complete = 1
				WHERE  id       = $sid
			");
		}

		/* Create schedule item */
		$created_at = self::get_datetime();

		$query = "
			INSERT INTO schedule (
				template_id,
				subject,
				body,
				link,
				email,
				created_at
			) VALUES (
				'$template_id',
				'$subject',
				'$body',
				'$link',
				'$recipient',
				'$created_at'
			)
		";
		return $db->do_query($query);
	}

	/**
	 *
	 * Immediately send an email
	 *
	 * As an alternative to the safer email scheduler, we can fire an email instantly, avoiding the delay. Eg. 2fa codes
	 *
	 * @param  string  $template_id
	 * @param  string  $recipient
	 * @param  string  $subject
	 * @param  string  $body
	 * @param  string  $link
	 * @return bool    $return   Indicating if dispatch was successful
	 *
	 */
	public static function instant_email(
		string $template_id,
		string $recipient,
		string $subject,
		string $body,
		string $link = ''
	) {
		global $db;

		/*
		Template IDs

		 - welcome
		 - approved
		 - denied
		 - twofa
		 - register
		 - register-admin
		 - forgot-password

		*/

		// Check previously sent instant emails for duplicates/throttling
		$now         = self::get_datetime();
		$one_min_ago = self::get_datetime(-60);
		$ten_min_ago = self::get_datetime(-600);

		$one_min_check = $db->do_select("
			SELECT COUNT(email) AS c
			FROM  instant_emails
			WHERE email = '$recipient'
			AND   sent_at > '$one_min_ago'
		")[0]['c'] ?? 0;

		$ten_min_check = $db->do_select("
			SELECT COUNT(email) AS c
			FROM  instant_emails
			WHERE email = '$recipient'
			AND   sent_at > '$ten_min_ago'
		")[0]['c'] ?? 0;

		if ($one_min_check > 5) {
			// exit 429
			_exit(
				'error',
				'You are trying to send emails too often. Please wait one minute to try again.',
				429,
				'You are trying to send emails too often. Please wait one minute to try again.'
			);
		}

		if ($ten_min_check > 10) {
			// exit 429
			_exit(
				'error',
				'You are trying to send emails too often. Please wait five minutes to try again.',
				429,
				'You are trying to send emails too often. Please wait five minutes to try again.'
			);
		}

		// send instant email
		$emailer = new PHPMailer(true);
		$emailer->isSMTP();
		$emailer->Host = getenv('EMAIL_HOST');
		$emailer->Port = getenv('EMAIL_PORT');
		$emailer->SMTPKeepAlive = true;
		$emailer->Timeout    = 10;
		$emailer->SMTPSecure = 'tls';
		$emailer->SMTPAuth   = true;
		$emailer->Username   = getenv('EMAIL_USER');
		$emailer->Password   = getenv('EMAIL_PASS');

		$emailer->setFrom(
			getenv('EMAIL_FROM'), 
			getenv('APP_NAME')
		);

		$emailer->addReplyTo(
			getenv('EMAIL_FROM'),
			getenv('APP_NAME')
		);

		$emailer->isHTML(true);

		$api_url   = PROTOCOL."://".CORS_SITE;
		$front_url = PROTOCOL."://".FRONTEND_URL;
		$this_year = self::get_filing_year();

		if ($this_year != FIRST_YEAR) {
			$year_marker = FIRST_YEAR.' - '.$this_year;
		} else {
			$year_marker = FIRST_YEAR;
		}

		if (!$link) {
			$link = $front_url;
		}

		$template = file_get_contents(
			BASE_DIR.
			'/templates/'.
			$template_id.
			'.html'
		);

		$template = str_replace('[SUBJECT]',      $subject,     $template);
		$template = str_replace('[BODY]',         $body,        $template);
		$template = str_replace('[LINK]',         $link,        $template);
		$template = str_replace('[API_URL]',      $api_url,     $template);
		$template = str_replace('[FRONTEND_URL]', $front_url,   $template);
		$template = str_replace('[YEAR_MARKER]',  $year_marker, $template);

		try {
			$emailer->addAddress($recipient);
			$emailer->Subject = $subject;
			$emailer->Body    = $template;
			$emailer->send();
			elog("SENT: Instant '".$template_id."' email to: ".$recipient);

			$db->do_query("
				INSERT INTO instant_emails (
					template_id,
					subject,
					body,
					link,
					email,
					sent_at
				) VALUES (
					'$template_id',
					'$subject',
					'$body',
					'$link',
					'$recipient',
					'$now'
				)
			");
		} catch (Exception $e) {
			elog($e);
			$emailer->getSMTPInstance()->reset();
			elog("FAILED: Instant '".$template_id."' email to: ".$recipient);
			return false;
		}

		$emailer->clearAddresses();
		$emailer->clearAttachments();

		return true;
	}

	/**
	 *
	 * Send MFA code
	 *
	 * For MFA authenticated functions
	 *
	 * @param  string  $guid
	 * @return bool
	 *
	 */
	public static function send_mfa(string $guid) {
		global $db;

		$query = "
			SELECT email
			FROM   users
			WHERE  guid = '$guid'
		";

		$selection  = $db->do_select($query);
		$email      = $selection[0]['email'] ?? '';
		$code       = self::generate_hash(6);
		$created_at = self::get_datetime();

		if ($selection) {
			$query = "
				DELETE FROM twofa
				WHERE guid = '$guid'
			";
			$db->do_query($query);

			$query = "
				INSERT INTO twofa (
					guid,
					created_at,
					code
				) VALUES (
					'$guid',
					'$created_at',
					'$code'
				)
			";
			$db->do_query($query);

			self::instant_email(
				'twofa',
				$email,
				APP_NAME.' - Multi Factor Authentication',
				'Hello, please find your MFA code for '.APP_NAME.'. This code expires in 5 minutes.',
				$code
			);

			return true;
		}
		return false;
	}

	/**
	 *
	 * Verfiy MFA code
	 *
	 * Once successfully verified, MFA allowance lasts 5 minutes
	 *
	 * @param  string  $guid
	 * @param  string  $mfa_code
	 * @return string
	 *
	 */
	public static function verify_mfa(
		string $guid,
		string $mfa_code
	) {
		global $db;

		if(strlen($mfa_code) > 8) {
			return 'incorrect';
		}

		// check mfa type first
		$query = "
			SELECT totp
			FROM users
			WHERE guid = '$guid'
		";
		$mfa_type = $db->do_select($query);
		$mfa_type = (int)($mfa_type[0]['totp'] ?? 0);

		// totp type mfa
		if($mfa_type == 1) {
			$verified = Totp::check_code($guid, $mfa_code);

			if($verified) {
				self::create_mfa_allowance($guid);
				return 'success';
			}

			return 'incorrect';
		}

		// email type mfa
		$query = "
			SELECT code, created_at
			FROM  twofa
			WHERE guid = '$guid'
			AND   code = '$mfa_code'
		";

		$selection    = $db->do_select($query);
		$fetched_code = $selection[0]['code'] ?? '';
		$created_at   = $selection[0]['created_at'] ?? 0;
		$expire_time  = self::get_datetime(-300); // 5 minutes ago

		if ($selection) {
			if (strtolower($mfa_code) == strtolower($fetched_code)) {
				$query = "
					DELETE FROM twofa
					WHERE guid = '$guid'
				";
				$db->do_query($query);

				if($expire_time < $created_at) {
					self::create_mfa_allowance($guid);
					return 'success';
				} else {
					return 'expired';
				}
			}
		}
		return 'incorrect';
	}

	/**
	 *
	 * Create an MFA Allowance
	 *
	 * Happens when MFA is successfully verified.
	 * Lasts 5 minutes.
	 * Purposed for user ability to submit MFA and then submit authenticated request sequentially.
	 *
	 * @param  string  $guid
	 * @return bool
	 *
	 */
	public static function create_mfa_allowance(string $guid) {
		global $db;

		$expires_at = self::get_datetime(300); // 5 minutes from now

		$query = "
			DELETE FROM mfa_allowance
			WHERE guid = '$guid'
		";
		$db->do_query($query);

		$query = "
			INSERT INTO mfa_allowance (
				guid,
				expires_at
			) VALUES (
				'$guid',
				'$expires_at'
			)
		";
		$return = $db->do_query($query);

		return $return;
	}

	/**
	 *
	 * Consume MFA Allowance
	 *
	 * Once successfully consumed, MFA allowance is purged.
	 * If allowance is attempted to be consumed and found to be expired, it purges record and returns false.
	 *
	 * @param  string  $guid
	 * @return bool
	 *
	 */
	public static function consume_mfa_allowance(string $guid) {
		global $db;

		$query = "
			SELECT expires_at
			FROM mfa_allowance
			WHERE guid = '$guid'
		";
		$selection = $db->do_select($query);

		if(!$selection) {
			return false;
		}

		$expires_at = $selection[0]['expires_at'] ?? '';
		$now_time   = self::get_datetime();

		if($now_time > $expires_at) {
			$return = false;
		} else {
			$return = true;
		}

		$query = "
			DELETE FROM mfa_allowance
			WHERE guid = '$guid'
		";
		$db->do_query($query);

		return $return;
	}

	/**
	 *
	 * Base64 encode data quickly
	 *
	 * @param  string  $data
	 * @return string
	 *
	 */
	public static function b_encode($data) {
		return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
	}

	/**
	 *
	 * Base64 decode data quickly
	 *
	 * @param  string  $data
	 * @return string
	 *
	 */
	public static function b_decode($data) {
		return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
	}

	/**
	 *
	 * Encrypt data quickly. Crypto safe
	 *
	 * @param  string  $data
	 * @return string  $ciphertext
	 *
	 */
	public static function aes_encrypt($data) {
		$iv = openssl_random_pseudo_bytes(16);

		$ciphertext = openssl_encrypt(
			$data,
			self::cipher,
			hex2bin(MASTER_KEY),
			0,
			$iv
		);

		$ciphertext = self::b_encode(self::b_encode($ciphertext).'::'.bin2hex($iv));

		return $ciphertext;
	}

	/**
	 *
	 * Decrypt data quickly. Crypto safe
	 *
	 * @param  string  $data
	 * @return string
	 *
	 */
	public static function aes_decrypt($data) {
		$decoded = self::b_decode($data);
		$split   = explode('::', $decoded);
		$iv      = $split[1] ?? '';

		if(strlen($iv) % 2 == 0 && ctype_xdigit($iv)) {
			$iv = hex2bin($iv);
		} else {
			return self::b_decode($data);
		}

		$data = self::b_decode($split[0]);

		$decrypted = openssl_decrypt(
			$data,
			self::cipher,
			hex2bin(MASTER_KEY),
			OPENSSL_ZERO_PADDING,
			$iv
		);

		return rtrim($decrypted, "\0..\32");
	}

	/**
	 *
	 * Encrypt a PII array
	 *
	 * @param  array  $data
	 * @return string $data_json_enc  Encrypted json string ciphertext
	 *
	 */
	public static function encrypt_pii(array $data = array()) {
		$data_json     = json_encode($data);
		$data_json_enc = self::aes_encrypt($data_json);

		return $data_json_enc;
	}

	/**
	 *
	 * Decrypt a PII ciphertext string
	 *
	 * @param  string  $enc_json
	 * @return array   $data     Array object matching a Structs class const
	 *
	 */
	public static function decrypt_pii(string $ciphertext = '') {
		$data_json = self::aes_decrypt($ciphertext);

		try {
			$data = json_decode($data_json, true);
		} catch (\Exception $e) {
			$data = array();
		}

		return $data;
	}

	/**
	 *
	 * Get Dir Contents
	 *
	 * Recursively get all files/folders in the specied directory $dir.
	 * Returns list of items relative to base $__dir supplied to method, meant as __DIR__.
	 *
	 * @param  string  $__dir
	 * @param  string  $dir
	 * @return array   $result
	 *
	 */
	public static function get_dir_contents(
		$__dir,
		$dir,
		&$result = array()
	) {
		$files = scandir($dir);

		foreach ($files as $key => $val) {
			$path = realpath($dir.DIRECTORY_SEPARATOR.$val);
			$path = str_replace($__dir.'/' , '', $path);

			if (!is_dir($path)) {
				$result[] = $path;
			} elseif (
				$val != '.' &&
				$val != '..'
			) {
				self::get_dir_contents($__dir, $path, $result);
				$result[] = $path;
			}
		}

		return $result;
	}

	/**
	 *
	 * Get real IP address
	 *
	 * @return string
	 *
	 */
	public static function get_real_ip() {
		if(!empty($_SERVER['HTTP_CLIENT_IP'])) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
			$ip = $_SERVER['REMOTE_ADDR'] ?? '';
		}

		if($ip == '::1')
			return '127.0.0.1';

		if(!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
			return '127.0.0.1';

		return $ip;
	}

	/**
	 *
	 * Verify provided IP is in a provided CIDR range
	 *
	 * @param  string  $ip
	 * @param  string  $iprange
	 * @return bool
	 *
	 */
	public static function in_CIDR_range(
		string $ip,
		string $iprange
	) {
		if(!$iprange || $iprange == '') return true;

		if(strpos($iprange, '/') === false) {
			if(inet_pton($ip) == inet_pton($iprange)) return true;
		} else {
			list($subnet, $bits) = explode('/', $iprange);
			// Convert subnet to binary string of $bits length
			$subnet = unpack('H*', inet_pton($subnet)); // Subnet in Hex
			foreach($subnet as $i => $h) $subnet[$i] = base_convert($h, 16, 2); // Array of Binary
			$subnet = substr(implode('', $subnet), 0, $bits); // Subnet in Binary, only network bits
			// Convert remote IP to binary string of $bits length
			$ip = unpack('H*', inet_pton($ip)); // IP in Hex
			foreach($ip as $i => $h) $ip[$i] = base_convert($h, 16, 2); // Array of Binary
			$ip = substr(implode('', $ip), 0, $bits); // IP in Binary, only network bits
			// Check network bits match
			if($subnet == $ip) return true;
		}
		return false;
	}

	/**
	 *
	 * Verify ISO 3166 Country Codes
	 *
	 * @param  string  $country
	 * @return bool
	 *
	 */
	public static $countries = array(
		'AF' => 'Afghanistan',
		'AX' => 'Aland Islands',
		'AL' => 'Albania',
		'DZ' => 'Algeria',
		'AS' => 'American Samoa',
		'AD' => 'Andorra',
		'AO' => 'Angola',
		'AI' => 'Anguilla',
		'AQ' => 'Antarctica',
		'AG' => 'Antigua And Barbuda',
		'AR' => 'Argentina',
		'AM' => 'Armenia',
		'AW' => 'Aruba',
		'AU' => 'Australia',
		'AT' => 'Austria',
		'AZ' => 'Azerbaijan',
		'BS' => 'Bahamas',
		'BH' => 'Bahrain',
		'BD' => 'Bangladesh',
		'BB' => 'Barbados',
		'BY' => 'Belarus',
		'BE' => 'Belgium',
		'BZ' => 'Belize',
		'BJ' => 'Benin',
		'BM' => 'Bermuda',
		'BT' => 'Bhutan',
		'BO' => 'Bolivia',
		'BA' => 'Bosnia And Herzegovina',
		'BW' => 'Botswana',
		'BV' => 'Bouvet Island',
		'BR' => 'Brazil',
		'IO' => 'British Indian Ocean Territory',
		'BN' => 'Brunei Darussalam',
		'BG' => 'Bulgaria',
		'BF' => 'Burkina Faso',
		'BI' => 'Burundi',
		'KH' => 'Cambodia',
		'CM' => 'Cameroon',
		'CA' => 'Canada',
		'CV' => 'Cape Verde',
		'KY' => 'Cayman Islands',
		'CF' => 'Central African Republic',
		'TD' => 'Chad',
		'CL' => 'Chile',
		'CN' => 'China',
		'CX' => 'Christmas Island',
		'CC' => 'Cocos (Keeling) Islands',
		'CO' => 'Colombia',
		'KM' => 'Comoros',
		'CG' => 'Congo',
		'CD' => 'Congo, Democratic Republic',
		'CK' => 'Cook Islands',
		'CR' => 'Costa Rica',
		'CI' => 'Cote D\'Ivoire',
		'HR' => 'Croatia',
		'CU' => 'Cuba',
		'CY' => 'Cyprus',
		'CZ' => 'Czech Republic',
		'DK' => 'Denmark',
		'DJ' => 'Djibouti',
		'DM' => 'Dominica',
		'DO' => 'Dominican Republic',
		'EC' => 'Ecuador',
		'EG' => 'Egypt',
		'SV' => 'El Salvador',
		'GQ' => 'Equatorial Guinea',
		'ER' => 'Eritrea',
		'EE' => 'Estonia',
		'ET' => 'Ethiopia',
		'FK' => 'Falkland Islands (Malvinas)',
		'FO' => 'Faroe Islands',
		'FJ' => 'Fiji',
		'FI' => 'Finland',
		'FR' => 'France',
		'GF' => 'French Guiana',
		'PF' => 'French Polynesia',
		'TF' => 'French Southern Territories',
		'GA' => 'Gabon',
		'GM' => 'Gambia',
		'GE' => 'Georgia',
		'DE' => 'Germany',
		'GH' => 'Ghana',
		'GI' => 'Gibraltar',
		'GR' => 'Greece',
		'GL' => 'Greenland',
		'GD' => 'Grenada',
		'GP' => 'Guadeloupe',
		'GU' => 'Guam',
		'GT' => 'Guatemala',
		'GG' => 'Guernsey',
		'GN' => 'Guinea',
		'GW' => 'Guinea-Bissau',
		'GY' => 'Guyana',
		'HT' => 'Haiti',
		'HM' => 'Heard Island & Mcdonald Islands',
		'VA' => 'Holy See (Vatican City State)',
		'HN' => 'Honduras',
		'HK' => 'Hong Kong',
		'HU' => 'Hungary',
		'IS' => 'Iceland',
		'IN' => 'India',
		'ID' => 'Indonesia',
		'IR' => 'Iran, Islamic Republic Of',
		'IQ' => 'Iraq',
		'IE' => 'Ireland',
		'IM' => 'Isle Of Man',
		'IL' => 'Israel',
		'IT' => 'Italy',
		'JM' => 'Jamaica',
		'JP' => 'Japan',
		'JE' => 'Jersey',
		'JO' => 'Jordan',
		'KZ' => 'Kazakhstan',
		'KE' => 'Kenya',
		'KI' => 'Kiribati',
		'KR' => 'Korea',
		'KW' => 'Kuwait',
		'KG' => 'Kyrgyzstan',
		'LA' => 'Lao People\'s Democratic Republic',
		'LV' => 'Latvia',
		'LB' => 'Lebanon',
		'LS' => 'Lesotho',
		'LR' => 'Liberia',
		'LY' => 'Libyan Arab Jamahiriya',
		'LI' => 'Liechtenstein',
		'LT' => 'Lithuania',
		'LU' => 'Luxembourg',
		'MO' => 'Macao',
		'MK' => 'Macedonia',
		'MG' => 'Madagascar',
		'MW' => 'Malawi',
		'MY' => 'Malaysia',
		'MV' => 'Maldives',
		'ML' => 'Mali',
		'MT' => 'Malta',
		'MH' => 'Marshall Islands',
		'MQ' => 'Martinique',
		'MR' => 'Mauritania',
		'MU' => 'Mauritius',
		'YT' => 'Mayotte',
		'MX' => 'Mexico',
		'FM' => 'Micronesia, Federated States Of',
		'MD' => 'Moldova',
		'MC' => 'Monaco',
		'MN' => 'Mongolia',
		'ME' => 'Montenegro',
		'MS' => 'Montserrat',
		'MA' => 'Morocco',
		'MZ' => 'Mozambique',
		'MM' => 'Myanmar',
		'NA' => 'Namibia',
		'NR' => 'Nauru',
		'NP' => 'Nepal',
		'NL' => 'Netherlands',
		'AN' => 'Netherlands Antilles',
		'NC' => 'New Caledonia',
		'NZ' => 'New Zealand',
		'NI' => 'Nicaragua',
		'NE' => 'Niger',
		'NG' => 'Nigeria',
		'NU' => 'Niue',
		'NF' => 'Norfolk Island',
		'MP' => 'Northern Mariana Islands',
		'NO' => 'Norway',
		'OM' => 'Oman',
		'PK' => 'Pakistan',
		'PW' => 'Palau',
		'PS' => 'Palestinian Territory, Occupied',
		'PA' => 'Panama',
		'PG' => 'Papua New Guinea',
		'PY' => 'Paraguay',
		'PE' => 'Peru',
		'PH' => 'Philippines',
		'PN' => 'Pitcairn',
		'PL' => 'Poland',
		'PT' => 'Portugal',
		'PR' => 'Puerto Rico',
		'QA' => 'Qatar',
		'RE' => 'Reunion',
		'RO' => 'Romania',
		'RU' => 'Russian Federation',
		'RW' => 'Rwanda',
		'BL' => 'Saint Barthelemy',
		'SH' => 'Saint Helena',
		'KN' => 'Saint Kitts And Nevis',
		'LC' => 'Saint Lucia',
		'MF' => 'Saint Martin',
		'PM' => 'Saint Pierre And Miquelon',
		'VC' => 'Saint Vincent And Grenadines',
		'WS' => 'Samoa',
		'SM' => 'San Marino',
		'ST' => 'Sao Tome And Principe',
		'SA' => 'Saudi Arabia',
		'SN' => 'Senegal',
		'RS' => 'Serbia',
		'SC' => 'Seychelles',
		'SL' => 'Sierra Leone',
		'SG' => 'Singapore',
		'SK' => 'Slovakia',
		'SI' => 'Slovenia',
		'SB' => 'Solomon Islands',
		'SO' => 'Somalia',
		'ZA' => 'South Africa',
		'GS' => 'South Georgia And Sandwich Isl.',
		'ES' => 'Spain',
		'LK' => 'Sri Lanka',
		'SD' => 'Sudan',
		'SR' => 'Suriname',
		'SJ' => 'Svalbard And Jan Mayen',
		'SZ' => 'Swaziland',
		'SE' => 'Sweden',
		'CH' => 'Switzerland',
		'SY' => 'Syrian Arab Republic',
		'TW' => 'Taiwan',
		'TJ' => 'Tajikistan',
		'TZ' => 'Tanzania',
		'TH' => 'Thailand',
		'TL' => 'Timor-Leste',
		'TG' => 'Togo',
		'TK' => 'Tokelau',
		'TO' => 'Tonga',
		'TT' => 'Trinidad And Tobago',
		'TN' => 'Tunisia',
		'TR' => 'Turkey',
		'TM' => 'Turkmenistan',
		'TC' => 'Turks And Caicos Islands',
		'TV' => 'Tuvalu',
		'UG' => 'Uganda',
		'UA' => 'Ukraine',
		'AE' => 'United Arab Emirates',
		'GB' => 'United Kingdom',
		'US' => 'United States',
		'UM' => 'United States Outlying Islands',
		'UY' => 'Uruguay',
		'UZ' => 'Uzbekistan',
		'VU' => 'Vanuatu',
		'VE' => 'Venezuela',
		'VN' => 'Viet Nam',
		'VG' => 'Virgin Islands, British',
		'VI' => 'Virgin Islands, U.S.',
		'WF' => 'Wallis And Futuna',
		'EH' => 'Western Sahara',
		'YE' => 'Yemen',
		'ZM' => 'Zambia',
		'ZW' => 'Zimbabwe'
	);

	public static function ISO3166_country(string $country) {
		if (in_array($country, self::$countries)) {
			return true;
		}

		if (array_key_exists($country, self::$countries)) {
			return true;
		}

		return false;
	}
}
