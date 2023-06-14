<?php
/**
 *
 * GET /user/get-notifications
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class UserGetNotifications extends Endpoints {
	function __construct() {
		global $db, $helper;

		require_method('GET');

		$auth      = authenticate_session(1);
		$user_guid = $auth['guid'] ?? '';
		$now       = $helper->get_datetime();

		$notifications = $db->do_select("
			SELECT
			a.notification_id,
			a.dismissed_at,
			b.title,
			b.message,
			b.type,
			b.dismissable,
			b.priority,
			b.created_at,
			b.activate_at,
			b.deactivate_at,
			b.cta
			FROM user_notifications AS a
			JOIN notifications AS b
			ON a.notification_id = b.id
			WHERE a.guid = '$user_guid'
			AND (
				activate_at IS NULL OR
				activate_at < '$now'
			)
			ORDER BY b.created_at DESC
		");

		_exit(
			'success',
			$notifications
		);
	}
}
new UserGetNotifications();
