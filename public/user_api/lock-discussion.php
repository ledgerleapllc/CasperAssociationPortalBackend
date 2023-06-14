<?php
/**
 *
 * POST /user/lock-discussion
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param int    $discussion_id
 *
 */
class UserLockDiscussion extends Endpoints {
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
		$updated_at    = $helper->get_datetime();

		$check = $db->do_select("
			SELECT id
			FROM discussions
			WHERE id = $discussion_id
			AND guid = '$user_guid'
		");

		if (!$check) {
			_exit(
				'error',
				'You are not authorized to make this change',
				403
			);
		}

		$db->do_query("
			UPDATE discussions
			SET locked = 1, updated_at = '$updated_at'
			WHERE id = $discussion_id
		");

		_exit(
			'success',
			'Successfully locked this discussion. The discussion is now archived and cannot be deleted'
		);
	}
}
new UserLockDiscussion();
