<?php
include_once('../../core.php');
/**
 *
 * GET /public/get-node-data
 *
 * @api
 * @param string $public_key
 *
 */
class PublicGetNodeData extends Endpoints {
	function __construct(
		$public_key = ''
	) {
		global $db, $helper;

		require_method('GET');

		$public_key = parent::$params['public_key'] ?? null;

		if (!$public_key) {
			$public_key = null;
		}

		$helper->sanitize_input(
			$public_key,
			true,
			Regex::$validator_id['char_limit'] - 2,
			Regex::$validator_id['char_limit'],
			Regex::$validator_id['pattern'],
			'Public Key'
		);

		// define return object
		$return = array(
			"node_status"        => "Offline",
			"stake_amount"       => 0,
			"delegators"         => 0,
			"uptime"             => 0,
			"total_eras"         => 0,
			"eras_since_redmark" => 0,
			"total_redmarks"     => 0,

			// extras for profile page
			"fee"                => 0,
			"self_stake"         => 0
		);

		// get current node data first
		$current_era_id = $helper->get_current_era_id();
		$node_info = $db->do_select("
			SELECT *
			FROM all_node_data
			WHERE era_id = $current_era_id
			AND public_key = '$public_key'
		");
		$node_info = $node_info[0] ?? array();

		$return["node_status"]  = ucfirst($node_info["status"] ?? "");
		$return["stake_amount"] = (int)($node_info["bid_total_staked_amount"] ?? 0);
		$return["delegators"]   = (int)($node_info["bid_delegators_count"] ?? 0);
		$return["uptime"]       = round((float)($node_info["uptime"] ?? 0), 2);

		$era_data = $helper->get_era_data($public_key);

		$return["total_eras"]         = $era_data["total_eras"];
		$return["total_redmarks"]     = $era_data["total_redmarks"];
		$return["eras_since_redmark"] = $era_data["eras_since_redmark"];

		// extras
		$return["fee"]        = (float)($node_info["bid_delegation_rate"] ?? 0);
		$return["self_stake"] = (int)($node_info["bid_self_staked_amount"] ?? 0);

		_exit(
			'success',
			$return
		);
	}
}
new PublicGetNodeData();