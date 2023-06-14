<?php
/**
 *
 * GET /user/get-draft-discussion
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param int    $draft_id
 *
 */
class UserGetDraftDiscussion extends Endpoints {
	function __construct(
		$draft_id = 0
	) {
		global $db, $helper, $pagelock;

		require_method('GET');

		$auth      = authenticate_session(1);
		$user_guid = $auth['guid'] ?? '';
		$draft_id  = (int)(parent::$params['draft_id'] ?? 0);

		// 403 if page is locked from user access due to KYC or probation
		$pagelock->check($user_guid, 'discs');

		$discussion = $db->do_select("
			SELECT *
			FROM discussion_drafts
			WHERE guid = '$user_guid'
			AND     id = $draft_id
		");
		$discussion = $discussion[0] ?? null;

		if (!$discussion) {
			_exit(
				'error',
				'Could not find draft',
				400,
				'Could not find draft'
			);
		}

		_exit(
			'success',
			$discussion
		);
	}
}
new UserGetDraftDiscussion();
