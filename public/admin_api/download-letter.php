<?php
/**
 *
 * GET /admin/download-letter
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param string $guid
 *
 */
class AdminDownloadLetter extends Endpoints {
	function __construct(
		$guid = ''
	) {
		global $db, $helper;

		require_method('GET');

		$auth = authenticate_session(2);
		$guid = parent::$params['guid'] ?? '';

		$selection = $db->do_select("
			SELECT letter
			FROM users
			WHERE guid = '$guid'
		");

		$return = array(
			"letter" => $selection[0]['letter'] ?? '',
			"guid"   => $guid
		);

		_exit(
			'success',
			$return
		);
	}
}
new AdminDownloadLetter();
