<?php
include_once('../../core.php');
/**
 *
 * GET /admin/get-nodes
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class AdminGetNodes extends Endpoints {
	function __construct() {
		global $db, $helper;

		require_method('GET');

		$auth           = authenticate_session(2);
		$current_era_id = $helper->get_current_era_id();

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
			WHERE a.era_id = $current_era_id
			AND b.verified IS NOT NULL
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
			SELECT
			public_key, uptime,
			bid_delegators_count,
			bid_delegation_rate,
			bid_total_staked_amount
			FROM all_node_data
			WHERE era_id = $current_era_id
			AND in_current_era = 1
			AND in_next_era = 1
			AND in_auction = 1
		");
		$max_delegators = 0;
		$max_stake_amount = 0;

		foreach ($ranking as $r) {
			if ((int)$r['bid_delegators_count'] > $max_delegators) {
				$max_delegators   = (int)$r['bid_delegators_count'];
			}
			if ((int)$r['bid_total_staked_amount'] > $max_stake_amount) {
				$max_stake_amount = (int)$r['bid_total_staked_amount'];
			}
		}

		foreach ($ranking as $r) {
			$uptime_score = (float)(25 * (float)$r['uptime'] / 100);
			$uptime_score = $uptime_score < 0 ? 0 : $uptime_score;

			$fee_score = 25 * (1 - (float)((float)$r['bid_delegation_rate'] / 100));
			$fee_score = $fee_score < 0 ? 0 : $fee_score;

			$count_score = (float)((float)$r['bid_delegators_count'] / $max_delegators) * 25;
			$count_score = $count_score < 0 ? 0 : $count_score;

			$stake_score = (float)((float)$r['bid_total_staked_amount'] / $max_stake_amount) * 25;
			$stake_score = $stake_score < 0 ? 0 : $stake_score;

			$return["ranking"][$r['public_key']] = $uptime_score + $fee_score + $count_score + $stake_score;
		}

		uasort($return["ranking"], function($x, $y) {
			if ($x == $y) {
				return 0;
			}
			return ($x > $y) ? -1 : 1;
		});

		$sorted_ranking = [];
		$i = 1;

		foreach ($return["ranking"] as $public_key => $score) {
			$sorted_ranking[$public_key] = $i;
			$i += 1;
		}

		$return["ranking"] = $sorted_ranking;
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
new AdminGetNodes();