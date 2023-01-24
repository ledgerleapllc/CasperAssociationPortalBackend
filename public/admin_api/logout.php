<?php
include_once('../../core.php');
/**
 *
 * GET /admin/logout
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class AdminLogout extends Endpoints {
	function __construct() {
		global $authentication;

		require_method('GET');

		$auth = authenticate_session(2);
		$guid = $auth['guid'] ?? '';

		$authentication->clear_session($guid);

		_exit(
			'success',
			'Session terminated'
		);
	}
}
new AdminLogout();