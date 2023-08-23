<?php
/**
 *
 * GET /admin/get-emailer-admins
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class AdminGetEmailerAdmins extends Endpoints {
	function __construct() {
		global $db, $helper;

		require_method('GET');

		$auth = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';

		$contacts = $db->do_select("
			SELECT
			guid,
			email,
			created_at
			FROM emailer_admins
		") ?? array();

		_exit(
			'success',
			$contacts
		);
	}
}
new AdminGetEmailerAdmins();
