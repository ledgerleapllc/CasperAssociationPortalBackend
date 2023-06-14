<?php
/**
 *
 * PUT /admin/censor-comment
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param int $comment_id
 *
 */
class AdminCensorComment extends Endpoints {
	function __construct(
		$comment_id = 0
	) {
		global $db, $helper;

		require_method('PUT');

		$auth       = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';
		$comment_id = (int)(parent::$params['comment_id'] ?? 0);

		$check = $db->do_select("
			SELECT guid
			FROM  discussion_comments
			WHERE id = $comment_id
		");

		if (!$check) {
			_exit(
				'error',
				'You are not authorized to do that',
				403
			);
		}

		$result = $db->do_query("
			UPDATE discussion_comments
			SET   flagged = 1
			WHERE id      = $comment_id
		");

		_exit(
			'success',
			'Comment has been censored'
		);
	}
}
new AdminCensorComment();
