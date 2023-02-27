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

		$auth       = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';
		$user_guid  = parent::$params['guid'] ?? '';

		$suspensions->reinstate_user($user_guid);

		_exit(
			'success',
			'Member reinstated'
		);
	}
}
new AdminReinstateUser();
