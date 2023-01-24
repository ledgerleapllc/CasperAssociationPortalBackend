<?php
include_once('../../core.php');
/**
 *
 * POST /user/login-mfa
 *
 * MFA for guest users. eg. Login
 *
 * @api
 * @param string $mfa_code
 * @param string $guid
 *
 */
class UserLoginMfa extends Endpoints {
	function __construct(
		$mfa_code = '',
		$guid = ''
	) {
		global $db, $helper, $authentication;

		require_method('POST');

		$mfa_code = (string)(parent::$params['mfa_code'] ?? '');
		$guid     = parent::$params['guid'] ?? null;
		$cookie   = parent::$params['cookie'] ?? null;

		if (!$cookie) {
			$cookie = null;
		}

		$helper->sanitize_input(
			$guid,
			true,
			Regex::$guid['char_limit'],
			Regex::$guid['char_limit'],
			Regex::$guid['pattern'],
			'GUID'
		);

		$helper->sanitize_input(
			$cookie,
			false,
			Regex::$cookie['char_limit'],
			Regex::$cookie['char_limit'],
			Regex::$cookie['pattern'],
			'Cookie'
		);

		$AUTHENTICATED = false;

		// get mfa type first
		$query = "
			SELECT totp
			FROM users
			WHERE guid = '$guid'
		";
		$totp_on = $db->do_select($query);
		$totp_on = (int)($totp_on[0]['totp'] ?? 0);

		// totp mfa type
		if($totp_on == 1) {
			$query = "
				SELECT *
				FROM totp_logins
				WHERE guid = '$guid'
			";
			$totp       = $db->do_select($query);
			$expires_at = $totp[0]['expires_at'] ?? 0;

			if($helper->get_datetime() > $expires_at) {
				$query = "
					DELETE FROM totp_logins
					WHERE guid = '$guid'
				";
				$db->do_query($query);

				_exit(
					'error',
					'Authenticator code expired. Please try logging back in',
					400,
					'Login Authenticator code expired.'
				);
			}

			$verified = Totp::check_code($guid, $mfa_code);

			if($verified) {
				$query = "
					DELETE FROM totp_logins
					WHERE guid = '$guid'
				";
				$db->do_query($query);

				$AUTHENTICATED = true;
			}
		}

		// email mfa type
		else {
			$query = "
				SELECT code, created_at
				FROM twofa
				WHERE guid = '$guid'
				AND code   = '$mfa_code'
			";
			$selection    = $db->do_select($query);
			$fetched_code = $selection[0]['code'] ?? '';
			$created_at   = $selection[0]['created_at'] ?? 0;
			$expire_time  = $helper->get_datetime(-300); // 5 minutes ago

			if($selection && $mfa_code == $fetched_code) {
				if($expire_time < $created_at) {
					$query = "
						DELETE FROM twofa
						WHERE guid = '$guid'
					";
					$db->do_query($query);

					$AUTHENTICATED = true;
				} else {
					$query = "
						DELETE FROM twofa
						WHERE guid = '$guid'
					";
					$db->do_query($query);

					_exit(
						'error',
						'MFA code expired. Please try logging back in',
						400,
						'Login MFA code expired'
					);
				}
			}
		}

		if($AUTHENTICATED) {
			/* issue session */
			$bearer = $authentication->issue_session($guid);

			/* get the rest of the user array */
			$user = $helper->get_user($guid);

			// record login
			$email      = $user['email'] ?? '';
			$role       = $user['role'] ?? '';
			$ip         = $helper->get_real_ip();
			$user_agent = filter($_SERVER['HTTP_USER_AGENT'] ?? '');

			// log login
			$helper->log_login(
				$guid,
				$email,
				1,
				'Successful MFA authentication',
				$ip,
				$user_agent
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
					'user'   => $user
				)
			);
		}

		_exit(
			'error',
			'MFA code incorrect. Please re-enter your code',
			400,
			'MFA code incorrect'
		);
	}
}
new UserLoginMfa();