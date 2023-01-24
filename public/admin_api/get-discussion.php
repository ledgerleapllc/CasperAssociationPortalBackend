<?php
include_once('../../core.php');
/**
 *
 * GET /admin/get-discussion
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param int    $discussion_id
 *
 */
class AdminGetDiscussion extends Endpoints {
	function __construct(
		$discussion_id = 0
	) {
		global $db, $helper;

		require_method('GET');

		$auth          = authenticate_session(2);
		$discussion_id = (int)(parent::$params['discussion_id'] ?? 0);
		$discussion    = $db->do_select("
			SELECT 
			a.id, 
			a.guid AS creator_guid,
			a.title, 
			a.description, 
			a.is_read,
			a.locked,
			a.created_at, 
			a.updated_at,
			a.associated_ballot,
			a.for_upgrade,
			b.avatar_url,
			b.pseudonym,
			b.role,
			c.id AS pinned_by_me,
			d.id AS liked_by_me,
			e.title AS ballot_title,
			e.status AS ballot_status,
			f.status AS kyc_status
			FROM discussions AS a
			LEFT JOIN users AS b
			ON a.guid = b.guid
			LEFT JOIN discussion_pins AS c
			ON a.guid = c.guid AND a.id = c.discussion_id
			LEFT JOIN discussion_likes AS d
			ON a.guid = d.guid AND a.id = d.discussion_id
			LEFT JOIN ballots AS e
			ON a.associated_ballot = e.id
			LEFT JOIN shufti AS f
			ON b.guid = f.guid
			WHERE a.id = $discussion_id
			ORDER BY updated_at DESC
		");

		$discussion    = $discussion[0] ?? array();
		$discussion_id = $discussion['id'] ?? 0;

		if (empty($discussion)) {
			_exit(
				'error',
				'Discussion does not exist',
				404
			);
		}

		$likes = $db->do_select("
			SELECT count(id) AS likes
			FROM discussion_likes
			WHERE discussion_id = $discussion_id
		");
		$likes = $likes[0]['likes'] ?? 0;

		$comments = $db->do_select("
			SELECT
			a.id,
			a.guid, 
			a.content,
			a.flagged,
			a.created_at,
			a.updated_at,
			b.pseudonym,
			b.avatar_url,
			b.role,
			c.status AS kyc_status
			FROM discussion_comments AS a
			LEFT JOIN users AS b
			ON a.guid = b.guid
			LEFT JOIN shufti AS c
			ON a.guid = c.guid
			WHERE a.discussion_id = $discussion_id
			ORDER BY a.created_at DESC
		");
		$comments = $comments ?? array();

		$pins = $db->do_select("
			SELECT count(id) AS pins
			FROM discussion_pins
			WHERE discussion_id = $discussion_id
		");
		$pins = $pins[0]['pins'] ?? 0;

		$discussion['likes']    = $likes;
		$discussion['comments'] = $comments;
		$discussion['pins']     = $pins;

		_exit(
			'success',
			$discussion
		);
	}
}
new AdminGetDiscussion();