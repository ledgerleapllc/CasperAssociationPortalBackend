<?php
/**
 *
 * PUT /admin/cmp-check
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param string $guid
 * @param bool   $value
 *
 */
class AdminCmpCheck extends Endpoints {
	function __construct(
		$guid  = '',
		$value = true
	) {
		global $db, $helper;

		require_method('PUT');

		$auth       = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';
		$guid       = parent::$params['guid'] ?? '';
		$value      = (bool)(parent::$params['value'] ?? 0);
		$value      = (int)$value;

		$db->do_query("
			UPDATE shufti
			SET cmp_checked = $value
			WHERE guid = '$guid'
		");

		_exit(
			'success',
			'CMP validated this user'
		);
	}
}
new AdminCmpCheck();