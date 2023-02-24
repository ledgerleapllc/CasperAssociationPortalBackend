<?php
/**
 *
 * GET /user/get-account-validators
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class UserGetAccountValidators extends Endpoints {
	function __construct() {
		global $db, $helper;

		require_method('GET');

		$auth = authenticate_session(1);
		$user_guid = $auth['guid'] ?? '';

		$validators = $db->do_select("
			SELECT
			public_key AS validator_id,
			created_at,
			updated_at,
			verified AS verified_at
			FROM user_nodes
			WHERE guid = '$user_guid'
		");

		$validators = $validators ?? array();

		_exit(
			'success',
			$validators
		);
	}
}
new UserGetAccountValidators();