<?php
/**
 *
 * PUT /admin/reinstate-user
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param string  $guid
 *
 */
class AdminReinstateUser extends Endpoints {
	function __construct(
		$guid = ''
	) {
		global $db, $helper, $suspensions;

		require_method('PUT');

		$auth      = authenticate_session(2);
		$guid      = $auth['guid'] ?? '';
		$user_guid = parent::$params['guid'] ?? '';

		$suspensions->reset_user_suspension($user_guid);

		_exit(
			'success',
			'Member has been refused reentry'
		);
	}
}
new AdminReinstateUser();