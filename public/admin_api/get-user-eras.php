<?php
/**
 *
 * GET /admin/get-user-eras
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param string $guid
 *
 */
class AdminGetUserEras extends Endpoints {
	function __construct(
		$guid = ''
	) {
		global $db, $helper;

		require_method('GET');

		$auth           = authenticate_session(2);
		$admin_guid     = $auth['guid'] ?? '';
		$current_era_id = $helper->get_current_era_id();
		$era_minus_360  = $current_era_id - 360;
		$guid           = parent::$params['guid'] ?? '';

		$helper->sanitize_input(
			$guid,
			true,
			Regex::$guid['char_limit'],
			Regex::$guid['char_limit'],
			Regex::$guid['pattern'],
			'GUID'
		);

		// define return object
		$return = array(
			"public_keys" => array(),
			"eras"        => array()
		);

		// nodes list
		$nodes = $db->do_select("
			SELECT a.public_key
			FROM  all_node_data AS a
			JOIN  user_nodes    AS b
			ON    a.public_key  = b.public_key
			WHERE a.era_id      = $current_era_id
			AND   b.guid        = '$guid'
			AND   b.verified    IS NOT NULL
			ORDER by a.public_key ASC
		");

		$nodes = $nodes ?? array();

		foreach ($nodes as $node) {
			$public_key = $node['public_key'] ?? '';

			if ($public_key) {
				$return["public_keys"][] = $public_key;
			}
		}

		// eras
		$eras = $db->do_select("
			SELECT
			a.public_key,
			a.era_id,
			a.created_at,
			a.in_current_era,
			a.in_auction,
			a.bid_inactive,
			a.uptime
			FROM  all_node_data AS a
			JOIN  user_nodes    AS b
			ON    a.public_key  = b.public_key
			WHERE b.guid        = '$guid'
			AND   b.verified    IS NOT NULL
			AND   a.era_id      > $era_minus_360
			ORDER BY a.era_id DESC
		");

		$eras = $eras ?? array();
		$sorted_eras = array();

		// for each node address's era
		foreach ($eras as $era) {
			$era_id          = $era['era_id'] ?? 0;
			$public_key      = $era['public_key'];
			$era_start_time  = $era['created_at'] ?? '';
			$era_start_time  = explode(" ", $era_start_time);
			$era_start_time1 = $era_start_time[0] ?? '';
			$era_start_time2 = $era_start_time[1] ?? '';

			if (!isset($sorted_eras['#'.$era_id])) {
				$sorted_eras['#'.$era_id] = array(
					"era_start_time1"  => $era_start_time1,
					"era_start_time2"  => $era_start_time2,
					"addresses"        => array()
				);
			}

			$sorted_eras['#'.$era_id]["addresses"][$public_key] = [
				"in_pool" => (int)($era['in_current_era']) && !(int)($era['bid_inactive']),
				"rewards" => round($era['uptime'], 3)
			];
		}

		$return["eras"] = $sorted_eras;

		_exit(
			'success',
			$return
		);
	}
}
new AdminGetUserEras();
