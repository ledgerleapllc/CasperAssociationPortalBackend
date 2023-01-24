<?php
include_once('../../core.php');
/**
 *
 * GET /admin/get-iplog
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param string  $guid  Optional
 *
 */
class AdminGetIplog extends Endpoints {
	function __construct(
		$guid = ''
	) {
		global $db, $helper;

		require_method('GET');

		$auth       = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';
		$guid       = (string)(parent::$params['guid'] ?? '');

		$query = "
			SELECT
			a.logged_in_at, a.email, a.successful, a.detail, 
			a.ip, a.user_agent, a.source,
			b.role
			FROM login_attempts AS a
			LEFT JOIN users AS b
			ON a.guid = b.guid
		";

		if ($guid) {
			$query .= " WHERE a.guid = '$guid'";
		}

		$iplog = $db->do_select($query);
		!$iplog ? $iplog = array() : $iplog;

		$query = "
			SELECT email
			FROM users
			WHERE guid = '$guid'
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
new AdminGetIplog();