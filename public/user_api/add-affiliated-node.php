<?php
/**
 *
 * POST /user/add-affiliated-node
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param string $address
 *
 */
class UserAddAffiliatedNode extends Endpoints {
	function __construct(
		$address = ''
	) {
		global $db, $helper;

		require_method('POST');

		$auth      = authenticate_session(1);
		$user_guid = $auth['guid'] ?? '';
		$address   = strtolower(parent::$params['address'] ?? '');
		$now       = $helper->get_datetime();

		$helper->sanitize_input(
			$address,
			true,
			Regex::$validator_id['char_limit'] - 2,
			Regex::$validator_id['char_limit'],
			Regex::$validator_id['pattern'],
			'Validator ID'
		);

		$can_claim = $helper->can_claim_validator_id($address);

		if ($can_claim) {
			$first_address = $db->do_select("
				SELECT public_key
				FROM user_nodes
				WHERE guid = '$user_guid'
				ORDER BY created_at DESC
				LIMIT 1
			");

			$first_address    = $first_address[0]['public_key'] ?? '';
			$account_info     = $helper->get_account_info_standard($first_address);
			$associated_nodes = ($account_info['associated_nodes'] ?? array());
			$verified         = false;

			foreach ($associated_nodes as $node) {
				if ($node == $address) {
					$verified = true;
				}
			}

			if ($verified) {
				$db->do_query("
					DELETE FROM user_nodes
					WHERE public_key = '$address'
				");

				$db->do_query("
					INSERT INTO user_nodes (
						guid,
						created_at,
						updated_at,
						public_key,
						verified
					) VALUES (
						'$user_guid',
						'$now',
						'$now',
						'$address',
						'$now'
					)
				");

				_exit(
					'success',
					'Added '.$address.' to your account'
				);
			}

			_exit(
				'error',
				'You are not authorized to do that',
				403
			);
		}

		_exit(
			'error',
			'You are not authorized to do that',
			403
		);
	}
}
new UserAddAffiliatedNode();