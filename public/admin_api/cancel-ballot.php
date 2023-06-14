<?php
/**
 *
 * POST /admin/cancel-ballot
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param int $ballot_id
 *
 */
class AdminCancelBallot extends Endpoints {
	function __construct(
		$ballot_id = 0
	) {
		global $db, $helper;

		require_method('POST');

		$auth       = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';
		$ballot_id  = (int)(parent::$params['ballot_id'] ?? 0);
		$ballot     = $db->do_select("
			SELECT id
			FROM ballots
			WHERE id   = $ballot_id
			AND guid   = '$admin_guid'
			AND status = 'pending'
		");

		$ballot = $ballot[0] ?? array();

		if (!$ballot) {
			authenticate_session(3);
			$db->do_query("
				DELETE FROM ballots
				WHERE id = $ballot_id
			");
		}

		$db->do_query("
			DELETE FROM ballots
			WHERE id = $ballot_id
		");

		_exit(
			'success',
			'Successfully deleted ballot '
		);
	}
}
new AdminCancelBallot();
