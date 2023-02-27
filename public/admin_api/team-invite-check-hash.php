<?php
/**
 *
 * GET /admin/team-invite-check-hash
 *
 * @api
 * @param string $hash
 *
 */
class AdminTeamInviteCheckHash extends Endpoints {
	function __construct(
		$hash = ''
	) {
		global $db, $helper;

		require_method('GET');

		$hash              = parent::$params['hash'] ?? '';
		$uri               = $helper->aes_decrypt($hash);
		$uri               = explode('::', $uri);
		$guid              = $uri[0];
		$email             = $uri[1] ?? '';
		$confirmation_code = $uri[2] ?? '';
		$time              = (int)($uri[3] ?? 0);

		$check = $db->do_select("
			SELECT *
			FROM team_invites
			WHERE guid = '$guid'
			AND confirmation_code = '$confirmation_code'
			AND email = '$email'
			AND accepted_at IS NULL
		");

		if ($check) {
			// user check
			$user = $db->do_select("
				SELECT *
				FROM users
				WHERE email = '$email'
			");

			if ($user) {
				_exit(
					'success',
					'existing'
				);
			} else {
				_exit(
					'success',
					'new'
				);
			}
		}

		_exit(
			'error',
			'Invalid/expired invitation',
			400,
			'Invalid team invite hash'
		);
	}
}
new AdminTeamInviteCheckHash();
