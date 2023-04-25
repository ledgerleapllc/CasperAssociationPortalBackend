<?php
/**
 *
 * POST /admin/delete-upgrade
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param int $upgrade_id
 *
 */
class AdminDeleteUpgrade extends Endpoints {
	function __construct(
		$upgrade_id = 0,
	) {
		global $db, $helper;

		require_method('POST');

		$auth        = authenticate_session(2);
		$admin_guid  = $auth['guid'] ?? '';

		$db->do_query("
			DELETE FROM upgrades
			WHERE id = $upgrade_id
		");

		_exit(
			'success',
			'Protocol upgrade removed'
		);
	}
}
new AdminDeleteUpgrade();