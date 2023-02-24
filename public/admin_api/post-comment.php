<?php
/**
 *
 * POST /admin/post-comment
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param int    $discussion_id
 * @param string $content
 *
 */
class AdminPostComment extends Endpoints {
	function __construct(
		$discussion_id = 0,
		$content       = ''
	) {
		global $db, $helper;

		require_method('POST');

		$auth          = authenticate_session(2);
		$admin_guid    = $auth['guid'] ?? '';
		$discussion_id = (int)(parent::$params['discussion_id'] ?? 0);
		$content       = parent::$params['content'] ?? '';
		$created_at    = $helper->get_datetime();

		$helper->sanitize_input(
			$content,
			true,
			1,
			2048,
			'[\s\S]',
			'Comment'
		);

		$check = $db->do_select("
			SELECT id
			FROM discussions
			WHERE id = $discussion_id
		");

		if (!$check) {
			_exit(
				'error',
				'The discussion to which you are trying to post a comment does not exist',
				404
			);
		}

		$result = $db->do_query("
			INSERT INTO discussion_comments (
				guid,
				discussion_id,
				content,
				created_at,
				updated_at
			) VALUES (
				'$admin_guid',
				$discussion_id,
				'$content',
				'$created_at',
				'$created_at'
			)
		");

		_exit(
			'success',
			'Your comment has been posted'
		);
	}
}
new AdminPostComment();