<?php
include_once('../../core.php');
/**
 *
 * $hash is the only required parameter if user by email already exists.
 *
 * POST /admin/accept-team-invite
 *
 * @api
 * @param string $hash       Hash from email link
 * @param string $password
 * @param string $pseudonym
 * @param string $telegram
 * @param string $first_name
 * @param string $last_name
 *
 */
class AdminAcceptTeamInvite extends Endpoints {
	function __construct(
		$hash       = '',
		$password   = '',
		$pseudonym  = '',
		$telegram   = '',
		$first_name = '',
		$last_name  = ''
	) {
		global $db, $helper, $authentication;

		require_method('POST');

		$hash       = parent::$params['hash'] ?? '';
		$password   = parent::$params['password'] ?? '';
		$pseudonym  = parent::$params['pseudonym'] ?? '';
		$telegram   = parent::$params['telegram'] ?? '';
		$first_name = parent::$params['first_name'] ?? '';
		$last_name  = parent::$params['last_name'] ?? '';

		// check for preexisting user to upgrade
		$uri               = $helper->aes_decrypt($hash);
		$uri               = explode('::', $uri);
		$guid              = $uri[0];
		$email             = $uri[1] ?? '';
		$confirmation_code = $uri[2] ?? '';
		$time              = (int)($uri[3] ?? 0);

		$user = $db->do_select("
			SELECT *
			FROM users 
			WHERE email = '$email'
		");

		if (!$user) {
			$helper->sanitize_input(
				$first_name,
				true,
				2,
				Regex::$human_name['char_limit'],
				Regex::$human_name['pattern'],
				'First Name'
			);

			$helper->sanitize_input(
				$last_name,
				true,
				2,
				Regex::$human_name['char_limit'],
				Regex::$human_name['pattern'],
				'Last Name'
			);

			$helper->sanitize_input(
				$pseudonym,
				true,
				3,
				Regex::$pseudonym['char_limit'],
				Regex::$pseudonym['pattern'],
				'Pseudonym'
			);

			if (!$telegram) {
				$telegram = null;
			}

			$helper->sanitize_input(
				$telegram,
				false,
				4,
				Regex::$telegram['char_limit'],
				Regex::$telegram['pattern'],
				'Telegram handle'
			);

			if(
				!$password ||
				strlen($password) < 8 ||
				!preg_match('/[\'\/~`\!@#\$%\^&\*\(\)_\-\+=\{\}\[\]\|;:"\<\>,\.\?\\\]/', $password) ||
				!preg_match('/[0-9]/', $password)
			) {
				_exit(
					'error',
					'Invalid new password. Must be at least 8 characters long, contain at least one (1) special character, and one (1) number',
					400,
					'Invalid new password. Failed complexity requirements'
				);
			}
		}

		$check = $db->do_select("
			SELECT *
			FROM team_invites
			WHERE guid = '$guid'
			AND confirmation_code = '$confirmation_code'
			AND email = '$email'
			AND accepted_at IS NULL
		");

		if (!$check) {
			_exit(
				'error',
				'Invalid/expired invitation',
				400,
				'Invalid team invite hash'
			);
		}

		// check pseudonym
		$pseudonym = $db->do_select("
			SELECT guid
			FROM users
			WHERE pseudonym = '$pseudonym'
		");

		if ($pseudonym) {
			_exit(
				'error',
				'Pseudonym already taken',
				400,
				'Pseudonym already taken'
			);
		}

		$accepted_at       = $helper->get_datetime();
		$password_hash     = hash('sha256', $password);
		$registration_ip   = $helper->get_real_ip();

		$pii_data = Structs::user_info;
		$pii_data["first_name"]       = $first_name;
		$pii_data["last_name"]        = $last_name;
		$pii_data["registration_ip"]  = $registration_ip;
		$pii_data_enc = $helper->encrypt_pii($pii_data);

		$db->do_query("
			UPDATE team_invites
			SET accepted_at = '$accepted_at'
			WHERE email = '$email'
			AND guid = '$guid'
		");

		if ($user) {
			$db->do_query("
				UPDATE users
				SET role = 'sub-admin'
				WHERE email = '$email'
			");
		} else {
			$db->do_query("
				INSERT INTO users (
					guid,
					role,
					email,
					pseudonym,
					telegram,
					account_type,
					pii_data,
					password,
					created_at,
					confirmation_code,
					verified,
					admin_approved
				) VALUES (
					'$guid',
					'sub-admin',
					'$email',
					'$pseudonym',
					'$telegram',
					'individual',
					'$pii_data_enc',
					'$password_hash',
					'$accepted_at',
					'$confirmation_code',
					1,
					1
				)
			");
		}

		/* create session */
		$bearer = $authentication->issue_session($guid);

		// log login
		$user_agent = filter($_SERVER['HTTP_USER_AGENT'] ?? '');

		$helper->log_login(
			$guid,
			$email,
			1,
			'First login',
			$registration_ip,
			$user_agent,
			''
		);

		/* get new user */
		$me = $helper->get_user($guid);

		if ($me) {
			$recipient = $email;
			$subject   = 'Casper Association Adminstrative Invitation';
			$body      = 'Welcome to the '.APP_NAME.'. You have been accepted as an adminstrative team member.';

			$helper->schedule_email(
				'admin-alert',
				$recipient,
				$subject,
				$body,
				''
			);
			_exit('success', $bearer);
		}

		_exit(
			'success',
			'Team member created!'
		);
	}
}
new AdminAcceptTeamInvite();