<?php
include_once('../../core.php');
/**
 *
 * GET /admin/get-draft-discussion
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param int    $draft_id
 *
 */
class AdminGetDraftDiscussion extends Endpoints {
	function __construct(
		$draft_id = 0
	) {
		global $db, $helper, $pagelock;

		require_method('GET');

		$auth       = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';
		$draft_id   = (int)(parent::$params['draft_id'] ?? 0);

		$discussion = $db->do_select("
			SELECT *
			FROM discussion_drafts
			WHERE guid = '$admin_guid'
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
new AdminGetDraftDiscussion();