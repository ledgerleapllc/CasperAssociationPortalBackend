<?php
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
			"eras_since_redmark" => 999999999,
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
			$uptime     = (float)($node['uptime'] ?? 0);
			$return['uptime'] += $uptime;
			$return['updates'] = 100;

			$era_data = $helper->get_era_data($public_key);

			$return['total_redmarks'] += $era_data['total_redmarks'];

			if ($return['total_eras'] < $era_data['total_eras']) {
				$return['total_eras'] = $era_data['total_eras'];
			}

			if ($era_data['eras_since_redmark'] < $return['eras_since_redmark']) {
				$return['eras_since_redmark'] = $era_data['eras_since_redmark'];
			}
		}

		if ($return['eras_since_redmark'] > $return['total_eras']) {
			$return['eras_since_redmark'] = $return['total_eras'];
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
