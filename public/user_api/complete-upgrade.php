<?php
include_once('../../core.php');
/**
 *
 * POST /user/complete-upgrade
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param string $version
 *
 */
class UserGetUpgrades extends Endpoints {
	function __construct(
		$version = ''
	) {
		global $db, $helper;

		require_method('POST');

		$auth      = authenticate_session(1);
		$user_guid = $auth['guid'] ?? '';
		$now       = $helper->get_datetime();
		$version   = parent::$params['version'] ?? '';

		// check
		$check = $db->do_select("
			SELECT *
			FROM upgrades
			WHERE version = '$version'
		");

		if (!$check) {
			_exit(
				'error',
				'Protocol version does not exist',
				400,
				'Protocol version does not exist'
			);
		}

		// check user
		$check = $db->do_select("
			SELECT *
			FROM user_upgrades
			WHERE version = '$version'
		");

		$status     = $check[0]['status'] ?? '';
		$created_at = $check[0]['created_at'] ?? '';

		if (!$check) {
			// insert completion
			$db->do_query("
				INSERT INTO user_upgrades (
					guid,
					version,
					status,
					created_at
				) VALUES (
					'$user_guid',
					'$version',
					'complete',
					'$now'
				)
			");

			_exit(
				'success',
				'Protocol upgrade marked as completed'
			);
		} else {
			if (!$created_at || $status != 'complete') {
				// do completion update
				$db->do_query("
					UPDATE user_upgrades
					SET
					status     = 'complete',
					created_at = '$now'
					WHERE guid = '$user_guid'
				");

				_exit(
					'success',
					'Protocol upgrade marked as completed'
				);
			} else {
				// already completed
				_exit(
					'error',
					'Protocol version already marked as completed by you',
					400,
					'Protocol version already marked as completed by you'
				);
			}
		}

		_exit(
			'error',
			'There was a problem marking this protocol upgrade as completed by you at this time',
			500,
			'There was a problem marking this protocol upgrade as completed by you at this time'
		);
	}
}
new UserGetUpgrades();