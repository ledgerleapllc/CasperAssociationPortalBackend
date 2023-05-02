<?php
/**
 *
 * GET /admin/get-available-upgrade
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class AdminGetAvailableUpgrade extends Endpoints {
	function __construct() {
		global $db, $helper;

		require_method('GET');

		$auth        = authenticate_session(2);
		$admin_guid  = $auth['guid'] ?? '';
		$now         = $helper->get_datetime();
		$current_era = $helper->get_current_era_id();

		// do blockchain check
		$ch = curl_init();
		curl_setopt(
			$ch, 
			CURLOPT_URL, 
			'http://'.NODE_IP.':8888/status'
		);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$port8888_response = curl_exec($ch);

		if (curl_errno($ch)) {
			elog('Protocol upgrade CURL error: '.curl_error($ch));
		}

		curl_close($ch);

		try {
			$port8888_json = json_decode($port8888_response);
		} catch (Exception $e) {
			elog('Protocol upgrade trusted node CURL error: ');
			elog($e);
			$port8888_json = array();
		}

		$next_upgrade     = $port8888_json->next_upgrade ?? null;
		$activation_point = (int)($next_upgrade->activation_point ?? 0);
		$protocol_version = $next_upgrade->protocol_version ?? '';

		if ($activation_point && $protocol_version) {
			$check = $db->do_select("
				SELECT id
				FROM upgrades
				WHERE version = '$protocol_version'
			");

			if (!$check) {
				// estimate activation date from activation_point
				$eras_diff     = $activation_point - $current_era;
				$hours         = $eras_diff * 2;
				$activation_at = $helper->get_datetime($hours * 60 * 60);

				// insert
				$db->do_query("
					INSERT INTO upgrades (
						version,
						created_at,
						updated_at,
						visible,
						activate_at,
						activate_era
					) VALUES (
						'$protocol_version',
						'$now',
						'$now',
						0,
						'$activation_at',
						$activation_point
					)
				");
			}
		}

		$upgrade = $db->do_select("
			SELECT *
			FROM upgrades
			WHERE activate_era > $current_era
			ORDER BY id DESC
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
new AdminGetAvailableUpgrade();