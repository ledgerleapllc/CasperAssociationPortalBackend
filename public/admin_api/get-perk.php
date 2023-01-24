<?php
include_once('../../core.php');
/**
 *
 * GET /admin/get-perk
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param int $perk_id
 *
 */
class AdminGetPerk extends Endpoints {
	function __construct(
		$perk_id = 0
	) {
		global $db, $helper, $pagelock;

		require_method('GET');

		$auth       = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';

		// 403 if page is locked from user access due to KYC or probation
		$pagelock->check($admin_guid, 'perks');

		$perk_id = (int)(parent::$params['perk_id'] ?? 0);

		$perk    = $db->do_select("
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
new AdminGetPerk();