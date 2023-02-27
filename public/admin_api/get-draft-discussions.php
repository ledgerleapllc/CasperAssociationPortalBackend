<?php
/**
 *
 * GET /admin/get-draft-discussions
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class AdminGetDraftDiscussions extends Endpoints {
	function __construct() {
		global $db, $helper;

		require_method('GET');

		$auth        = authenticate_session(2);
		$admin_guid  = $auth['guid'] ?? '';

		// fetch drafts
		$discussions = $db->do_select("
			SELECT
			a.id,
			a.title,
			a.description,
			a.associated_ballot,
			a.for_upgrade,
			a.created_at,
			a.updated_at,
			b.avatar_url,
			b.pseudonym,
			b.role,
			e.status AS kyc_status
			FROM discussion_drafts AS a
			LEFT JOIN users AS b
			ON a.guid = b.guid
			LEFT JOIN shufti AS e
			ON a.guid = e.guid
			WHERE a.guid = '$admin_guid'
			ORDER BY a.updated_at DESC
		");

		_exit(
			'success',
			$discussions
		);
	}
}
new AdminGetDraftDiscussions();
