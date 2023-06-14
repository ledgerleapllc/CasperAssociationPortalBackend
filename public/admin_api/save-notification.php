<?php
/**
 *
 * POST /admin/save-notification
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param array $notification  Contains id, title, message, type, dismissable, priority, visible, activate_at, deactivate_at, and cta
 * @param array $broadcast     Contains guid, and notification_id (user is tagged)
 *
 */
class AdminSaveNotification extends Endpoints {
	function __construct(
		$notification = array(),
		$broadcast    = array()
	) {
		global $db, $helper;

		require_method('POST');

		$auth       = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';

		$notification = parent::$params['notification'] ?? array();
		$broadcast    = parent::$params['broadcast'] ?? array();

		$notification_id = (int)($notification['id'] ?? 0);
		$title           = $notification['title'] ?? '';
		$message         = $notification['message'] ?? '';
		$type            = $notification['type'] ?? '';
		$dismissable     = (bool)($notification['dismissable'] ?? '');
		$priority        = (int)($notification['priority'] ?? 1);
		$visible         = (bool)($notification['visible'] ?? '');
		$activate_at     = $notification['activate_at'] ?? '';
		$deactivate_at   = $notification['deactivate_at'] ?? '';
		$cta             = $notification['cta'] ?? '';

		if (strlen($title) > 128) {
			_exit(
				'error',
				'Title too long. Limit to 128 characters',
				400,
				'Notification title too long. Limit to 128 characters'
			);
		}

		if (strlen($message) > 2048) {
			_exit(
				'error',
				'Message too long. Limit to 2048 characters',
				400,
				'Notification message too long. Limit to 2048 characters'
			);
		}

		if (
			$type != 'info' &&
			$type != 'warning' &&
			$type != 'error' &&
			$type != 'question'
		) {
			_exit(
				'error',
				'Invalid notification type. Must be info, warning, error, or question',
				400,
				'Invalid notification type. Must be info, warning, error, or question'
			);
		}

		if (!$activate_at) {
			$activate_at = null;
		}

		if (!$deactivate_at) {
			$deactivate_at = null;
		}

		$helper->sanitize_input(
			$activate_at,
			false,
			Regex::$date_extended['char_limit'],
			Regex::$date_extended['char_limit'],
			Regex::$date_extended['pattern'],
			'Activation date'
		);

		$helper->sanitize_input(
			$deactivate_at,
			false,
			Regex::$date_extended['char_limit'],
			Regex::$date_extended['char_limit'],
			Regex::$date_extended['pattern'],
			'Deactivation date'
		);

		if ($activate_at && $deactivate_at) {
			if ($activate_at > $deactivate_at) {
				_exit(
					'error',
					'Notification end time cannot be before the start time of the notification',
					400,
					'Notification end time cannot be before the start time of the notification'
				);
			}
		}

		if (strlen($cta) > 256) {
			_exit(
				'error',
				'Call to action URL too long. Limit to 256 characters',
				400,
				'Notification call to action URL too long. Limit to 256 characters'
			);
		}

		$dismissable = (int)$dismissable;
		$visible     = (int)$visible;
		$created_at  = $helper->get_datetime();

		if ($notification_id == 0) {
			// insert
			$query = "
				INSERT INTO notifications (
					title,
					message,
					type,
					dismissable,
					priority,
					visible,
					created_at,
					cta,
					activate_at,
					deactivate_at
				) VALUES (
					'$title',
					'$message',
					'$type',
					$dismissable,
					$priority,
					$visible,
					'$created_at',
					'$cta',
			";

			if ($activate_at) {
				$query .= " '$activate_at',";
			} else {
				$query .= " NULL,";
			}

			if ($deactivate_at) {
				$query .= " '$deactivate_at')";
			} else {
				$query .= " NULL)";
			}

		} else {
			// update
			$query = "
				UPDATE notifications
				SET
				title         = '$title',
				message       = '$message',
				type          = '$type',
				dismissable   = $dismissable,
				priority      = $priority,
				visible       = $visible,
				cta           = '$cta',
			";

			if ($activate_at) {
				$query .= " activate_at = '$activate_at',";
			} else {
				$query .= " activate_at = NULL,";
			}

			if ($deactivate_at) {
				$query .= " deactivate_at = '$deactivate_at' ";
			} else {
				$query .= " deactivate_at = NULL ";
			}

			$query .= "
				WHERE id = $notification_id
			";
		}

		$db->do_query($query);

		// get new notification_id if just creating
		if ($notification_id == 0) {
			$notification_id = $db->do_select("
				SELECT id
				FROM notifications
				ORDER BY created_at DESC
				LIMIT 1
			");
			$notification_id = $notification_id[0]['id'] ?? 0;
		}

		// broadcast notification to users
		foreach ($broadcast as $user) {
			// elog($user);
			$guid   = $user['guid'] ?? '';
			$tagged = (bool)($user['notification_id'] ?? 0);

			// check if user is already registered to the notification broadcast
			$check = $db->do_select("
				SELECT guid
				FROM user_notifications
				WHERE guid = '$guid'
				AND notification_id = $notification_id
			");

			// if no record exists and the user is tagged, then attach
			if (!$check && $tagged) {
				$db->do_query("
					INSERT INTO user_notifications (
						notification_id,
						guid,
						created_at
					) VALUES (
						$notification_id,
						'$guid',
						'$created_at'
					)
				");
			}

			// if record exists and user is not tagged, then remove
			if ($check && !$tagged) {
				$db->do_query("
					DELETE FROM user_notifications
					WHERE guid = '$guid'
					AND notification_id = $notification_id
				");
			}
		}

		_exit(
			'success',
			'Saved notification'
		);
	}
}
new AdminSaveNotification();
