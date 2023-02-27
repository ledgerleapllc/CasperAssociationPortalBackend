<?php
/**
 *
 * POST /admin/pin-discussion
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param int  $discussion_id
 * @param bool $pin
 *
 */
class AdminPinDiscussion extends Endpoints {
	function __construct(
		$discussion_id = 0,
		$pin           = true
	) {
		global $db, $helper;

		require_method('POST');

		$auth          = authenticate_session(2);
		$admin_guid    = $auth['guid'] ?? '';
		$discussion_id = (int)(parent::$params['discussion_id'] ?? 0);
		$pin           = (bool)(parent::$params['pin'] ?? false);
		$now           = $helper->get_datetime();

		$check = $db->do_select("
			SELECT id
			FROM discussions
			WHERE id = $discussion_id
		");

		if (!$check) {
			_exit(
				'error',
				'Discussion does not exist',
				404
			);
		}

		if ($pin) {
			$check = $db->do_select("
				SELECT guid
				FROM discussion_pins
				WHERE guid = '$admin_guid'
				AND discussion_id = $discussion_id
			");

			if ($check) {
				_exit(
					'success',
					'Discussion is already pinned by you'
				);
			}

			$db->do_query("
				INSERT INTO discussion_pins (
					guid,
					discussion_id,
					created_at,
					updated_at
				) VALUES (
					'$admin_guid',
					$discussion_id,
					'$now',
					'$now'
				)
			");

			_exit(
				'success',
				'Discussion pinned'
			);
		} else {
			$result = $db->do_query("
				DELETE FROM discussion_pins
				WHERE guid = '$admin_guid'
				AND discussion_id = $discussion_id
			");

			_exit(
				'success',
				'Discussion unpinned'
			);
		}
	}
}
new AdminPinDiscussion();
