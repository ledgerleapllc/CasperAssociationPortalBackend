<?php
include_once('../../core.php');
/**
 *
 * GET /admin/get-upgrades
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class AdminGetUpgrades extends Endpoints {
	function __construct() {
		global $db, $helper;

		require_method('GET');

		$auth       = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';

		$upgrades  = $db->do_select("
			SELECT *
			FROM upgrades
			ORDER BY id DESC
		");

		$upgrades = $upgrades ?? array();

		_exit(
			'success',
			$upgrades
		);
	}
}
new AdminGetUpgrades();