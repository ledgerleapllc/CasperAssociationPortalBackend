<?php
include_once('../../core.php');
/**
 *
 * POST /admin/confirm-totp
 *
 * HEADER Authorization: Bearer
 *
 * Confirm TOTP mfa
 *
 * @api
 * @param string  $totp_code
 *
 */
class AdminConfirmTotp extends Endpoints {
	function __construct(
		$totp_code = ''
	) {
		global $db, $helper;

		require_method('POST');

		$auth       = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';
		$totp_code  = parent::$params['totp_code'] ?? null;

		if(!$totp_code) {
			_exit(
				'error',
				'Failed to provide authenticator code',
				400,
				'Failed to provide authenticator code'
			);
		}

		$valid = Totp::check_code($admin_guid, $totp_code);

		if($valid) {
			/* finally turn on totp mfa */
			$query = "
				UPDATE users
				SET totp   = 1
				WHERE guid = '$admin_guid'
			";
			$db->do_query($query);

			_exit(
				'success',
				'Successfully switched to authenticator MFA'
			);
		}

		_exit(
			'error',
			'Incorrect authenticator code',
			403,
			'Incorrect authenticator code'
		);
	}
}
new AdminConfirmTotp();