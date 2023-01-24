<?php
include_once('../../core.php');
/**
 *
 * POST /user/cancel-general-assembly
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param int $id
 *
 */
class UserCancelGeneralAssembly extends Endpoints {
	function __construct(
		$id = 0
	) {
		global $db, $helper;

		require_method('POST');

		$auth       = authenticate_session(1);
		$user_guid  = $auth['guid'] ?? '';
		$id         = (int)(parent::$params['id'] ?? 0);

		$check = $db->do_select("
			SELECT *
			FROM general_assemblies
			WHERE creator = '$user_guid'
			AND id        = $id
		");

		if (!$check) {
			_exit(
				'error',
				'You are not authorized to do that',
				403,
				'You are not authorized to do that'
			);
		}

		$finished = (int)($check[0]['finished'] ?? 0);

		if ($finished > 0) {
			_exit(
				'error',
				'Cannot cancel an assembly that is already finished',
				400,
				'Cannot cancel an assembly that is already finished'
			);
		}

		$db->do_query("
			DELETE FROM general_assemblies
			WHERE id = $id
		");

		_exit(
			'success',
			'Cancelled General Assembly'
		);
	}
}
new UserCancelGeneralAssembly();