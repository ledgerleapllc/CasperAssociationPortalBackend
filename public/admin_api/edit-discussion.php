<?php
/**
 *
 * PUT /admin/edit-discussion
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param int    $discussion_id
 * @param string $description
 *
 */
class AdminEditDiscussion extends Endpoints {
	function __construct(
		$discussion_id = 0,
		$description   = ''
	) {
		global $db, $helper;

		require_method('PUT');

		$auth          = authenticate_session(2);
		$admin_guid    = $auth['guid'] ?? '';
		$discussion_id = (int)(parent::$params['discussion_id'] ?? 0);
		$description   = parent::$params['description'] ?? '';
		$updated_at    = $helper->get_datetime();

		if (strlen($description) > 64000) {
			_exit(
				'error',
				'Discussion body text limited to 64000 characters',
				400
			);
		}

		$check = $db->do_select("
			SELECT locked
			FROM discussions
			WHERE id = $discussion_id
		");

		if(!$check) {
			_exit(
				'error',
				'Discussion does not exist',
				403
			);
		}

		$check = (bool)($check[0]['locked'] ?? 0);

		if ($check > 1) {
			_exit(
				'error',
				'Cannot modify a locked discussion',
				403
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
			'Discussion has been modified'
		);
	}
}
new AdminEditDiscussion();