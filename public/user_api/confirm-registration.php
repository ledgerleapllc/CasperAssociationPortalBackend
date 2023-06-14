<?php
/**
 *
 * POST /user/confirm-registration
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param string $confirmation_code
 *
 */
class UserConfirmRegistration extends Endpoints {
	function __construct(
		$confirmation_code = ''
	) {
		global $db, $helper;

		require_method('POST');

		$auth = authenticate_session();
		$guid = $auth['guid'] ?? '';
		$confirmation_code = parent::$params['confirmation_code'] ?? '';

		$query = "
			SELECT verified, confirmation_code
			FROM users
			WHERE guid = '$guid'
		";

		$selection                 = $db->do_select($query);
		$already_verified          = $selection[0]['verified'] ?? null;
		$fetched_confirmation_code = $selection[0]['confirmation_code'] ?? null;

		if($already_verified === 1) {
			_exit(
				'success',
				'Already confirmed registration'
			);
		}

		if(
			$confirmation_code &&
			$confirmation_code == $fetched_confirmation_code
		) {
			$db->do_query("
				UPDATE users
				SET verified = 1
				WHERE guid   = '$guid'
			");

			/* send confirmation welcome email, per global settings */
			$enabled = (bool)$helper->fetch_setting('enabled_welcome');

			if ($enabled) {
				$user_email = $db->do_select("
					SELECT email
					FROM users
					WHERE guid = '$guid'
				");

				$user_email = $user_email[0]['email'] ?? '';
				$subject    = 'Welcome to the Casper Association Portal';
				$body       = $helper->fetch_setting('email_welcome');

				if($user_email) {
					$helper->schedule_email(
						'user-alert',
						$user_email,
						$subject,
						$body
					);
				}
			}

			_exit(
				'success',
				'Successfully confirmed registration'
			);
		}

		_exit(
			'error',
			'Failed to register user',
			400,
			'Failed to register user'
		);
	}
}
new UserConfirmRegistration();
