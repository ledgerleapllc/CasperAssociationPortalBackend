<?php
include_once('../../core.php');
/**
 *
 * POST /admin/delete-emailer-admin
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param sring $email
 *
 */
class AdminDeleteEmailerAdmin extends Endpoints {
	function __construct(
		$email = ''
	) {
		global $db, $helper;

		require_method('POST');

		$auth       = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';
		$email      = parent::$params['email'] ?? '';

		if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			_exit(
				'error',
				'Invalid email address',
				400,
				'Invalid email address'
			);
		}

		$db->do_query("
			DELETE FROM emailer_admins
			WHERE email = '$email'
		");

		_exit(
			'success',
			'Removed emailer admin'
		);
	}
}
new AdminDeleteEmailerAdmin();