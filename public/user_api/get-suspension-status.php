<?php
/**
 *
 * GET /user/get-suspension-status
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class UserGetSuspensionStatus extends Endpoints {
	function __construct() {
		global $suspensions;

		require_method('GET');

		$auth      = authenticate_session();
		$user_guid = $auth['guid'] ?? '';
		$stats     = $suspensions->user_reinstatement_stats($user_guid);

		_exit(
			'success',
			$stats
		);
	}
}
new UserGetSuspensionStatus();
