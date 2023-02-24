<?php
/**
 *
 * GET /admin/get-ballot
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param int    $ballot_id
 *
 */
class AdminGetBallot extends Endpoints {
	function __construct(
		$ballot_id = 0
	) {
		global $db, $helper;

		require_method('GET');

		$auth       = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';
		$ballot_id  = (int)(parent::$params['ballot_id'] ?? 0);
		$ballot     = $db->do_select("
			SELECT 
			a.id,
			a.guid, 
			a.title, 
			a.description,
			a.start_time,
			a.end_time,
			a.status,
			a.file_url,
			a.created_at, 
			a.updated_at
			FROM ballots AS a
			LEFT JOIN votes AS b
			ON a.id = b.ballot_id
			WHERE a.id = $ballot_id
			ORDER BY a.updated_at DESC
		");

		$ballot    = $ballot[0] ?? array();
		$ballot_id = (int)($ballot['id'] ?? 0);

		if (empty($ballot)) {
			_exit(
				'error',
				'Invalid ballot ID',
				404
			);
		}

		// for/against percs
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

		$total_votes             = $for_votes + $against_votes;
		$total_votes             = $total_votes == 0 ? 1 : $total_votes;
		$ballot['votes_for']     = round($for_votes / $total_votes * 100, 1);
		$ballot['votes_against'] = round($against_votes / $total_votes * 100, 1);

		// time remaining
		$now   = time();
		$end   = strtotime($ballot['end_time'].' UTC');
		$start = strtotime($ballot['start_time'].' UTC');

		$numerator   = $end - $now;
		$denominator = $end - $start;
		$numerator   = $numerator <= 0 ? 1 : $numerator;
		$denominator = $denominator <= 0 ? 1 : $denominator;

		date_default_timezone_set('GMT');
		$r = date("d:H:i:s", $numerator);

		$ballot['time_remaining']      = $r;
		$ballot['time_remaining_perc'] = round($numerator / $denominator * 100, 4);

		if ($ballot['time_remaining_perc'] < 0) {
			$ballot['time_remaining_perc'] = 0;
		}

		_exit(
			'success',
			$ballot
		);
	}
}
new AdminGetBallot();