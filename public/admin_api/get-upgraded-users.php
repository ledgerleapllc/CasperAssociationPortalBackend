<?php
/**
 *
 * GET /admin/get-upgraded-users
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param string $version
 *
 */
class AdminGetUpgradedUsers extends Endpoints {
	function __construct(
		$version = ''
	) {
		global $db, $helper;

		require_method('GET');

		$auth       = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';
		$version    = parent::$params['version'] ?? '';

		$users     = $db->do_select("
			SELECT
			a.public_key,
			b.guid,
			b.email,
			b.pseudonym,
			c.status AS upgrade_status,
			c.created_at AS upgraded_at
			FROM user_nodes AS a
			LEFT JOIN users AS b
			ON a.guid = b.guid
			LEFT JOIN user_upgrades AS c
			ON b.guid = c.guid
			AND c.version = '$version'
			WHERE a.verified IS NOT NULL
		");

		$users = $users ?? array();

		_exit(
			'success',
			$users
		);
	}
}
new AdminGetUpgradedUsers();
