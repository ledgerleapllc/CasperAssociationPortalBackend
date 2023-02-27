<?php
/**
 *
 * POST /user/post-discussion
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param int    $draft_id
 * @param string $title
 * @param string $description
 * @param int    $associated_ballot
 * @param bool   $for_upgrade
 *
 */
class UserPostDiscussion extends Endpoints {
	function __construct(
		$draft_id          = 0,
		$title             = '',
		$description       = '',
		$associated_ballot = 0,
		$for_upgrade       = false
	) {
		global $db, $helper, $pagelock;

		_exit(
			'error',
			'Post discussion endpoint is disabled for users at this time',
			400,
			'Post discussion endpoint is disabled for users at this time'
		);

		require_method('POST');

		$auth              = authenticate_session(1);
		$user_guid         = $auth['guid'] ?? '';

		// 403 if page is locked from user access due to KYC or probation
		$pagelock->check($user_guid, 'discs');

		$draft_id          = (int)(parent::$params['draft_id'] ?? 0);
		$title             = parent::$params['title'] ?? '';
		$description       = parent::$params['description'] ?? '';
		$associated_ballot = (int)(parent::$params['associated_ballot'] ?? 0);
		$for_upgrade       = (bool)(parent::$params['for_upgrade'] ?? false);
		$for_upgrade       = $for_upgrade ? 1 : 0;
		$created_at        = $helper->get_datetime();

		$helper->sanitize_input(
			$title,
			true,
			5,
			256,
			Regex::$title['pattern'],
			'Title'
		);

		if (strlen($description) > 64000) {
			_exit(
				'error',
				'Discussion body text limited to 64000 characters',
				400,
				'Discussion body text limited to 64000 characters'
			);
		}

		if ($associated_ballot) {
			$check = $db->do_select("
				SELECT id
				FROM ballots
				WHERE id = $associated_ballot
			");

			if (!$check) {
				_exit(
					'error',
					'Associated ballot attached to the creation of this discussion is invalid',
					400,
					'Associated ballot attached to the creation of this discussion is invalid'
				);
			}
		}

		$query = "
			INSERT INTO discussions (
				guid,
				title,
				description,
				associated_ballot,
				for_upgrade,
				created_at,
				updated_at
			) VALUES (
				'$user_guid',
				'$title',
				'$description',
				$associated_ballot,
				$for_upgrade,
				'$created_at',
				'$created_at'
			)
		";

		$db->do_query($query);

		// clear draft, if applicable
		if ($draft_id > 0) {
			$db->do_query("
				DELETE FROM discussion_drafts
				WHERE guid = '$user_guid'
				AND   id   = $draft_id
			");
		}

		_exit(
			'success',
			'Your discussion has been posted'
		);
	}
}
new UserPostDiscussion();
