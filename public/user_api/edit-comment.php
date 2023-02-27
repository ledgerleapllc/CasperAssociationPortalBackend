<?php
/**
 *
 * PUT /user/edit-comment
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param int    $comment_id
 * @param string $content
 *
 */
class UserEditComment extends Endpoints {
	function __construct(
		$comment_id = 0,
		$content    = ''
	) {
		global $db, $helper;

		require_method('PUT');

		$auth       = authenticate_session(1);
		$user_guid  = $auth['guid'] ?? '';
		$comment_id = (int)(parent::$params['comment_id'] ?? 0);
		$content    = parent::$params['content'] ?? '';
		$updated_at = $helper->get_datetime();

		$helper->sanitize_input(
			$content,
			true,
			1,
			2048,
			'[\s\S]',
			'Comment'
		);

		$check = $db->do_select("
			SELECT id, guid, discussion_id
			FROM discussion_comments
			WHERE id = $comment_id
		");

		if (!$check) {
			_exit(
				'error',
				'The comment you are trying to edit does not exist',
				404,
				'The comment you are trying to edit does not exist'
			);
		}

		$check_guid = $check[0]['guid'] ?? '';

		if ($check_guid != $user_guid) {
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
				'Cannot modify comments on a discussion that is locked and archived',
			);
		}

		$db->do_query("
			UPDATE discussion_comments
			SET content = '$content', updated_at = '$updated_at'
			WHERE id = $comment_id
		");

		_exit(
			'success',
			'Your comment has been modified'
		);
	}
}
new UserEditComment();
