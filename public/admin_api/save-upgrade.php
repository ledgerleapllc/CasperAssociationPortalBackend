<?php
include_once('../../core.php');
/**
 *
 * POST /admin/save-upgrade
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param int    $id
 * @param string $version
 * @param bool   $visible
 * @param string $activate_at
 * @param int    $activate_era
 * @param string $link
 * @param string $notes
 *
 */
class AdminSaveUpgrade extends Endpoints {
	function __construct(
		$upgrade_id   = 0,
		$version      = '',
		$visible      = false,
		$activate_at  = '',
		$activate_era = 0,
		$link         = '',
		$notes        = ''
	) {
		global $db, $helper;

		require_method('POST');

		$auth         = authenticate_session(2);
		$admin_guid   = $auth['guid'] ?? '';
		$current_era  = $helper->get_current_era_id();

		$upgrade_id   = (int)(parent::$params['id'] ?? 0);
		$version      = parent::$params['version'] ?? '';
		$visible      = (bool)(parent::$params['visible'] ?? 0);
		$activate_at  = parent::$params['activate_at'] ?? '';
		$activate_era = (int)(parent::$params['activate_era'] ?? 0);
		$link         = parent::$params['link'] ?? '';
		$notes        = parent::$params['notes'] ?? '';

		$created_at   = $helper->get_datetime();

		if (strlen($version) > 32) {
			_exit(
				'error',
				'Version number too long. Limit to 32 characters',
				400,
				'Upgrade Version number too long. Limit to 32 characters'
			);
		}

		if (strlen($link) > 255) {
			_exit(
				'error',
				'Software link too long. Limit to 255 characters',
				400,
				'Upgrade Software link too long. Limit to 255 characters'
			);
		}

		if (strlen($notes) > 4096) {
			_exit(
				'error',
				'Notes too long. Limit to 4096 characters',
				400,
				'Upgrade Notes too long. Limit to 4096 characters'
			);
		}

		$helper->sanitize_input(
			$activate_at,
			true,
			Regex::$date_extended['char_limit'],
			Regex::$date_extended['char_limit'],
			Regex::$date_extended['pattern'],
			'Activation date'
		);

		$visible = (int)$visible;

		if ($upgrade_id == 0) {
			// check version
			$check = $db->do_select("
				SELECT id
				FROM upgrades
				WHERE version = '$version'
			");

			if ($check) {
				_exit(
					'error',
					'Protocol upgrade version '.$version.' already exists.',
					400,
					'Protocol upgrade version '.$version.' already exists.'
				);
			}

			// insert
			$query = "
				INSERT INTO upgrades (
					version,
					created_at,
					updated_at,
					visible,
					activate_at,
					activate_era,
					link,
					notes
				) VALUES (
					'$version',
					'$created_at',
					'$created_at',
					$visible,
					'$activate_at',
					$activate_era,
					'$link',
					'$notes'
				)
			";
		} else {
			$query = "
				UPDATE upgrades
				SET
				version      = '$version',
				updated_at   = '$created_at',
				visible      = $visible,
				activate_at  = '$activate_at',
				activate_era = $activate_era,
				link         = '$link',
				notes        = '$notes'
				WHERE id     = $upgrade_id
			";
		}

		$db->do_query($query);

		_exit(
			'success',
			'Saved protocol upgrade'
		);
	}
}
new AdminSaveUpgrade();