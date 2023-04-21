<?php
/**
 *
 * POST /admin/delete-comment
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param int $comment_id
 *
 */
class AdminDeleteComment extends Endpoints {
	function __construct(
		$comment_id = 0
	) {
		global $db, $helper;

		require_method('POST');

		$auth       = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';
		$comment_id = (int)(parent::$params['comment_id'] ?? 0);

		// No restriction on admins deleted comments anymore
		/*
		$check = $db->do_select("
			SELECT guid
			FROM discussion_comments
			WHERE id = $comment_id
			AND guid = '$admin_guid'
		");

		if (!$check) {
			_exit(
				'error',
				'You are not authorized to do that',
				403
			);
		}

		$result = $db->do_query("
			DELETE FROM discussion_comments
			WHERE id = $comment_id
		");
		*/

		$db->do_query("
			UPDATE discussion_comments
			SET    deleted = 1
			WHERE  id      = $comment_id
		");

		_exit(
			'success',
			'Your comment has been deleted'
		);
	}
}
new AdminDeleteComment();