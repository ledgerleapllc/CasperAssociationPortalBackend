<?php
/**
 *
 * POST /user/like-discussion
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param int  $discussion_id
 * @param bool $like
 *
 */
class UserLikeDiscussion extends Endpoints {
	function __construct(
		$discussion_id = 0,
		$like          = true
	) {
		global $db, $helper;

		require_method('POST');

		$auth          = authenticate_session(1);
		$user_guid     = $auth['guid'] ?? '';
		$discussion_id = (int)(parent::$params['discussion_id'] ?? 0);
		$like          = (bool)(parent::$params['like'] ?? false);
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

		if ($like) {
			$check = $db->do_select("
				SELECT guid
				FROM discussion_likes
				WHERE guid = '$user_guid'
				AND discussion_id = $discussion_id
			");

			if ($check) {
				_exit(
					'success',
					'Discussion is already liked by you'
				);
			}

			$db->do_query("
				INSERT INTO discussion_likes (
					guid,
					discussion_id,
					created_at
				) VALUES (
					'$user_guid',
					$discussion_id,
					'$now'
				)
			");

			_exit(
				'success',
				'Discussion liked'
			);
		} else {
			$result = $db->do_query("
				DELETE FROM discussion_likes
				WHERE guid = '$user_guid'
				AND discussion_id = $discussion_id
			");

			_exit(
				'success',
				'Discussion unliked'
			);
		}
	}
}
new UserLikeDiscussion();
