<?php
/**
 *
 * GET /admin/get-perks
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class AdminGetPerks extends Endpoints {
	function __construct() {
		global $db, $helper;

		require_method('GET');

		$auth       = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';

		$perks      = $db->do_select("
			SELECT *
			FROM perks
		");

		$perks = $perks ?? array();

		_exit(
			'success',
			$perks
		);
	}
}
new AdminGetPerks();
