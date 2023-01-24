<?php
include_once('../../core.php');
/**
 *
 * GET /admin/get-finished-ballots
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class AdminGetFinishedBallots extends Endpoints {
	function __construct() {
		global $db, $helper;

		require_method('GET');

		$auth       = authenticate_session(1);
		$admin_guid = $auth['guid'] ?? '';
		$ballots    = $db->do_select("
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
			WHERE a.status = 'done'
			ORDER BY a.updated_at DESC
		");

		$ballots = $ballots ?? array();

		foreach ($ballots as &$ballot) {
			$ballot_id = (int)($ballot['id'] ?? 0);
			$for_votes = $db->do_select("
				SELECT count(guid) AS vCount
				FROM votes
				WHERE ballot_id = $ballot_id
				AND direction = 'for'
			");
			$for_votes = (int)($for_votes[0]['vCount'] ?? 0);

			$against_votes = $db->do_select("
				SELECT count(guid) AS vCount
				FROM votes
				WHERE ballot_id = $ballot_id
				AND direction = 'against'
			");
			$against_votes = (int)($against_votes[0]['vCount'] ?? 0);

			if ($for_votes > $against_votes) {
				$ballot['for_against'] = 'Passed '.$for_votes.'/'.$against_votes;
			}

			if ($for_votes < $against_votes) {
				$ballot['for_against'] = 'Failed '.$for_votes.'/'.$against_votes;
			}

			if ($for_votes == $against_votes) {
				$ballot['for_against'] = 'Tied '.$for_votes.'/'.$against_votes;
			}

			$ballot['total_votes'] = $for_votes + $against_votes;
		}

		_exit(
			'success',
			$ballots
		);
	}
}
new AdminGetFinishedBallots();