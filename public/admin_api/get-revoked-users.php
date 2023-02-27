<?php
/**
 *
 * GET /admin/get-revoked-users
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class AdminGetRevokedUsers extends Endpoints {
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
			b.email,
			b.pseudonym,
			b.account_type,
			b.created_at AS registration_date,
			b.pii_data
			FROM suspensions AS a
			JOIN users       AS b
			ON    a.guid           = b.guid
			WHERE a.reinstated     = 0
			AND   b.admin_approved = 1
			AND   b.verified       = 1
			AND (
				a.decision IS NULL OR
				a.decision = ''
			)
		");

		$users = $users ?? array();

		_exit(
			'success',
			$users
		);
	}
}
new AdminGetRevokedUsers();
