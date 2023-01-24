<?php
include_once('../../core.php');
/**
 *
 * GET /user/get-iplog
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class UserGetIplog extends Endpoints {
	function __construct() {
		global $db, $helper;

		require_method('GET');

		$auth = authenticate_session(1);
		$user_guid = $auth['guid'] ?? '';

		$query = "
			SELECT
			a.logged_in_at, a.email, a.successful, a.detail, 
			a.ip, a.user_agent, a.source,
			b.role
			FROM login_attempts AS a
			LEFT JOIN users AS b
			ON a.guid    = b.guid
			WHERE a.guid = '$user_guid'
		";

		$iplog = $db->do_select($query);
		!$iplog ? $iplog = array() : $iplog;

		$query = "
			SELECT email
			FROM users
			WHERE guid = '$user_guid'
		";
		$email = $db->do_select($query);
		$email = $email[0]['email'] ?? '';

		_exit(
			'success',
			array(
				"email" => $email,
				"iplog" => $iplog
			)
		);
	}
}
new UserGetIplog();