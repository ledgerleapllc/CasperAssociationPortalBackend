<?php
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
			FROM  user_nodes
			WHERE guid = '$user_guid'
			AND   verified IS NOT NULL
		");

		$user_nodes = $user_nodes ?? array();

		$setting_eras_since_redmark    = (int)($helper->fetch_setting('eras_since_redmark'));
		$setting_eras_required_to_vote = (int)($helper->fetch_setting('eras_required_to_vote'));

		$return = array(
			"can_vote"         => false,
			"eras_of_history"  => null,
			"history_required" => $setting_eras_required_to_vote,
			"redmarks"         => 0,
			"redmarks_window"  => $setting_eras_since_redmark
		);

		foreach ($user_nodes as $node) {
			$public_key    = $node['public_key'] ?? '';

			$node_era_data = $helper->get_era_data(
				$public_key,
				$setting_eras_since_redmark
			);

			// redmarks window
			$redmarks = $node_era_data['total_redmarks'];

			if ($return["redmarks"] === null) {
				$return["redmarks"] = $redmarks;
			} else {
				if ($redmarks > $return["redmarks"]) {
					$return["redmarks"] = $redmarks;
				}
			}

			// required history
			$eras_of_history = $node_era_data['total_eras'];

			if ($eras_of_history > $setting_eras_required_to_vote) {
				$eras_of_history = $setting_eras_required_to_vote;
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
			$return["redmarks"] = 0;
		}

		if ($return["eras_of_history"] === null) {
			$return["eras_of_history"] = 0;
		}

		if (
			$return["redmarks"]        <= 0 &&
			$return["eras_of_history"] >= $setting_eras_required_to_vote
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
