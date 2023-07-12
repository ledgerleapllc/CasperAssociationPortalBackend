<?php
/**
 *
 * POST /user/vote
 *
 * HEADER Authorization: Bearer
 *
 * 'flip' direction only works when a vote for this user by ballot ID is found to exist.
 *
 * @api
 * @param int    $ballot_id
 * @param string $direction  enum('for', 'against', 'flip')
 *
 */
class UserVote extends Endpoints {
	function __construct(
		$ballot_id = 0,
		$direction = 'for'
	) {
		global $db, $helper, $pagelock;

		require_method('POST');

		$auth       = authenticate_session(1);
		$user_guid  = $auth['guid'] ?? '';

		// 403 if page is locked from user access due to KYC or probation
		$pagelock->check($user_guid, 'votes');

		// double check voting eligibility
		$user_nodes = $db->do_select("
			SELECT public_key
			FROM  user_nodes
			WHERE guid = '$user_guid'
			AND   verified IS NOT NULL
		") ?? array();

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

		if (!$return["can_vote"]) {
			_exit(
				'error',
				"You are not eligibile to vote at this time",
				400,
				"You are not eligibile to vote at this time"
			);
		}

		// process the request
		$ballot_id  = (int)(parent::$params['ballot_id'] ?? 0);
		$direction  = parent::$params['direction'] ?? '';
		$created_at = $helper->get_datetime();

		if (
			$direction != 'for' &&
			$direction != 'against' &&
			$direction != 'flip'
		) {
			_exit(
				'error',
				"Vote must be cast either 'for' or 'against'",
				400,
				"Vote must be cast either 'for' or 'against'"
			);
		}

		// only for flipping a vote
		if ($direction == 'flip') {
			$check = $db->do_select("
				SELECT direction
				FROM votes
				WHERE guid = '$user_guid'
				AND ballot_id = $ballot_id
			");
			$check = $check[0]['direction'] ?? '';

			if (!$check) {
				_exit(
					'error',
					"You can't flip a vote you haven't cast yet",
					400,
					"You can't flip a vote you haven't cast yet"
				);
			}

			switch ($check) {
				case 'for':     $new_direction = 'against'; break;
				case 'against': $new_direction = 'for';     break;
				default: 'for'; break;
			}

			$db->do_query("
				UPDATE votes
				SET
				direction       = '$new_direction',
				updated_at      = '$created_at'
				WHERE ballot_id = $ballot_id
				AND   guid      = '$user_guid'
			");

			_exit(
				'success',
				'Your vote cast for this ballot changed to '.$new_direction
			);
		}

		$check = $db->do_select("
			SELECT id
			FROM  votes
			WHERE guid      = '$user_guid'
			AND   ballot_id = $ballot_id
		");

		if ($check) {
			_exit(
				'error',
				"You have already cast a vote for this ballot",
				403,
				"You have already cast a vote for this ballot"
			);
		}

		$check = $db->do_select("
			SELECT status
			FROM  ballots
			WHERE id = $ballot_id
		");
		$check = $check[0]['status'] ?? '';

		if (!$check) {
			_exit(
				'error',
				"Ballot does not exist",
				400,
				"Ballot does not exist"
			);
		}

		if ($check == 'pending') {
			_exit(
				'error',
				"Cannot cast a vote on a ballot that is still pending",
				403,
				"Cannot cast a vote on a ballot that is still pending"
			);
		}

		if ($check == 'done') {
			_exit(
				'error',
				"Voting for this ballot has concluded",
				403,
				"Voting for this ballot has concluded"
			);
		}

		$result = $db->do_query("
			INSERT INTO votes (
				guid,
				ballot_id,
				direction,
				created_at,
				updated_at
			) VALUES (
				'$user_guid',
				$ballot_id,
				'$direction',
				'$created_at',
				'$created_at'
			)
		");

		_exit(
			'success',
			'Your vote has been cast'
		);
	}
}
new UserVote();
