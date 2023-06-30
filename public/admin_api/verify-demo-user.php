<?php
/**
 *
 * POST /admin/verify-demo-user
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param string $guid
 *
 */
class AdminVerifyDemoUser extends Endpoints {
	function __construct(
		$guid = ''
	) {
		global $db, $helper;

		_exit(
			'error',
			'verify-demo-user endpoint disabled',
			403,
			'verify-demo-user endpoint disabled'
		);

		require_method('POST');

		$auth        = authenticate_session(2);
		$admin_guid  = $auth['guid'] ?? '';
		$guid        = parent::$params['guid'] ?? '';
		$now         = $helper->get_datetime();

		$db->do_query("
			UPDATE user_nodes
			SET   verified = '$now'
			WHERE guid     = '$guid'
		");

		_exit(
			'success',
			'Demo user validator node is verified'
		);
	}
}
new AdminVerifyDemoUser();
