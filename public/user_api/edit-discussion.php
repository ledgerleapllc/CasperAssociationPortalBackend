<?php
/**
 *
 * PUT /user/edit-discussion
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param int    $discussion_id
 * @param string $description
 *
 */
class UserEditDiscussion extends Endpoints {
	function __construct(
		$discussion_id = 0,
		$description   = ''
	) {
		global $db, $helper, $pagelock;

		require_method('PUT');

		$auth          = authenticate_session(1);
		$user_guid     = $auth['guid'] ?? '';

		// 403 if page is locked from user access due to KYC or probation
		$pagelock->check($user_guid, 'discs');

		$discussion_id = (int)(parent::$params['discussion_id'] ?? 0);
		$description   = parent::$params['description'] ?? '';
		$updated_at    = $helper->get_datetime();

		if (strlen($description) > 64000) {
			_exit(
				'error',
				'Discussion body text limited to 64000 characters',
				400,
				'Discussion body text limited to 64000 characters'
			);
		}

		$check = $db->do_select("
			SELECT locked
			FROM discussions
			WHERE id = $discussion_id
			AND guid = '$user_guid'
		");

		if(!$check) {
			_exit(
				'error',
				'You are not authorized to do that',
				403,
				'You are not authorized to do that'
			);
		}

		$check = (bool)($check[0]['locked'] ?? 0);

		if ($check) {
			_exit(
				'error',
				'Cannot modify a locked discussion',
				403,
				'Cannot modify a locked discussion'
			);
		}

		$result = $db->do_query("
			UPDATE discussions
			SET
			description = '$description',
			updated_at  = '$updated_at'
			WHERE id    = $discussion_id
		");

		_exit(
			'success',
			'Your discussion has been modified'
		);
	}
}
new UserEditDiscussion();
