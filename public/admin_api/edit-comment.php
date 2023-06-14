<?php
/**
 *
 * PUT /admin/edit-comment
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param int    $comment_id
 * @param string $content
 *
 */
class AdminEditComment extends Endpoints {
	function __construct(
		$comment_id = 0,
		$content    = ''
	) {
		global $db, $helper;

		require_method('PUT');

		$auth       = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';
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
			SELECT id, guid
			FROM discussion_comments
			WHERE id = $comment_id
		");

		if (!$check) {
			_exit(
				'error',
				'The comment you are trying to edit does not exist',
				404
			);
		}

		$check_guid = $check[0]['guid'] ?? '';

		if ($check_guid != $admin_guid) {
			_exit(
				'error',
				'You are not authorized to do that',
				403
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
new AdminEditComment();
