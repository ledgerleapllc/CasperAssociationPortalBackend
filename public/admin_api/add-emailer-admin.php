<?php
/**
 *
 * POST /admin/add-emailer-admin
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param string $email
 *
 */
class AdminAddEmailerAdmin extends Endpoints {
	function __construct(
		$email = ''
	) {
		global $db, $helper;

		require_method('POST');

		$auth       = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';
		$email      = parent::$params['email'] ?? '';
		$now        = $helper->get_datetime();

		if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			_exit(
				'error',
				'Invalid email address',
				400,
				'Invalid email address'
			);
		}

		$check = $db->do_select("
			SELECT *
			FROM emailer_admins
			WHERE email = '$email'
		");

		if ($check) {
			_exit(
				'error',
				'Emailer admin is already added to this list',
				400,
				'Emailer admin is already added to this list'
			);
		}

		$guid = $db->do_select("
			SELECT guid
			FROM users
			WHERE email = '$email'
		");

		$guid = $guid[0]['guid'] ?? '';

		$db->do_query("
			INSERT INTO emailer_admins (
				guid,
				email,
				created_at
			) VALUES (
				'$guid',
				'$email',
				'$now'
			)
		");

		_exit(
			'success',
			'Added emailer admin'
		);
	}
}
new AdminAddEmailerAdmin();
