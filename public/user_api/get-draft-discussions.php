<?php
/**
 *
 * GET /user/get-draft-discussions
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class UserGetDraftDiscussions extends Endpoints {
	function __construct() {
		global $db, $helper, $pagelock;

		require_method('GET');

		$auth      = authenticate_session(1);
		$user_guid = $auth['guid'] ?? '';

		// 403 if page is locked from user access due to KYC or probation
		$pagelock->check($user_guid, 'discs');

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
			WHERE a.guid = '$user_guid'
			ORDER BY a.updated_at DESC
		");

		_exit(
			'success',
			$discussions
		);
	}
}
new UserGetDraftDiscussions();