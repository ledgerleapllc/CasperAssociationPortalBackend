<?php
/**
 *
 * GET /user/get-entity-docs
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class UserGetEntityDocs extends Endpoints {
	function __construct() {
		global $db, $helper, $S3;

		require_method('GET');

		$auth      = authenticate_session(1);
		$user_guid = $auth['guid'] ?? '';

		$entity_docs = $db->do_select("
			SELECT *
			FROM entity_docs
			WHERE user_guid   = '$user_guid'
			AND   entity_guid IS NULL
		");

		_exit(
			'success',
			$entity_docs
		);
	}
}
new UserGetEntityDocs();