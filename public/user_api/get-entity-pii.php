<?php
include_once('../../core.php');
/**
 *
 * GET /user/get-entity-pii
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class UserGetEntityPii extends Endpoints {
	function __construct() {
		global $db, $helper;

		require_method('GET');

		$auth        = authenticate_session(1);
		$user_guid   = $auth['guid'] ?? '';
		$entity_piis = $helper->get_user_entities($user_guid);
		$output      = array();

		foreach ($entity_piis as $entity_guid => $entity_pii) {
			$output = $entity_pii;
			break;
		}

		_exit(
			'success',
			$output
		);
	}
}
new UserGetEntityPii();