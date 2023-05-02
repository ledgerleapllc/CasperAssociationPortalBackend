<?php
/**
 *
 * GET /user/get-upgrades
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class UserGetUpgrades extends Endpoints {
	function __construct() {
		global $db, $helper;

		require_method('GET');

		$auth      = authenticate_session(1);
		$user_guid = $auth['guid'] ?? '';

		$upgrades  = $db->do_select("
			SELECT *
			FROM upgrades
			ORDER BY activate_era DESC
		");

		$upgrades = $upgrades ?? array();

		foreach ($upgrades as &$upgrade) {
			$version  = $upgrade['version'] ?? '';
			$upgraded = $db->do_select("
				SELECT status
				FROM  user_upgrades
				WHERE guid    = '$user_guid'
				AND   version = '$version'
				AND   status  = 'complete'
			");

			if ($upgraded) {
				$upgrade['upgraded'] = true;
			} else {
				$upgrade['upgraded'] = false;
			}
		}

		_exit(
			'success',
			$upgrades
		);
	}
}
new UserGetUpgrades();