<?php
/**
 *
 * GET /user/get-active-ballots
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class UserGetActiveBallots extends Endpoints {
	function __construct() {
		global $db, $helper, $pagelock;

		require_method('GET');

		$auth      = authenticate_session(1);
		$user_guid = $auth['guid'] ?? '';

		// 403 if page is locked from user access due to KYC or probation
		$pagelock->check($user_guid, 'votes');

		$ballots = $db->do_select("
			SELECT
			a.id,
			a.guid,
			a.title,
			a.description,
			a.start_time,
			a.end_time,
			a.status,
			a.created_at,
			a.updated_at
			FROM ballots AS a
			WHERE a.status = 'active'
			OR    a.status = 'pending'
			ORDER BY a.start_time ASC
		");

		$ballots = $ballots ?? array();

		foreach ($ballots as &$ballot) {
			$ballot_id     = (int)($ballot['id'] ?? 0);
			$status        = $ballot['status'] ?? '';

			$for_votes = $db->do_select("
				SELECT count(guid) AS vCount
				FROM votes
				WHERE ballot_id = $ballot_id
				AND   direction = 'for'
			");
			$for_votes = (int)($for_votes[0]['vCount'] ?? 0);

			$against_votes = $db->do_select("
				SELECT count(guid) AS vCount
				FROM votes
				WHERE ballot_id = $ballot_id
				AND   direction = 'against'
			");
			$against_votes = (int)($against_votes[0]['vCount'] ?? 0);

			if ($status == 'done') {
				if ($for_votes > $against_votes) {
					$ballot['for_against'] = 'Passing '.$for_votes.'/'.$against_votes;
				}

				if ($for_votes < $against_votes) {
					$ballot['for_against'] = 'Failing '.$for_votes.'/'.$against_votes;
				}

				if ($for_votes == $against_votes) {
					$ballot['for_against'] = 'Tied '.$for_votes.'/'.$against_votes;
				}
			}

			$ballot['total_votes'] = (
				(int)$for_votes +
				(int)$against_votes
			);

			$now   = time();
			$end   = strtotime($ballot['end_time'].' UTC');
			$start = strtotime($ballot['start_time'].' UTC');

			$numerator   = $end - $now;
			$denominator = $end - $start;
			$numerator   = $numerator <= 0 ? 1 : $numerator;
			$denominator = $denominator <= 0 ? 1 : $denominator;

			$r = $helper->get_timedelta($numerator);

			$ballot['time_remaining']      = $r;
			$ballot['time_remaining_perc'] = round($numerator / $denominator * 100);

			if ($ballot['time_remaining_perc'] < 0) {
				$ballot['time_remaining_perc'] = 0;
			}

			// my vote
			$myvote = $db->do_select("
				SELECT direction
				FROM  votes
				WHERE ballot_id = $ballot_id
				AND   guid      = '$user_guid'
			");
			$ballot['my_vote'] = $myvote[0]['direction'] ?? '';
		}

		_exit(
			'success',
			$ballots
		);
	}
}
new UserGetActiveBallots();
