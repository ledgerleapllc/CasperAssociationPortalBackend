<?php
/**
 *
 * GET /user/get-general-assembly-proposed-times
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param int $id
 *
 */
class UserGetGeneralAssemblyProposedTimes extends Endpoints {
	function __construct(
		$id = 0
	) {
		global $db, $helper;

		require_method('GET');

		$auth      = authenticate_session(1);
		$user_guid = $auth['guid'] ?? '';
		$id        = (int)(parent::$params['id'] ?? 0);

		$times = $db->do_select("
			SELECT *
			FROM assembly_times
			WHERE assembly_id = $id
		");

		$times = $times ?? array();

		_exit(
			'success',
			$times
		);
	}
}
new UserGetGeneralAssemblyProposedTimes();