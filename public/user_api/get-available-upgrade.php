<?php
/**
 *
 * GET /user/get-available-upgrade
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class UserGetAvailableUpgrade extends Endpoints {
	function __construct() {
		global $db, $helper;

		require_method('GET');

		$auth        = authenticate_session(1);
		$user_guid   = $auth['guid'] ?? '';
		$now         = $helper->get_datetime();
		$current_era = $helper->get_current_era_id();

		$upgrade   = $db->do_select("
			SELECT *
			FROM  upgrades
			WHERE visible = 1
			AND   activate_era > $current_era
			LIMIT 1
		");

		$upgrade = $upgrade[0] ?? array(
			'created_at'  => '',
			'activate_at' => ''
		);

		$time  = time();
		$end   = strtotime($upgrade['activate_at'].' UTC');
		$start = strtotime($upgrade['created_at'].' UTC');

		$numerator   = $end - $time;
		$denominator = $end - $start;
		$numerator   = $numerator <= 0 ? 1 : $numerator;
		$denominator = $denominator <= 0 ? 1 : $denominator;

		$r = $helper->get_timedelta($numerator);

		$upgrade['time_remaining']      = $r;
		$upgrade['time_remaining_perc'] = round($numerator / $denominator * 100);

		if ($upgrade['time_remaining_perc'] < 0) {
			$upgrade['time_remaining_perc'] = 0;
		}

		_exit(
			'success',
			$upgrade
		);
	}
}
new UserGetAvailableUpgrade();