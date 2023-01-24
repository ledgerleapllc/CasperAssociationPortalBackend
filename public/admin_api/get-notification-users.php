<?php
include_once('../../core.php');
/**
 *
 * GET /admin/get-notification-users
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param int $notification_id
 *
 */
class AdminGetNotificationUsers extends Endpoints {
	function __construct(
		$notification_id = 0
	) {
		global $db, $helper;

		require_method('GET');

		$auth            = authenticate_session(2);
		$admin_guid      = $auth['guid'] ?? '';
		$notification_id = (int)(parent::$params['notification_id'] ?? 0);

		$users = $db->do_select("
			SELECT
			a.guid,
			a.email,
			a.role,
			b.notification_id
			FROM users AS a
			LEFT JOIN user_notifications AS b
			ON  a.guid            = b.guid
			AND b.notification_id = $notification_id
		");

		$users = $users ?? array();

		_exit(
			'success',
			$users
		);
	}
}
new AdminGetNotificationUsers();