<?php
include_once('../../core.php');
/**
 *
 * POST /admin/delete-discussion
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param int $discussion_id
 *
 */
class AdminDeleteDiscussion extends Endpoints {
	function __construct(
		$discussion_id = 0
	) {
		global $db, $helper;

		require_method('POST');

		$auth          = authenticate_session(2);
		$admin_guid    = $auth['guid'] ?? '';
		$discussion_id = (int)(parent::$params['discussion_id'] ?? 0);

		$check = $db->do_select("
			SELECT id, locked
			FROM discussions
			WHERE id = $discussion_id
		");

		$locked = (bool)($check[0]['locked'] ?? false);

		if (!$check) {
			_exit(
				'error',
				'Discussion does not exist',
				404
			);
		}

		if ($locked > 1) {
			_exit(
				'error',
				'This discussion is locked and cannot be deleted',
				403
			);
		}

		$db->do_query("
			DELETE FROM discussions
			WHERE id = $discussion_id
		");

		$db->do_query("
			DELETE FROM discussion_pins
			WHERE discussion_id = $discussion_id
		");

		$db->do_query("
			DELETE FROM discussion_likes
			WHERE discussion_id = $discussion_id
		");

		$db->do_query("
			DELETE FROM discussion_comments
			WHERE discussion_id = $discussion_id
		");

		_exit(
			'success',
			'Successfully deleted this discussion'
		);
	}
}
new AdminDeleteDiscussion();