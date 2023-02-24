<?php
/**
 *
 * GET /admin/get-historic-revoked-users
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class AdminGetHistoricRevokedUsers extends Endpoints {
	function __construct() {
		global $db, $helper;

		require_method('GET');

		$auth       = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';

		$users = $db->do_select("
			SELECT 
			a.guid,
			a.reinstatable,
			a.reason,
			a.letter,
			a.created_at AS suspended_at,
			a.updated_at,
			a.reinstated_at,
			a.decision,
			b.email,
			b.pseudonym,
			b.account_type,
			b.created_at AS registration_date,
			b.pii_data
			FROM suspensions AS a
			JOIN users       AS b
			ON    a.guid       = b.guid
			WHERE a.reinstated = 1
			OR    a.decision   IS NOT NULL
		");

		$users = $users ?? array();

		_exit(
			'success',
			$users
		);
	}
}
new AdminGetHistoricRevokedUsers();