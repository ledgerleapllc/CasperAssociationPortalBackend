<?php
include_once('../../core.php');
/**
 *
 * GET /user/get-general-assemblies
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class UserGetGeneralAssemblies extends Endpoints {
	function __construct() {
		global $db, $helper;

		require_method('GET');

		$auth = authenticate_session(1);
		$user_guid = $auth['guid'] ?? '';

		$assemblies = $db->do_select("
			SELECT *
			FROM general_assemblies
			ORDER BY created_at DESC
		");

		$assemblies = $assemblies ?? array();

		_exit(
			'success',
			$assemblies
		);
	}
}
new UserGetGeneralAssemblies();