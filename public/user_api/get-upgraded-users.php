<?php
/**
 *
 * GET /user/get-upgraded-users
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param string $version
 *
 */
class UserGetUpgradedUsers extends Endpoints {
	function __construct(
		$version = ''
	) {
		global $db, $helper;

		require_method('GET');

		$auth      = authenticate_session(1);
		$user_guid = $auth['guid'] ?? '';
		$version   = parent::$params['version'] ?? '';

		$users     = $db->do_select("
			SELECT
			a.public_key,
			b.guid,
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
			AND b.role = 'user'
		");

		$users = $users ?? array();

		_exit(
			'success',
			$users
		);
	}
}
new UserGetUpgradedUsers();