<?php
/**
 *
 * POST /user/validate-shufti-signature
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param string $signature
 * @param string $response
 *
 */
class UserValidateShuftiSignature extends Endpoints {
	function __construct(
		$signature = '',
		$response  = ''
	) {
		global $db, $helper;

		require_method('POST');

		$auth      = authenticate_session(1);
		$user_guid = $auth['guid'] ?? '';
		$signature = parent::$params['signature'] ?? '';
		$response  = parent::$params['response'] ?? '';

		$response  = html_entity_decode($response);
		$response  = str_replace('\\', '', $response);
		$response  = str_replace('/', '\\/', $response);

		$hash = hash('sha256', $response.getenv('SHUFTI_CLIENT_SECRET'));
		// elog('RESPONSE HASH: '.$hash);
		// elog('RESPONSE SIG:  '.$signature);

		if (
			hash_equals(
				strtolower($hash), 
				strtolower($signature)
			)
		) {
			_exit(
				'success',
				'Shufti signature is valid'
			);
		}

		_exit(
			'error',
			'Shufti signature is not valid',
			400,
			'Shufti signature is not valid'
		);
	}
}
new UserValidateShuftiSignature();