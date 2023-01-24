<?php
include_once('../../core.php');
/**
 *
 * GET /user/get-vote-eligibility
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class UserGetVoteEligibility extends Endpoints {
	function __construct() {
		global $db, $helper, $pagelock;

		require_method('GET');

		$auth           = authenticate_session(1);
		$user_guid      = $auth['guid'] ?? '';
		$current_era_id = $helper->get_current_era_id();

		// 403 if page is locked from user access due to KYC or probation
		$pagelock->check($user_guid, 'votes');

		// define nodes and return object
		$user_nodes = $db->do_select("
			SELECT public_key
			FROM user_nodes
			WHERE guid = '$user_guid'
			AND verified IS NOT NULL
		");

		$user_nodes = $user_nodes ?? array();

		$setting_eras_since_redmark = (int)($helper->fetch_setting('eras_since_redmark'));
		$setting_minimum_eras       = (int)($helper->fetch_setting('minimum_eras'));

		$return = array(
			"can_vote"         => false,
			"eras_of_history"  => null,
			"history_required" => $setting_minimum_eras,
			"redmarks"         => 0,
			"redmarks_window"  => $setting_eras_since_redmark
		);

		foreach ($user_nodes as $node) {
			$public_key  = $node['public_key'] ?? '';
			$past_era_id = $current_era_id - $setting_eras_since_redmark;

			// redmarks window
			$redmarks = $db->do_select("
				SELECT count(id) AS eCount
				FROM   all_node_data
				WHERE  public_key  = '$public_key'
				AND (
					in_current_era = 0 OR
					bid_inactive   = 1
				)
				AND era_id        >= $past_era_id
			");
			$redmarks = (int)($redmarks[0]['eCount'] ?? 0);

			if ($return["redmarks"] === null) {
				$return["redmarks"] = $redmarks;
			} else {
				if ($redmarks > $return["redmarks"]) {
					$return["redmarks"] = $redmarks;
				}
			}

			// required history
			$eras_of_history = $db->do_select("
				SELECT count(id) AS eCount
				FROM   all_node_data
				WHERE  public_key = '$public_key'
				AND    in_current_era = 1
				AND    bid_inactive   = 0
			");
			$eras_of_history = (int)($eras_of_history[0]['eCount'] ?? 0);

			if ($eras_of_history > $setting_minimum_eras) {
				$eras_of_history = $setting_minimum_eras;
			}

			if ($return["eras_of_history"] === null) {
				$return["eras_of_history"] = $eras_of_history;
			} else {
				if ($eras_of_history < $return["eras_of_history"]) {
					$return["eras_of_history"] = $eras_of_history;
				}
			}
		}

		if ($return["redmarks"] === null) {
			$return["redmarks"] = $setting_eras_since_redmark;
		}

		if ($return["eras_of_history"] === null) {
			$return["eras_of_history"] = 0;
		}

		if (
			$return["redmarks"]        <= 0 &&
			$return["eras_of_history"] >= $setting_minimum_eras
		) {
			$return["can_vote"] = true;
		}

		_exit(
			'success',
			$return
		);
	}
}
new UserGetVoteEligibility();