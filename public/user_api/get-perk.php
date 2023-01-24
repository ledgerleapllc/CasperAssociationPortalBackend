<?php
include_once('../../core.php');
/**
 *
 * GET /user/get-perk
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param int $perk_id
 *
 */
class UserGetPerk extends Endpoints {
	function __construct(
		$perk_id = 0
	) {
		global $db, $helper, $pagelock;

		require_method('GET');

		$auth      = authenticate_session(1);
		$user_guid = $auth['guid'] ?? '';

		// 403 if page is locked from user access due to KYC or probation
		$pagelock->check($user_guid, 'perks');

		$perk_id   = (int)(parent::$params['perk_id'] ?? 0);

		$perk      = $db->do_select("
			SELECT *
			FROM perks
			WHERE id = $perk_id
		");

		$perk = $perk[0] ?? array();

		_exit(
			'success',
			$perk
		);
	}
}
new UserGetPerk();