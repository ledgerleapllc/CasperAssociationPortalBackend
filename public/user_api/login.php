<?php
/**
 *
 * POST /user/login
 *
 * @api
 * @param string $email
 * @param string $password
 *
 */
class UserLogin extends Endpoints {
	function __construct(
		$email    = '', 
		$password = ''
	) {
		global $db, $helper, $authentication;

		require_method('POST');

		$email    = parent::$params['email'] ?? null;
		$password = parent::$params['password'] ?? null;
		$cookie   = parent::$params['cookie'] ?? null;

		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			_exit(
				'error',
				'Invalid email address',
				400,
				'Invalid email address'
			);
		}

		if (!$password) {
			_exit(
				'error',
				'Please provide a password',
				400,
				'No password provided'
			);
		}

		if (!$cookie) {
			$cookie = null;
		}

		$helper->sanitize_input(
			$cookie,
			false,
			Regex::$cookie['char_limit'],
			Regex::$cookie['char_limit'],
			Regex::$cookie['pattern'],
			'Cookie'
		);

		$query = "
			SELECT 
			guid, 
			email, 
			password, 
			twofa, 
			totp, 
			role,
			confirmation_code
			FROM users
			WHERE email = '$email'
		";

		$result         = $db->do_select($query);
		$result         = $result[0] ?? null;
		$guid           = $result['guid'] ?? '';
		$twofa          = (int)($result['twofa'] ?? 0);
		$totp           = (int)($result['totp'] ?? 0);
		$role           = $result['role'] ?? '';
		$password_hash2 = $result['password'] ?? '';
		$password_hash  = hash('sha256', $password);
		$created_at     = $helper->get_datetime();
		$ip             = $helper->get_real_ip();
		$user_agent     = filter($_SERVER['HTTP_USER_AGENT'] ?? '');

		// set new password from v1 migration
		if ($password_hash2 == '--reset--') {
			$confirmation_code = $result['confirmation_code'] ?? '';

			$hash = $helper->aes_encrypt(
				$guid.'::'.
				$confirmation_code.'::'.
				(string)time().'::'.
				'set-new-password'
			);

			$link = PROTOCOL.'://'.FRONTEND_URL.'/set-password?token='.$hash;

			$helper->schedule_email(
				'user-alert',
				$email,
				'Set your new password',
				'Thank you for bearing with us. Our new platform requires members to reset their passwords upon first login to the new system. <br><br>Follow the link below to set your new password and activate your account',
				$link
			);

			_exit(
				'success',
				array(
					'redirect' => 'set-password',
					'message'  => 'Multi-factor authentication requested'
				)
			);
		}
		
		if (!hash_equals($password_hash2, $password_hash)) {
			// log failed login
			if($guid) {
				$failed_reason = 'Incorrect password';
			} else {
				$failed_reason = 'Email does not exist';
			}

			$helper->log_login(
				$guid,
				$email,
				0,
				$failed_reason,
				$ip,
				$user_agent
			);

			_exit(
				'error',
				'Invalid email or password',
				403,
				'Invalid email or password'
			);
		}

		/* check MFA */
		if($twofa == 1) {
			// totp mfa type, both auths required to be at most 5 minutes apart
			$totp_expires_at = $helper->get_datetime(300); // 5 minutes

			if($totp == 1) {
				$query = "
					INSERT INTO totp_logins (
						guid,
						expires_at
					) VALUES (
						'$guid',
						'$totp_expires_at'
					)
				";
				$db->do_query($query);

				// log login
				$helper->log_login(
					$guid,
					$email,
					0,
					'Require TOTP authentication',
					$ip,
					$user_agent
				);

				_exit(
					'success',
					array(
						'twofa'   => true,
						'totp'    => true,
						'guid'    => $guid,
						'message' => 'Multi-factor authentication requested'
					)
				);
			}

			// email mfa type
			$code = $helper->generate_hash(6);
			
			$helper->schedule_email(
				'twofa',
				$email,
				'Multi Factor Authentication',
				'Please find your MFA code below to login to '.APP_NAME.'. This code expires in 5 minutes.',
				$code
			);

			$query = "
				DELETE FROM twofa
				WHERE guid = '$guid'
			";
			$db->do_query($query);

			$query = "
				INSERT INTO twofa (
					guid,
					created_at,
					code
				) VALUES (
					'$guid',
					'$created_at',
					'$code'
				)
			";
			$db->do_query($query);

			// log login
			$helper->log_login(
				$guid,
				$email,
				0,
				'Require email 2FA',
				$ip,
				$user_agent
			);

			_exit(
				'success',
				array(
					'twofa'   => true,
					'totp'    => false,
					'guid'    => $guid,
					'message' => 'Multi-factor authentication requested'
				)
			);
		}

		// No MFA enabled, so check account security params.
		$secure = $helper->check_authorized_devices(
			$guid,
			$ip,
			$user_agent,
			$cookie
		);

		if($secure) {
			$bearer = $authentication->issue_session($guid);

			// log login
			$helper->log_login(
				$guid,
				$email,
				1,
				'Successful login',
				$ip,
				$user_agent,
				$cookie
			);

			// overwrite cookie if new cookie
			$cookie = $helper->add_authorized_device(
				$guid,
				$ip,
				$user_agent,
				$cookie
			);

			_exit(
				'success',
				array(
					'bearer' => $bearer,
					'cookie' => $cookie,
					'user'   => $result
				)
			);
		}

		// No MFA enabled, and browser/cookie/IP not recognized. Do 2fa check
		else {
			$code = $helper->generate_hash(6);
			
			$helper->schedule_email(
				'twofa',
				$email,
				'Verify Login Attempt Was You',
				'The device you are logging in from was not recognized. Please find your MFA code below to login to '.APP_NAME.'. This code expires in 5 minutes.',
				$code
			);

			$query = "
				DELETE FROM twofa
				WHERE guid = '$guid'
			";
			$db->do_query($query);

			$query = "
				INSERT INTO twofa (
					guid,
					created_at,
					code
				) VALUES (
					'$guid',
					'$created_at',
					'$code'
				)
			";
			$db->do_query($query);

			// log login
			$helper->log_login(
				$guid,
				$email,
				0,
				'Device not recognized. Request 2FA',
				$ip,
				$user_agent
			);

			_exit(
				'success',
				array(
					'twofa'   => false,
					'totp'    => false,
					'guid'    => $guid,
					'message' => 'Multi-factor authentication requested'
				)
			);
		}
	}
}
new UserLogin();