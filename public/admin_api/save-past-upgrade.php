<?php
/**
 *
 * POST /admin/save-past-upgrade
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param string $version
 * @param string $activate_at
 * @param int    $activate_era
 * @param string $link
 * @param string $notes
 *
 */
class AdminSavePastUpgrade extends Endpoints {
	function __construct(
		$version      = '',
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

		$version      = parent::$params['version'] ?? '';
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

		// check version
		$check = $db->do_select("
			SELECT id
			FROM  upgrades
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
		$db->do_query("
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
				1,
				'$activate_at',
				$activate_era,
				'$link',
				'$notes'
			)
		");

		_exit(
			'success',
			'Saved protocol upgrade'
		);
	}
}
new AdminSavePastUpgrade();