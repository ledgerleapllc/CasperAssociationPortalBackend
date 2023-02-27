<?php
/**
 *
 * POST /admin/save-draft-discussion
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
class AdminSaveDraftDiscussion extends Endpoints {
	function __construct(
		$draft_id          = 0,
		$title             = '',
		$description       = '',
		$associated_ballot = 0,
		$for_upgrade       = false
	) {
		global $db, $helper, $pagelock;

		require_method('POST');

		$auth              = authenticate_session(2);
		$admin_guid        = $auth['guid'] ?? '';

		$draft_id          = (int)(parent::$params['draft_id'] ?? 0);
		$title             = parent::$params['title'] ?? null;
		$description       = parent::$params['description'] ?? '';
		$associated_ballot = (int)(parent::$params['associated_ballot'] ?? 0);
		$for_upgrade       = (bool)(parent::$params['for_upgrade'] ?? false);
		$for_upgrade       = $for_upgrade ? 1 : 0;
		$created_at        = $helper->get_datetime();

		if (!$title) {
			$title = null;
		}

		$helper->sanitize_input(
			$title,
			false,
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

		if ($draft_id > 0) {
			// update with guid check
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

			$query = "
				UPDATE discussion_drafts
				SET
				title             = '$title',
				description       = '$description',
				associated_ballot = $associated_ballot,
				for_upgrade       = $for_upgrade,
				updated_at        = '$created_at'
				WHERE id          = $draft_id
			";
		} else {
			// create new with limit check
			$check = $db->do_select("
				SELECT count(guid) as dCount
				FROM discussion_drafts
				WHERE guid = '$admin_guid'
			");
			$dCount = (int)($check[0]['dCount'] ?? 0);

			if ($dCount > 10) {
				_exit(
					'error',
					'You can only have 10 discussions drafts saved at once',
					400,
					'You can only have 10 discussions drafts saved at once'
				);
			}

			$query = "
				INSERT INTO discussion_drafts (
					guid,
					title,
					description,
					associated_ballot,
					for_upgrade,
					created_at,
					updated_at
				) VALUES (
					'$admin_guid',
					'$title',
					'$description',
					$associated_ballot,
					$for_upgrade,
					'$created_at',
					'$created_at'
				)
			";
		}

		$db->do_query($query);

		// get new ID
		$draft_id = $db->do_select("
			SELECT id
			FROM discussion_drafts
			WHERE guid = '$admin_guid'
			ORDER BY updated_at DESC
			LIMIT 1
		");
		$draft_id = (int)($draft_id[0]['id'] ?? 0);

		_exit(
			'success',
			array(
				'message'  => 'Discussion draft saved',
				'draft_id' => $draft_id
			)
		);
	}
}
new AdminSaveDraftDiscussion();
