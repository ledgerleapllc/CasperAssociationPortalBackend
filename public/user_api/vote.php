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
				400
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
					400
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
				403
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
				400
			);
		}

		if ($check == 'pending') {
			_exit(
				'error',
				"Cannot cast a vote on a ballot that is still pending",
				403
			);
		}

		if ($check == 'done') {
			_exit(
				'error',
				"Voting for this ballot has concluded",
				403
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