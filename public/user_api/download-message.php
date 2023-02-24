<?php
/**
 *
 * GET /user/download-message
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class UserDownloadMessage extends Endpoints {
	function __construct() {
		global $db, $helper;

		require_method('GET');

		$auth      = authenticate_session(1);
		$user_guid = $auth['guid'] ?? '';
		$timestamp = $helper->get_datetime();
		$message   = 'Please use the Casper Signature python tool to sign this message! '.$timestamp;

		$db->do_query("
			UPDATE users 
			SET sig_message = '$message'
			WHERE guid = '$user_guid'
		");

		_exit(
			'success',
			$message
		);
	}
}
new UserDownloadMessage();