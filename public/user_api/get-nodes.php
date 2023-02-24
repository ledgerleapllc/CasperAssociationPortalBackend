<?php
/**
 *
 * GET /user/get-nodes
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class UserGetNodes extends Endpoints {
	function __construct() {
		global $db, $helper, $pagelock;

		require_method('GET');

		$auth           = authenticate_session(1);
		$user_guid      = $auth['guid'] ?? '';
		$current_era_id = $helper->get_current_era_id();

		// 403 if page is locked from user access due to KYC or probation
		$pagelock->check($user_guid, 'nodes');

		// define return object
		$return = array(
			"public_keys" => array(),
			"price_data"  => array(),
			"ranking"     => array(),
			"mbs"         => 0
		);

		// nodes list
		$nodes = $db->do_select("
			SELECT a.public_key
			FROM all_node_data AS a
			JOIN user_nodes AS b
			ON a.public_key = b.public_key
			WHERE a.era_id  = $current_era_id
			AND b.guid      = '$user_guid'
			AND b.verified  IS NOT NULL
		");

		$nodes = $nodes ?? array();

		foreach ($nodes as $node) {
			$public_key = $node['public_key'] ?? '';

			if ($public_key) {
				$return["public_keys"][] = $public_key;
			}
		}

		// token price graph
		$price_data = $db->do_select("
			SELECT price,created_at
			FROM token_price
		");

		$price_data = $price_data ?? array();

		foreach ($price_data as $p) {
			$created_at = $p['created_at'] ?? '';
			$price      = $p['price'] ?? '';
			$name       = strtotime($created_at) * 1000;

			$return["price_data"][] = array(
				$name,
				number_format($price, 4)
			);
		}

		// rankings
		$ranking = $db->do_select("
			SELECT public_key, node_rank
			FROM all_node_data
			WHERE era_id       = $current_era_id
			AND in_current_era = 1
			AND bid_inactive   = 0
			ORDER BY node_rank ASC
		");

		$ranking        = $ranking ?? array();
		$sorted_ranking = array();

		foreach ($ranking as $r) {
			$sorted_ranking[$r['public_key']] = $r['node_rank'];
		}

		$return["ranking"]         = $sorted_ranking;
		$return["node_rank_total"] = count($sorted_ranking);

		// MBS
		$mbs = $db->do_select("
			SELECT mbs
			FROM mbs
			WHERE era_id = $current_era_id
		");

		$return['mbs'] = (int)($mbs[0]['mbs'] ?? 0);

		_exit(
			'success',
			$return
		);
	}
}
new UserGetNodes();