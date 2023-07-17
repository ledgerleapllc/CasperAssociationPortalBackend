<?php
/**
 *
 * GET /admin/get-user
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param string  $guid
 *
 */
class AdminGetUser extends Endpoints {
	function __construct(
		$guid = ''
	) {
		global $db, $helper, $suspensions;

		require_method('GET');

		$auth       = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';
		$guid       = (string)(parent::$params['guid'] ?? '');
		$user       = $helper->get_user($guid);

		$nodes      = $db->do_select("
			SELECT public_key
			FROM user_nodes
			WHERE guid = '$guid'
			AND verified IS NOT NULL
		");

		$nodes = $nodes ?? array();

		foreach ($nodes as $key => $val) {
			$user['nodes'][] = $val['public_key'];
		}

		// kyc
		$kyc = $db->do_select("
			SELECT
			a.status AS kyc_status,
			a.reference_id,
			a.manual_review,
			a.reviewed_at,
			b.email AS reviewed_by,
			a.created_at,
			a.cmp_checked,
			a.declined_reason,
			a.id_check,
			a.address_check,
			a.background_check
			FROM shufti AS a
			LEFT JOIN users AS b
			ON a.reviewed_by = b.guid
			WHERE a.guid = '$guid'
		");

		$kyc = $kyc[0] ?? array(
			"kyc_status"        => "Not verified",
			"reference_id"      => "",
			"manual_review"     => 0,
			"reviewed_at"       => "",
			"reviewed_by"       => "",
			"created_at"        => "",
			"cmp_checked"       => 0,
			"declined_reason"   => '',
			"id_check"          => 0,
			"address_check"     => 0,
			"background_check"  => 0
		);

		$user['kyc'] = $kyc;

		// handle suspension
		$sus = $suspensions->is_suspended($guid);

		if ($sus) {
			$user["membership_status"] = "Revoked";
		} else {
			$user["membership_status"] = $kyc["kyc_status"];
		}

		// entity detail (if applicable)
		$entities = $helper->get_user_entities($guid);
		$entity   = array();

		foreach ($entities as $e) {
			$entity = $e;
			break;
		}

		$user['entity'] = $entity;

		_exit(
			'success',
			$user
		);
	}
}
new AdminGetUser();
