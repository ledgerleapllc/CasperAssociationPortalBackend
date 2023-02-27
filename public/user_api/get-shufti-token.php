<?php
/**
 *
 * GET /user/get-shufti-token
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class UserShuftiToken extends Endpoints {
	function __construct() {
		require_method('GET');
		authenticate_session();

		$token = base64_encode(
			getenv('SHUFTI_CLIENT_ID').
			':'.
			getenv('SHUFTI_CLIENT_SECRET')
		);

		_exit(
			'success',
			$token
		);
	}
}
new UserShuftiToken();
