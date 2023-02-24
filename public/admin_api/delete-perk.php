<?php
/**
 *
 * POST /admin/delete-perk
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param int $perk_id
 *
 */
class AdminDeletePerk extends Endpoints {
	function __construct(
		$perk_id = 0
	) {
		global $db, $helper;

		require_method('POST');

		$auth       = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';

		$perk_id    = (int)(parent::$params['perk_id'] ?? 0);

		$check      = $db->do_select("
			SELECT id
			FROM  perks
			WHERE id = $perk_id
		");
		if (!$check) {
			_exit(
				'error',
				'Perk does not exist',
				400,
				'Perk does not exist'
			);
		}

		$db->do_query("
			DELETE FROM perks
			WHERE id = $perk_id
		");

		_exit(
			'success',
			'Perk deleted'
		);
	}
}
new AdminDeletePerk();