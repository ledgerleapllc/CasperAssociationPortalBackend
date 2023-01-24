<?php
include_once('../../core.php');
/**
 *
 * PUT /admin/remove-site-badge
 *
 * HEADER Authorization: Bearer
 *
 * Resets a user's site badge URL and verification.
 *
 * @param string  $user_guid
 *
 */
class AdminRemoveSiteBadge extends Endpoints {
	function __construct(
		$user_guid = ''
	) {
		global $db, $helper;

		require_method('PUT');

		$auth = authenticate_session(2);
		$guid = $auth['guid'] ?? '';
		$user_guid = parent::$params['user_guid'] ?? '';

		$query = "
			UPDATE users
			SET badge_partner = 0, badge_partner_link = ''
			WHERE guid = '$user_guid'
		";
		elog($query);
		$done = $db->do_query($query);

		if($done) {
			_exit(
				'success',
				'Badge partner has been reset'
			);
		}

		_exit(
			'error',
			'There was a problem removing badge',
			500,
			'There was a problem removing badge'
		);
	}
}
new AdminRemoveSiteBadge();