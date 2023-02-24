<?php
/**
 *
 * GET /admin/get-permissions
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param string $guid
 *
 */
class AdminGetPermissions extends Endpoints {
	function __construct(
		$guid = ''
	) {
		global $db, $helper, $permissions;

		require_method('GET');

		$auth       = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';
		$guid       = parent::$params['guid'] ?? '';

		$permission = $db->do_select("
			SELECT *
			FROM permissions
			WHERE guid = '$guid'
		");

		$permission = $permission[0] ?? array();

		_exit(
			'success',
			$permission
		);
	}
}
new AdminGetPermissions();