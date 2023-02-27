<?php
/**
 *
 * GET /admin/get-pinned-discussions
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class AdminGetPinnedDiscussions extends Endpoints {
	function __construct() {
		global $db, $helper;

		require_method('GET');

		$auth        = authenticate_session(2);
		$admin_guid  = $auth['guid'] ?? '';
		$discussions = $db->do_select("
			SELECT
			a.id,
			a.title,
			a.description,
			a.is_read,
			a.locked,
			a.for_upgrade,
			a.created_at,
			a.updated_at,
			b.avatar_url,
			b.pseudonym,
			b.role,
			e.status AS kyc_status
			FROM discussions AS a
			LEFT JOIN users AS b
			ON a.guid = b.guid
			LEFT JOIN shufti AS e
			ON b.guid = e.guid
			JOIN discussion_pins AS c
			ON a.id = c.discussion_id
			WHERE c.guid = '$admin_guid'
			ORDER BY a.updated_at DESC
		");

		$discussions = $discussions ?? array();

		foreach ($discussions as &$discussion) {
			$discussion_id = $discussion['id'] ?? 0;

			$likes = $db->do_select("
				SELECT count(id) AS likes
				FROM discussion_likes
				WHERE discussion_id = $discussion_id
			");
			$likes = $likes[0]['likes'] ?? 0;

			$comments = $db->do_select("
				SELECT count(id) AS comments
				FROM discussion_comments
				WHERE discussion_id = $discussion_id
			");
			$comments = $comments[0]['comments'] ?? 0;

			$pins = $db->do_select("
				SELECT count(id) AS pins
				FROM discussion_pins
				WHERE discussion_id = $discussion_id
			");
			$pins = $pins[0]['pins'] ?? 0;

			$liked_by_me = (bool)$db->do_select("
				SELECT guid
				FROM discussion_likes
				WHERE guid = '$admin_guid'
				AND discussion_id = $discussion_id
			");

			$pinned_by_me = (bool)$db->do_select("
				SELECT guid
				FROM discussion_pins
				WHERE guid = '$admin_guid'
				AND discussion_id = $discussion_id
			");

			$discussion['likes']        = $likes;
			$discussion['comments']     = $comments;
			$discussion['pins']         = $pins;
			$discussion['liked_by_me']  = $liked_by_me;
			$discussion['pinned_by_me'] = $pinned_by_me;
		}

		_exit(
			'success',
			$discussions
		);
	}
}
new AdminGetPinnedDiscussions();
