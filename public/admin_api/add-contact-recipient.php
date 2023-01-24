<?php
include_once('../../core.php');
/**
 *
 * POST /admin/add-contact-recipient
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param string $email
 *
 */
class AdminAddContactRecipient extends Endpoints {
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
			FROM contact_recipients
			WHERE email = '$email'
		");

		if ($check) {
			_exit(
				'error',
				'Recipient is already added to this list',
				400,
				'Recipient is already added to this list'
			);
		}

		$guid = $db->do_select("
			SELECT guid
			FROM users
			WHERE email = '$email'
		");

		$guid = $guid[0]['guid'] ?? '';

		$db->do_query("
			INSERT INTO contact_recipients (
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
			'Added contact recipient'
		);
	}
}
new AdminAddContactRecipient();