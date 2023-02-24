<?php
/**
 *
 * GET /admin/me
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class AdminMe extends Endpoints {
	function __construct() {
		global $db, $helper;

		require_method('GET');

		$auth       = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';
		$me         = $helper->get_user($admin_guid);

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
new AdminMe();