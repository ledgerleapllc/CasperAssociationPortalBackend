<?php
include_once('../../core.php');
/**
 *
 * GET /user/get-perks
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class UserGetPerks extends Endpoints {
	function __construct() {
		global $db, $helper, $pagelock;

		require_method('GET');

		$auth      = authenticate_session(1);
		$user_guid = $auth['guid'] ?? '';

		// 403 if page is locked from user access due to KYC or probation
		$pagelock->check($user_guid, 'perks');

		$perks     = $db->do_select("
			SELECT *
			FROM perks
			WHERE visible = 1
		");

		$perks = $perks ?? array();

		_exit(
			'success',
			$perks
		);
	}
}
new UserGetPerks();