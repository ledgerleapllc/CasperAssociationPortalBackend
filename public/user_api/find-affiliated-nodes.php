<?php
/**
 *
 * GET /user/find-affiliated-nodes
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class UserFindAffiliatedNodes extends Endpoints {
	function __construct() {
		global $db, $helper;

		require_method('GET');

		$auth = authenticate_session(1);
		$user_guid = $auth['guid'] ?? '';

		$all_nodes = $db->do_select("
			SELECT public_key
			FROM user_nodes
			WHERE guid = '$user_guid'
			AND verified IS NOT NULL
		");

		$all_nodes  = $all_nodes ?? array();
		$all_nodes2 = array();

		foreach ($all_nodes as $key => $value) {
			$public_key = $value["public_key"] ?? "";

			if ($public_key) {
				$all_nodes2[] = $public_key;
			}
		}

		$first_node       = $all_nodes2[0] ?? '';
		$account_info     = $helper->get_account_info_standard($first_node);
		$associated_nodes = (array)($account_info['associated_nodes'] ?? array());

		foreach ($associated_nodes as $index => $node) {
			if (in_array($node, $all_nodes2)) {
				unset($associated_nodes[$index]);
			}
		}

		$associated_nodes = array_values($associated_nodes);

		_exit(
			'success',
			$associated_nodes
		);
	}
}
new UserFindAffiliatedNodes();
