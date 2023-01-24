<?php
include_once('../../core.php');
/**
 *
 * GET /user/get-my-votes
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class UserGetMyVotes extends Endpoints {
	function __construct() {
		global $db, $helper, $pagelock;

		require_method('GET');

		$auth      = authenticate_session(1);
		$user_guid = $auth['guid'] ?? '';

		// 403 if page is locked from user access due to KYC or probation
		$pagelock->check($user_guid, 'votes');

		$votes     = $db->do_select("
			SELECT 
			a.id,
			a.direction,
			a.created_at,
			a.updated_at,
			a.ballot_id,
			b.title
			FROM votes AS a
			LEFT JOIN ballots AS b
			ON a.ballot_id = b.id
			WHERE a.guid = '$user_guid'
		");

		$votes = $votes ?? array();

		foreach ($votes as &$vote) {
			$ballot_id   = (int)($vote['ballot_id'] ?? 0);
			$total_votes = $db->do_select("
				SELECT count(id) AS total_votes
				FROM votes
				WHERE ballot_id = $ballot_id
			");
			$vote['total_votes'] = (int)($total_votes[0]['total_votes'] ?? 0);

			$votes_for = $db->do_select("
				SELECT count(id) AS votes_for
				FROM votes
				WHERE ballot_id = $ballot_id
				AND direction = 'for'
			");
			$vote['votes_for'] = (int)($votes_for[0]['votes_for'] ?? 0);

			$votes_against = $db->do_select("
				SELECT count(id) AS votes_against
				FROM votes
				WHERE ballot_id = $ballot_id
				AND direction = 'against'
			");
			$vote['votes_against'] = (int)($votes_against[0]['votes_against'] ?? 0);
			$vote['for_against']   = $vote['votes_for'].'/'.$vote['votes_against'];
		}

		_exit(
			'success',
			$votes
		);
	}
}
new UserGetMyVotes();