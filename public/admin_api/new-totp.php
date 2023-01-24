<?php
include_once('../../core.php');
/**
 *
 * POST /admin/new-totp
 *
 * HEADER Authorization: Bearer
 *
 * Generate new TOTP secret
 *
 * @api
 *
 */
class AdminNewTotp extends Endpoints {
	function __construct() {
		global $db, $helper;

		require_method('POST');

		$auth = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';

		$provisioning_uri = Totp::create_totp_key($admin_guid);

		if($provisioning_uri) {
			_exit(
				'success',
				array(
					'instructions' => 'Encoding your provisioning uri into a QR code. Please save your key. You will not be able to view it again later',
					'provisioning_uri' => $provisioning_uri
				)
			);
		}

		_exit(
			'error',
			'Failed to create new authenticator secret',
			500,
			'Failed to create new authenticator secret'
		);
	}
}
new AdminNewTotp();