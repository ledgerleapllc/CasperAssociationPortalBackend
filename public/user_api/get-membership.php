<?php
include_once('../../core.php');
/**
 *
 * GET /user/get-membership
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class UserGetMembership extends Endpoints {
	function __construct() {
		global $db, $helper;

		require_method('GET');

		$auth           = authenticate_session(1);
		$user_guid      = $auth['guid'] ?? '';
		$current_era_id = $helper->get_current_era_id();

		// define return object
		$return = array(
			"node_status"        => "Offline",
			"kyc_status"         => "Not Verified",
			"uptime"             => 0,
			"total_eras"         => 0,
			"eras_since_redmark" => 0,
			"total_redmarks"     => 0,
			"updates"            => 0
		);

		$nodes = $db->do_select("
			SELECT a.status
			FROM  all_node_data AS a
			JOIN  user_nodes    AS b
			ON    a.public_key  = b.public_key
			WHERE a.era_id      = $current_era_id
			AND   b.guid        = '$user_guid'
			AND   b.verified    IS NOT NULL
		");

		$nodes    = $nodes ?? array();
		$statuses = array();

		foreach ($nodes as $node) {
			$status     = $node['status'] ?? 'Offline';
			$statuses[] = ucfirst($status);
		}

		$statuses = array_unique($statuses);
		$return['node_status'] = (
			!empty($statuses) ? 
			implode(', ', $statuses) : 
			$return['node_status']
		);

		$kyc_status = $db->do_select("
			SELECT status
			FROM shufti
			WHERE guid = '$user_guid'
		");

		$return['kyc_status'] = ucfirst($kyc_status[0]['status'] ?? 'Not Verified');

		$nodes = $db->do_select("
			SELECT 
			a.public_key,
			a.uptime,
			a.historical_performance
			FROM  all_node_data AS a
			JOIN  user_nodes    AS b
			ON    a.public_key  = b.public_key
			WHERE a.era_id      = $current_era_id
			AND   b.guid        = '$user_guid'
			AND   b.verified    IS NOT NULL
		");

		$nodes = $nodes ?? array();

		foreach ($nodes as $node) {
			$public_key = $node['public_key'] ?? '';
			$uptime     = (float)($node['historical_performance'] ?? 0);
			$return['uptime'] += $uptime;

			$node_eras = $db->do_select("
				SELECT count(era_id) AS eCount
				FROM  all_node_data
				WHERE in_current_era = 1
				AND   bid_inactive   = 0
				AND   public_key     = '$public_key'
			");

			$eCount = (int)($node_eras[0]['eCount'] ?? 0);

			if ($eCount > $return['total_eras']) {
				$return['total_eras'] = $eCount;
			}

			$node_eras_since_redmark = $db->do_select("
				SELECT era_id
				FROM all_node_data
				WHERE public_key = '$public_key'
				AND (
					in_current_era = 0 OR
					bid_inactive   = 1
				)
				ORDER BY era_id DESC
				LIMIT 1;
			");
			$node_eras_since_redmark = (int)($node_eras_since_redmark['era_id'] ?? 0);
			$node_eras_since_redmark = $current_era_id - $node_eras_since_redmark;
			$node_eras_since_redmark = $node_eras_since_redmark < 0 ?? 0;

			if ($node_eras_since_redmark < $return['eras_since_redmark']) {
				$return['eras_since_redmark'] = $node_eras_since_redmark;
			}

			$total_redmarks = $db->do_select("
				SELECT count(era_id) AS eCount
				FROM all_node_data
				WHERE public_key = '$public_key'
				AND (
					in_current_era = 0 OR
					bid_inactive   = 1
				)
			");
			$return['total_redmarks'] += (int)($total_redmarks[0]['eCount'] ?? 0);
			$return['updates'] = 100;;
		}

		$nodes_count      = count($nodes) < 1 ? 1 : count($nodes);
		$return['uptime'] = round($return['uptime'] / $nodes_count, 2);

		_exit(
			'success',
			$return
		);
	}
}
new UserGetMembership();