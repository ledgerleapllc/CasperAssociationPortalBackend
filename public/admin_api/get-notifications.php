<?php
/**
 *
 * GET /admin/get-notifications
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class AdminGetNotifications extends Endpoints {
	function __construct() {
		global $db, $helper;

		require_method('GET');

		$auth       = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';

		$notifications = $db->do_select("
			SELECT *
			FROM notifications
		");

		$notifications = $notifications ?? array();

		foreach ($notifications as &$notification) {
			$nid   = (int)($notification['id'] ?? 0);
			$users = $db->do_select("
				SELECT guid
				FROM user_notifications
				WHERE notification_id = $nid
			");

			$users = $users ?? array();
			$notification['broadcast'] = $users;
		}

		_exit(
			'success',
			$notifications
		);
	}
}
new AdminGetNotifications();
