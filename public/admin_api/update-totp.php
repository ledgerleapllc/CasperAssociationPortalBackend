<?php
include_once('../../core.php');
/**
 *
 * PUT /admin/update-totp
 *
 * HEADER Authorization: Bearer
 *
 * Activate/Deactivate TOTP mfa
 *
 * @param  bool   $active
 * @return array  $provisioning_uri  Returns provisioning_uri when TOTP is turned on for the first time
 *
 */
class AdminUpdateTotp extends Endpoints {
	function __construct(
		$active = true
	) {
		global $db, $helper;

		require_method('PUT');

		$auth       = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';
		$active     = isset(parent::$params['active']) ? (bool)parent::$params['active'] : null;

		if($active === true) {
			$query = "
				SELECT hash
				FROM totp
				WHERE guid = '$admin_guid'
				AND active = 1
				ORDER BY created_at DESC
				LIMIT 1
			";
			$check = $db->do_select($query);

			if (!$check) {
				$provisioning_uri = Totp::create_totp_key($admin_guid);

				if($provisioning_uri) {
					_exit(
						'success',
						array(
							'instructions'     => 'Encoding your provisioning uri into a QR code. Please save your key. You will not be able to view it again later',
							'provisioning_uri' => $provisioning_uri
						)
					);
				}

				_exit(
					'error',
					'Failed to update authenticator settings',
					500,
					'Failed to update authenticator settings'
				);
			}

			$hash = $check[0]['hash'] ?? '';

			_exit(
				'success',
				array(
					'message' => 'You have an authenticator app connected. Please verify it using your 6 digit code',
					'hash'    => $hash
				)
			);
		}

		if($active === false) {
			$query = "
				UPDATE users
				SET totp   = 0
				WHERE guid = '$admin_guid'
			";
			$db->do_query($query);

			_exit(
				'success',
				'Successfully switched to email MFA'
			);
		}

		_exit(
			'error',
			'Failed to update authenticator settings',
			400,
			'Failed to update authenticator settings'
		);
	}
}
new AdminUpdateTotp();