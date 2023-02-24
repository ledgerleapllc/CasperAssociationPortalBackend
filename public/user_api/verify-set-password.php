<?php
/**
 *
 * POST /user/verify-set-password
 *
 * @api
 * @param string $hash  Hash from email link
 *
 */
class UserVerifySetPassword extends Endpoints {
	function __construct(
		$hash = ''
	) {
		global $db, $helper, $authentication;

		require_method('POST');

		$hash = parent::$params['hash'] ?? '';

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
				'Password reset code invalid',
				403,
				'Password reset code invalid'
			);
		}

		$check = $db->do_select("
			SELECT 
			guid, 
			email, 
			confirmation_code
			FROM  users
			WHERE guid              = '$guid'
			AND   confirmation_code = '$confirmation_code'
			AND   password          = '--reset--'
		");

		if (!$check) {
			_exit(
				'error',
				'Password reset code invalid',
				403,
				'Password reset code invalid'
			);
		}

		_exit(
			'success',
			'Valid reset hash',
			200
		);
	}
}
new UserVerifySetPassword();