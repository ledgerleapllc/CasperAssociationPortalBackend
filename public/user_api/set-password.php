<?php
/**
 *
 * POST /user/set-password
 *
 * @api
 * @param string $hash          Hash from email link
 * @param string $new_password
 *
 */
class UserSetPassword extends Endpoints {
	function __construct(
		$hash         = '',
		$new_password = ''
	) {
		global $db, $helper, $authentication;

		require_method('POST');

		$hash              = parent::$params['hash'] ?? '';
		$new_password      = parent::$params['new_password'] ?? '';
		$new_password_hash = hash('sha256', $new_password);

		if (
			!$new_password ||
			strlen($new_password) < 8 ||
			!preg_match('/[\'\/~`\!@#\$%\^&\*\(\)_\-\+=\{\}\[\]\|;:"\<\>,\.\?\\\]/', $new_password) ||
			!preg_match('/[0-9]/', $new_password)
		) {
			_exit(
				'error',
				'Invalid new password. Must be at least 8 characters long, contain at least one (1) special character, and one (1) number',
				400,
				'Invalid new password. Failed complexity requirements'
			);
		}

		$uri               = $helper->aes_decrypt($hash);
		$uri               = explode('::', $uri);
		$guid              = $uri[0];
		$confirmation_code = $uri[1] ?? '';
		$time              = (int)($uri[2] ?? 0);
		$auth_code         = $uri[3] ?? null;

		// check reset auth code
		if ($auth_code != 'set-new-password') {
			_exit(
				'error',
				'Password reset code invalid. Please try again',
				403,
				'Password reset code invalid'
			);
		}

		/* final confirmation check */
		$check = $db->do_select("
			SELECT 
			guid, 
			email, 
			password, 
			twofa, 
			totp, 
			role,
			confirmation_code

			FROM  users
			WHERE guid              = '$guid'
			AND   confirmation_code = '$confirmation_code'
			AND   password          = '--reset--'
		");

		if (!$check) {
			_exit(
				'error',
				'Error setting password. Not authorized',
				403,
				'Error setting password. Not authorized'
			);
		}

		$email = $check[0]['email'] ?? '';

		// clear sessions
		$authentication->clear_session($guid);

		$query = "
			UPDATE users
			SET   password = '$new_password_hash'
			WHERE guid     = '$guid'
		";

		$success = $db->do_query($query);

		if ($success) {
			// auto login the user
			$bearer     = $authentication->issue_session($guid);
			$user_agent = filter($_SERVER['HTTP_USER_AGENT'] ?? '');
			$ip         = $helper->get_real_ip();

			// write cookie
			$cookie = $helper->add_authorized_device(
				$guid,
				$ip,
				$user_agent,
				''
			);

			// log login
			$helper->log_login(
				$guid,
				$email,
				1,
				'Successful activation',
				$ip,
				$user_agent,
				$cookie
			);

			_exit(
				'success',
				array(
					'bearer' => $bearer,
					'cookie' => $cookie,
					'user'   => $check[0]
				)
			);

			_exit(
				'success',
				'Successfully set your password',
				200
			);
		} else {
			_exit(
				'error',
				'Error setting password',
				400,
				'Error setting password'
			);
		}
	}
}
new UserSetPassword();