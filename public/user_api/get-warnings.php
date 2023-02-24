<?php
/**
 *
 * GET /user/get-warnings
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class UserGetWarnings extends Endpoints {
	function __construct() {
		global $db;

		require_method('GET');

		$auth      = authenticate_session(1);
		$user_guid = $auth['guid'] ?? '';

		$warnings = $db->do_select("
			SELECT *
			FROM warnings
			WHERE guid = '$user_guid'
			AND dismissed_at IS NULL
		");

		_exit(
			'success',
			$warnings
		);
	}
}
new UserGetWarnings();