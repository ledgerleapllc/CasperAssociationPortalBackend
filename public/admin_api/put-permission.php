<?php
/**
 *
 * PUT /admin/put-permission
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param string $guid
 * @param string $permission
 * @param int    $value
 *
 */
class AdminPutPermission extends Endpoints {
	function __construct(
		$guid       = '',
		$permission = '',
		$value      = 0
	) {
		global $db, $helper, $permissions;

		require_method('PUT');

		$auth       = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';
		$guid       = parent::$params['guid'] ?? '';
		$permission = strtolower(parent::$params['permission'] ?? '');
		$value      = (int)(parent::$params['value'] ?? 0);
		$pass       = false;

		// admin check
		$check = $helper->get_user($guid);
		$role  = $check['role'] ?? '';

		if ($role == 'sub-admin') {
			authenticate_session(3);
		}

		if ($role == 'admin') {
			authenticate_session(4);

			// _exit(
			// 	'error',
			// 	'Level 3 Admins can only be revoked by a super admin',
			// 	400,
			// 	'Level 3 Admins can only be revoked by a super admin'
			// );
		}

		switch ($permission) {
			case "membership":      $pass = true; break;
			case "nodes":           $pass = true; break;
			case "eras":            $pass = true; break;
			case "discussions":     $pass = true; break;
			case "ballots":         $pass = true; break;
			case "perks":           $pass = true; break;
			case "intake":          $pass = true; break;
			case "users":           $pass = true; break;
			case "teams":           $pass = true; break;
			case "global_settings": $pass = true; break;
			default:                $pass = false; break;
		}

		if (!$pass) {
			_exit(
				'error',
				'Invalid permissions name',
				400,
				'Invalid permissions name'
			);
		}

		if (
			$value != 0 &&
			$value != 1
		) {
			_exit(
				'error',
				'Permission value must be 1 or 0 (true/false)',
				400,
				'Permission value must be 1 or 0 (true/false)'
			);
		}

		// ensure existence first
		$permissions->allowed($guid);

		$db->do_query("
			UPDATE permissions
			SET   $permission = $value
			where  guid       = '$guid'
		");

		_exit(
			'success',
			'Permission updated'
		);
	}
}
new AdminPutPermission();