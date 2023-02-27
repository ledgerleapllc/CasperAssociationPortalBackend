<?php
/**
 *
 * POST /admin/discard-draft
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param int    $draft_id
 *
 */
class AdminDiscardDraft extends Endpoints {
	function __construct(
		$draft_id = 0
	) {
		global $db, $helper;

		require_method('POST');

		$auth       = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';

		$draft_id   = (int)(parent::$params['draft_id'] ?? 0);

		// check
		$check = $db->do_select("
			SELECT guid
			FROM  discussion_drafts
			WHERE id = $draft_id
			AND guid = '$admin_guid'
		");

		if (!$check) {
			_exit(
				'error',
				'You are not authorized to do that',
				403,
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
new AdminDiscardDraft();
