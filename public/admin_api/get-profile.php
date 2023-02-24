<?php
/**
 *
 * GET /admin/get-profile
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param string $identifier  Can pass a valid pseudonym or guid
 *
 */
class AdminGetProfile extends Endpoints {
	function __construct(
		$identifier = ''
	) {
		global $db, $helper;

		require_method('GET');

		$auth       = authenticate_session(2);
		$user_guid  = $auth['guid'] ?? '';
		$identifier = parent::$params['identifier'] ?? '';

		$split = explode('-', $identifier);
		$s0    = $split[0] ?? '';
		$s1    = $split[1] ?? '';
		$s2    = $split[2] ?? '';
		$s3    = $split[3] ?? '';
		$s4    = $split[4] ?? '';

		if (
			preg_match(Regex::$guid['pattern'], $identifier) &&
			strlen($s0) == 8 &&
			strlen($s1) == 4 &&
			strlen($s2) == 4 &&
			strlen($s3) == 4 &&
			strlen($s4) == 12
		) {
			// guid type
			$profile = $db->do_select("
				SELECT
				guid,
				account_type,
				role,
				pseudonym,
				created_at,
				avatar_url,
				kyc_hash
				FROM users 
				WHERE guid = '$identifier'
			");
		} elseif(preg_match(Regex::$pseudonym['pattern'], $identifier)) {
			// pseudonym type
			$profile = $db->do_select("
				SELECT
				guid,
				account_type,
				role,
				pseudonym,
				created_at,
				avatar_url,
				kyc_hash
				FROM users 
				WHERE pseudonym = '$identifier'
			");
		} else {
			$profile = array();
		}

		$profile   = $profile[0] ?? array();
		$guid      = $profile['guid'] ?? '';
		$pseudonym = $profile['pseudonym'] ?? '';
		$kyc_hash  = $profile['kyc_hash'] ?? '';

		if (!$guid) {
			_exit(
				'error',
				'Invalid user profile',
				404,
				'Invalid user profile'
			);
		}

		// nodes
		$nodes = $db->do_select("
			SELECT public_key
			FROM user_nodes
			WHERE guid = '$guid'
			AND verified IS NOT NULL
		");

		$profile["nodes"] = $nodes;

		// kyc
		$kyc_info = $db->do_select("
			SELECT
			reference_id,
			status AS kyc_status,
			updated_at AS verified_at
			FROM shufti
			WHERE guid = '$guid'
		");

		$kyc_info     = $kyc_info[0] ?? array();
		$reference_id = $kyc_info['reference_id'] ?? '';
		$kyc_status   = $kyc_info['kyc_status'] ?? '';
		$verified_at  = $kyc_info['verified_at'] ?? '';

		$kyc_hash = md5(
			$pseudonym.
			$reference_id.
			$kyc_status
		);

		$update_user = $db->do_query("
			UPDATE users
			SET kyc_hash = '$kyc_hash'
			WHERE guid = '$guid'
		");

		$profile["kyc_hash"]    = $kyc_hash;
		$profile["kyc_status"]  = $kyc_status;
		$profile["verified_at"] = $verified_at;

		// account info standard
		$public_key0 = $nodes[0]['public_key'] ?? '';
		$account_info_standard = $helper->get_account_info_standard($public_key0);

		// merge
		$return = array_merge(
			$profile,
			$account_info_standard
		);

		_exit(
			'success',
			$return
		);
	}
}
new AdminGetProfile();