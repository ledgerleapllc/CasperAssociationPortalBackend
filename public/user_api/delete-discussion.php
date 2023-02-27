<?php
/**
 *
 * POST /user/delete-discussion
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param int $discussion_id
 *
 */
class UserDeleteDiscussion extends Endpoints {
	function __construct(
		$discussion_id = 0
	) {
		global $db, $helper, $pagelock;

		require_method('POST');

		$auth          = authenticate_session(1);
		$user_guid     = $auth['guid'] ?? '';

		// 403 if page is locked from user access due to KYC or probation
		$pagelock->check($user_guid, 'discs');

		$discussion_id = (int)(parent::$params['discussion_id'] ?? 0);

		$check = $db->do_select("
			SELECT id, locked
			FROM discussions
			WHERE id = $discussion_id
			AND guid = '$user_guid'
		");

		$locked = (bool)($check[0]['locked'] ?? false);

		if (!$check) {
			_exit(
				'error',
				'You are not authorized to make this change',
				403,
				'You are not authorized to make this change'
			);
		}

		if ($locked) {
			_exit(
				'error',
				'This discussion is locked and cannot be deleted',
				403,
				'This discussion is locked and cannot be deleted'
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
new UserDeleteDiscussion();
