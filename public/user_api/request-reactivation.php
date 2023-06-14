<?php
/**
 *
 * POST /user/request-reactivation
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param string $letter
 *
 */
class UserVote extends Endpoints {
	function __construct(
		$letter = ''
	) {
		global $db, $helper;

		require_method('POST');

		$auth       = authenticate_session(1);
		$user_guid  = $auth['guid'] ?? '';

		$letter  = parent::$params['letter'] ?? '';
		$now     = $helper->get_datetime();

		if (strlen($letter) > 4096) {
			_exit(
				'error',
				'Letter is too long. Please limit to 4096 characters',
				400,
				'Letter is too long. Please limit to 4096 characters'
			);
		}

		$check   = $db->do_select("
			SELECT reinstatable
			FROM  suspensions
			WHERE guid         = '$user_guid'
			AND   reinstated   = 0
			AND   reinstatable = 1
		");

		if (!$check) {
			_exit(
				'error',
				'You are not authorized to do that',
				400,
				'You are not authorized to do that'
			);
		}

		$sid = $db->do_select("
			SELECT id
			FROM suspensions
			WHERE guid = '$user_guid'
			ORDER BY created_at DESC
			LIMIT 1
		");

		$sid = (int)($sid[0]['id'] ?? 0);

		$result = $db->do_query("
			UPDATE suspensions
			SET
			letter     = '$letter',
			updated_at = '$now'
			WHERE id   = $sid
		");

		_exit(
			'success',
			'Reinstatement requested'
		);
	}
}
new UserVote();
