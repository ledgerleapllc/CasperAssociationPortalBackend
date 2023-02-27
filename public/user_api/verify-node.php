<?php
/**
 *
 * POST /user/verify-node
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param string $address
 *
 */
class UserVerifyNode extends Endpoints {
	function __construct(
		$address = ''
	) {
		global $db, $helper;

		require_method('POST');

		$auth      = authenticate_session(1);
		$user_guid = $auth['guid'] ?? '';
		$address   = parent::$params['address'] ?? '';
		$now       = $helper->get_datetime();

		$helper->sanitize_input(
			$address,
			true,
			Regex::$validator_id['char_limit'] - 2,
			Regex::$validator_id['char_limit'],
			Regex::$validator_id['pattern'],
			'Validator ID'
		);

		$pre_check = $db->do_select("
			SELECT guid
			FROM   user_nodes
			WHERE  public_key = '$address'
			AND    guid       = '$user_guid'
			AND    verified   IS NOT NULL
		");

		if ($pre_check) {
			_exit(
				'success',
				'You own this node'
			);
		}

		$can_claim = $helper->can_claim_validator_id($address);

		if ($can_claim) {
			$db->do_query("
				DELETE FROM user_nodes
				WHERE public_key = '$address'
			");

			$db->do_query("
				INSERT INTO user_nodes (
					guid,
					created_at,
					updated_at,
					public_key
				) VALUES (
					'$user_guid',
					'$now',
					'$now',
					'$address'
				)
			");

			_exit(
				'success',
				$address.' is available to claim'
			);
		}

		_exit(
			'error',
			'The specified public key was not found in the pool of claimable validator IDs',
			403,
			'The specified public key was not found in the pool of claimable validator IDs'
		);
	}
}
new UserVerifyNode();
