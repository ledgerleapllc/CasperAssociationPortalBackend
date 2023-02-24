<?php
/**
 *
 * POST /admin/lock-discussion
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param int    $discussion_id
 *
 */
class AdminLockDiscussion extends Endpoints {
	function __construct(
		$discussion_id = 0
	) {
		global $db, $helper;

		require_method('POST');

		$auth          = authenticate_session(2);
		$admin_guid    = $auth['guid'] ?? '';
		$discussion_id = (int)(parent::$params['discussion_id'] ?? 0);
		$updated_at    = $helper->get_datetime();

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

		$db->do_query("
			UPDATE discussions
			SET locked = 2, updated_at = '$updated_at'
			WHERE id = $discussion_id
		");

		_exit(
			'success',
			'Successfully locked this discussion. The discussion is now archived and cannot be deleted'
		);
	}
}
new AdminLockDiscussion();