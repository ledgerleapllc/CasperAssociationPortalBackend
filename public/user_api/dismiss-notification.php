<?php
include_once('../../core.php');
/**
 *
 * POST /user/dismiss-notification
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param int $notification_id
 *
 */
class UserDismissNotification extends Endpoints {
	function __construct(
		$notification_id = 0
	) {
		global $db, $helper;

		require_method('POST');

		$auth            = authenticate_session(1);
		$user_guid       = $auth['guid'] ?? '';
		$notification_id = (int)(parent::$params['notification_id'] ?? 0);
		$now             = $helper->get_datetime();

		// check
		$check = $db->do_select("
			SELECT dismissable
			FROM notifications
			WHERE id = $notification_id
			AND dismissable = 1
		");

		if (!$check) {
			_exit(
				'error',
				'Cannot dismiss this notification',
				400,
				'Cannot dismiss this notification'
			);
		}

		$db->do_query("
			UPDATE user_notifications
			SET dismissed_at = '$now'
			WHERE guid = '$user_guid'
			AND notification_id = $notification_id
		");

		_exit(
			'success',
			'Dismissed notification'
		);
	}
}
new UserDismissNotification();