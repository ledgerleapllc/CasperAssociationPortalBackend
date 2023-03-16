<?php
/**
 *
 * POST /user/delete-comment
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param int $comment_id
 *
 */
class UserDeleteComment extends Endpoints {
	function __construct(
		$comment_id = 0
	) {
		global $db, $helper;

		require_method('POST');

		$auth       = authenticate_session(1);
		$user_guid  = $auth['guid'] ?? '';
		$comment_id = (int)(parent::$params['comment_id'] ?? 0);

		$check = $db->do_select("
			SELECT guid, discussion_id
			FROM discussion_comments
			WHERE id = $comment_id
			AND guid = '$user_guid'
		");

		if (!$check) {
			_exit(
				'error',
				'You are not authorized to do that',
				403,
				'You are not authorized to do that'
			);
		}

		// locked discussion check
		$discussion_id = (int)($check[0]['discussion_id'] ?? 0);
		$locked = $db->do_select("
			SELECT locked
			FROM discussions
			WHERE id = $discussion_id
		");
		$locked = (int)($locked[0]['locked'] ?? 0);

		if ($locked > 1) {
			_exit(
				'error',
				'Cannot modify comments on a discussion that is locked and archived',
				403,
				'Cannot modify comments on a discussion that is locked and archived'
			);
		}

		$result = $db->do_query("
			DELETE FROM discussion_comments
			WHERE id = $comment_id
		");

		_exit(
			'success',
			'Your comment has been deleted'
		);
	}
}
new UserDeleteComment();