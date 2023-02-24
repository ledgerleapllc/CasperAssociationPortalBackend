<?php
/**
 *
 * POST /admin/forgot-password
 *
 * @api
 * @param string $email
 *
 */
class AdminForgotPassword extends Endpoints {
	function __construct(
		$email = ''
	) {
		global $db, $helper;

		require_method('POST');

		$email = parent::$params['email'] ?? '';

		if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			_exit(
				'error',
				'Invalid email address',
				400,
				'Invalid email address'
			);
		}

		$query = "
			SELECT guid, email, confirmation_code
			FROM users
			WHERE email = '$email'
		";
		$selection       = $db->do_select($query);
		$selection       = $selection[0] ?? null;
		$guid            = $selection['guid'] ?? null;
		$email           = $selection['email'] ?? null;
		$reset_auth_code = $helper->generate_hash();

		if(
			$selection &&
			$guid &&
			$email
		) {
			// record auth code so we can de-auth after single use
			$query = "
				INSERT INTO password_resets (
					guid,
					code
				) VALUES (
					'$guid',
					'$reset_auth_code'
				)
			";
			$db->do_query($query);

			$confirmation_code = $selection['confirmation_code'] ?? '';
			$uri = $helper->aes_encrypt($guid.'::'.$confirmation_code.'::'.(string)time().'::'.$reset_auth_code);

			$subject = APP_NAME.' - Forgot Password';
			$body = 'You are receiving this email because we received a password reset request for your account. Please follow the link below to reset your password. This password reset link will expire in 10 minutes.';
			$link = 'https://'.getenv('FRONTEND_URL').'/reset-password/'.$uri.'?email='.$email;

			$helper->schedule_email(
				'reset-password',
				$email,
				$subject,
				$body,
				$link
			);
		}

		_exit(
			'success',
			'Please check your email for a reset link'
		);
	}
}
new AdminForgotPassword();