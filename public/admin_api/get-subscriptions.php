<?php
/**
 *
 * GET /admin/get-subscriptions
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class AdminGetSubscriptions extends Endpoints {
	function __construct() {
		global $db, $helper;

		require_method('GET');
		$auth       = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';

		$query = "
			SELECT *
			FROM subscriptions
		";
		$selection = $db->do_select($query);

		if(!$selection) {
			$selection = array();
		}

		_exit(
			'success',
			$selection
		);
	}
}
new AdminGetSubscriptions();