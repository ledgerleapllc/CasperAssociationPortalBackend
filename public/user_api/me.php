<?php
/**
 *
 * GET /user/me
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class UserMe extends Endpoints {
	function __construct() {
		global $db, $helper;

		require_method('GET');

		$auth      = authenticate_session();
		$user_guid = $auth['guid'] ?? '';
		$me        = $helper->get_user($user_guid);

		if($me) {
			_exit(
				'success',
				$me
			);
		}

		_exit(
			'error',
			'Unauthorized',
			403,
			'Unauthorized'
		);
	}
}
new UserMe();