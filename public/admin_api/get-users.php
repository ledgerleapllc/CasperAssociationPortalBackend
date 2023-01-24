<?php
include_once('../../core.php');
/**
 *
 * GET /admin/get-users
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class AdminGetUsers extends Endpoints {
	function __construct() {
		global $db, $helper;

		require_method('GET');
		$auth           = authenticate_session(2);
		$admin_guid     = $auth['guid'] ?? '';
		$current_era_id = $helper->get_current_era_id();

		$users = $db->do_select("
			SELECT
			a.guid,
			a.email,
			a.pseudonym,
			a.account_type,
			a.verified,
			a.created_at AS registration_date,
			a.admin_approved,
			a.pii_data
			FROM users AS a
			WHERE a.role LIKE '%user'
		");

		$users = $users ?? array();

		foreach ($users as &$user) {
			// pii
			$pii_data = $helper->decrypt_pii($user['pii_data'] ?? '');
			$user['pii_data'] = $pii_data ?? array(
				"first_name"      => "",
				"middle_name"     => "",
				"last_name"       => "",
				"registration_ip" => "",
				"phone"           => "",
			);

			// entities
			$user_guid = $user['guid'] ?? '';
			$entity = $db->do_select("
				SELECT 
				a.pii_data AS entity_pii
				FROM  entities AS a
				JOIN  user_entity_relations AS b
				ON    b.entity_guid = a.entity_guid
				WHERE b.user_guid   = '$user_guid'
			");

			$entity         = $entity[0] ?? array();
			$entity_pii     = $helper->decrypt_pii($entity['entity_pii'] ?? '');
			$user['entity'] = $entity_pii ?? array(
				"entity_name"          => "",
				"entity_type"          => "",
				"registration_number"  => "",
				"registration_country" => "",
				"tax_id"               => "",
			);

			// kyc
			$kyc_status = $db->do_select("
				SELECT
				status AS kyc_status
				FROM shufti
				WHERE guid = '$user_guid'
			");

			$kyc_status = $kyc_status[0]['kyc_status'] ?? 'Not Verified';
			$user['kyc_status'] = $kyc_status;

			// membership status
			$membership_status = ucfirst($kyc_status);

			$nodes = $db->do_select("
				SELECT 
				a.public_key,
				b.status AS node_status,
				b.bid_total_staked_amount
				FROM user_nodes AS a
				JOIN all_node_data AS b
				ON a.public_key = b.public_key
				WHERE a.guid = '$user_guid'
				AND b.era_id = $current_era_id
				AND a.verified IS NOT NULL
			");

			$nodes         = $nodes ?? array();
			$user['nodes'] = $nodes;
			$total_stake   = 0;

			if (!$nodes || empty($nodes)) {
				$membership_status = 'Offline';
			}

			foreach ($nodes as $node) {
				$total_stake += $node['bid_total_staked_amount'];

				if ($node['node_status'] != 'online') {
					$user['$membership_status'] = ucfirst($node['node_status']);
				}
			}

			$user['total_stake']       = $total_stake;
			$user['membership_status'] = $membership_status;
		}

		_exit(
			'success',
			$users
		);
	}
}
new AdminGetUsers();