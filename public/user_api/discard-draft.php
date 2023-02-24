<?php
/**
 *
 * POST /user/discard-draft
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param int    $draft_id
 *
 */
class UserDiscardDraft extends Endpoints {
	function __construct(
		$draft_id = 0
	) {
		global $db, $helper, $pagelock;

		require_method('POST');

		$auth      = authenticate_session(1);
		$user_guid = $auth['guid'] ?? '';

		// 403 if page is locked from user access due to KYC or probation
		$pagelock->check($user_guid, 'discs');

		$draft_id  = (int)(parent::$params['draft_id'] ?? 0);

		// check
		$check = $db->do_select("
			SELECT guid
			FROM  discussion_drafts
			WHERE id = $draft_id
			AND guid = '$user_guid'
		");

		if (!$check) {
			_exit(
				'error',
				'You are not authorized to do that',
				400,
				'You are not authorized to do that'
			);
		}

		$db->do_query("
			DELETE FROM discussion_drafts
			WHERE id = $draft_id
		");

		_exit(
			'success',
			'Discussion draft discarded'
		);
	}
}
new UserDiscardDraft();