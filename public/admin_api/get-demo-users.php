<?php
/**
 *
 * GET /admin/get-demo-users
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class AdminGetDemoUsers extends Endpoints {
	function __construct() {
		global $db, $helper;

		require_method('GET');

		$auth        = authenticate_session(2);
		$admin_guid  = $auth['guid'] ?? '';

		$demo_users  = $db->do_select("
			SELECT 
			a.guid,
			a.public_key,
			b.email
			FROM user_nodes AS a
			JOIN users      AS b
			ON    a.guid = b.guid
			WHERE a.verified IS NULL
		") ?? array();

		_exit(
			'success',
			$demo_users
		);
	}
}
new AdminGetDemoUsers();