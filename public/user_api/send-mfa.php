<?php
/**
 *
 * POST /user/send-mfa
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class UserSendMfa extends Endpoints {
	function __construct() {
		global $db, $helper;

		require_method('POST');

		$auth = authenticate_session(1);
		$user_guid = $auth['guid'] ?? '';

		// check for mfa type, email/totp
		$query = "
			SELECT totp
			FROM users
			WHERE guid = '$user_guid'
		";
		$mfa_type = $db->do_select($query);
		$mfa_type = (int)($mfa_type[0]['totp'] ?? 0);

		if($mfa_type == 1) {
			_exit(
				'success',
				'Check your authenticator for an MFA code'
			);
		}

		$sent = $helper->send_mfa($user_guid);

		if($sent) {
			_exit(
				'success',
				'Check your email for an MFA code'
			);
		}

		_exit(
			'error',
			'Failed to send MFA code',
			500,
			'Failed to send MFA code'
		);
	}
}
new UserSendMfa();