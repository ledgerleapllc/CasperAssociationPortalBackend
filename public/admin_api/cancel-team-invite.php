<?php
/**
 *
 * POST /admin/cancel-team-invite
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param string $email
 *
 */
class AdminCancelTeamInvite extends Endpoints {
	function __construct(
		$email = ''
	) {
		global $db, $helper;

		require_method('POST');

		$auth       = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';
		$email      = (string)(parent::$params['email'] ?? '');

		// check
		$check = $db->do_select("
			SELECT *
			FROM team_invites
			WHERE email = '$email'
			AND accepted_at IS NULL
		");

		if ($check) {
			$db->do_query("
				DELETE FROM team_invites
				WHERE email = '$email'
			");
		}

		_exit(
			'success',
			'Member invitation cancelled'
		);
	}
}
new AdminCancelTeamInvite();