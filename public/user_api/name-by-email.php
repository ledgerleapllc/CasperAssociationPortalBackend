<?php
/**
 *
 * GET /user/name-by-email
 *
 * @api
 * @param string $email
 *
 */
class UserNameByEmail extends Endpoints {
	function __construct(
		$email = ''
	) {
		global $db, $helper;

		require_method('GET');

		$email = parent::$params['email'] ?? null;

		if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			_exit(
				'error',
				'Invalid email address',
				400,
				'Invalid email address'
			);
		}

		if(strlen($email) > 255) {
			_exit(
				'error',
				'Invalid email address',
				400,
				'Invalid email address'
			);
		}

		$query = "
			SELECT guid
			FROM users
			WHERE email = '$email'
		";

		$selection = $db->do_select($query);
		$selection = $selection[0]['guid'] ?? null;

		if($selection) {
			_exit(
				'success',
				$selection
			);
		}

		_exit(
			'error',
			'User not found',
			404,
			'User not found'
		);
	}
}
new UserNameByEmail();