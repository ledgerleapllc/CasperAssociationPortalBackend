<?php
/**
 *
 * POST /admin/delete-contact-recipient
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class AdminDeleteContactRecipient extends Endpoints {
	function __construct() {
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
			DELETE FROM contact_recipients
			WHERE email = '$email'
		");

		_exit(
			'success',
			'Removed contact recipient'
		);
	}
}
new AdminDeleteContactRecipient();