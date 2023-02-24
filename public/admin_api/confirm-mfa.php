<?php
/**
 *
 * POST /admin/confirm-mfa
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param string  $mfa_code
 *
 */
class AdminConfirmMfa extends Endpoints {
	function __construct(
		$mfa_code = ''
	) {
		global $helper;

		require_method('POST');

		$auth       = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';
		$mfa_code   = (string)(parent::$params['mfa_code'] ?? '');
		$response   = $helper->verify_mfa($admin_guid, $mfa_code);

		if($response == 'incorrect') {
			_exit(
				'error',
				'Incorrect MFA code',
				403,
				'Incorrect MFA code'
			);
		} elseif($response == 'expired') {
			_exit(
				'error',
				'MFA code is expired',
				403,
				'MFA code is expired'
			);
		} elseif($response == 'success') {
			_exit(
				'success',
				'MFA code accepted. Expires in 5 minutes'
			);
		}

		_exit(
			'error',
			'Failed to confirm MFA code',
			400,
			'Failed to confirm MFA code'
		);
	}
}
new AdminConfirmMfa();