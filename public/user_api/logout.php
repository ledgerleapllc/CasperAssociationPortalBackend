<?php
/**
 *
 * GET /user/logout
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class UserLogout extends Endpoints {
	function __construct() {
		global $authentication;

		require_method('GET');

		$auth = authenticate_session();
		$guid = $auth['guid'] ?? '';

		$authentication->clear_session($guid);

		_exit(
			'success',
			'Session terminated'
		);
	}
}
new UserLogout();