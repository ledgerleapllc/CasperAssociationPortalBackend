<?php
/**
 *
 * POST /admin/delete-notifications
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param int $notification_id
 *
 */
class AdminDeleteNotifications extends Endpoints {
	function __construct(
		$notification_id = 0
	) {
		global $db, $helper;

		require_method('POST');

		$auth            = authenticate_session(2);
		$admin_guid      = $auth['guid'] ?? '';
		$notification_id = (int)(parent::$params['notification_id'] ?? 0);

		$db->do_query("
			DELETE FROM notifications
			WHERE id = $notification_id
		");

		_exit(
			'success',
			'Notification removed'
		);
	}
}
new AdminDeleteNotifications();
