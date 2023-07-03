<?php
/**
 *
 * HEADER Authorization: Token
 *
 */
include_once(__DIR__.'/../../core.php');

global $db, $helper;

$now               = $helper->get_datetime();
$current_era_id    = $helper->get_current_era_id();

// collect settings
$uptime_calc_size  = (int)$helper->fetch_setting('uptime_calc_size');
$uptime_warning    = (float)$helper->fetch_setting('uptime_warning');
$uptime_probation  = (float)$helper->fetch_setting('uptime_probation');
$correction_units  = (int)$helper->fetch_setting('uptime_correction_units');
$correction_metric = $helper->fetch_setting('uptime_correction_metric');
$correction_time   = 0;

if ($correction_metric == 'minutes') {
	$correction_time = $correction_units * 60;
} elseif ($correction_metric == 'hours') {
	$correction_time = $correction_units * 60 * 60;
} elseif ($correction_metric == 'days') {
	$correction_time = $correction_units * 60 * 60 * 24;
} else {
	$correction_time = $correction_units;
}

$nodes = $db->do_select("
	SELECT
	guid,
	public_key
	FROM user_nodes
	WHERE verified IS NOT NULL
");
$nodes = $nodes ?? array();
// elog($nodes);

foreach ($nodes as $node) {
	$guid       = $node['guid'] ?? '';
	$public_key = $node['public_key'] ?? '';
	$pk_short   = $helper->format_hash($public_key, 15);
	$query      = null;

	$h = $db->do_select("
		SELECT
		uptime,
		historical_performance,
		status
		FROM  all_node_data
		WHERE public_key = '$public_key'
		AND   era_id     = $current_era_id
	");

	$uptime                 = $h[0]['uptime'] ?? 0;
	$historical_performance = $h[0]['historical_performance'] ?? 0;
	$status_check           = $h[0]['status'] ?? 'offline';

	// pass if suspended. let suspension class handle it
	if ($status_check == 'suspended') {
		continue;
	}

	// check
	$check = $db->do_select("
		SELECT guid
		FROM  warnings
		WHERE guid       = '$guid'
		AND   public_key = '$public_key'
	");

	if (!$check) {
		// clear duplicates
		$db->do_query("
			DELETE FROM warnings
			WHERE public_key = '$public_key'
		");

		$db->do_query("
			INSERT INTO warnings (
				guid,
				public_key,
				created_at,
				type,
				message,
				dismissed_at
			) VALUES (
				'$guid',
				'$public_key',
				'$now',
				'warning',
				'',
				'$now'
			)
		");
	}

	if ($uptime >= $uptime_warning) {
		// all good
		$query = "
			UPDATE warnings
			SET
			type             = 'warning',
			created_at       = '$now',
			dismissed_at     = '$now',
			message          = ''
			WHERE guid       = '$guid'
			AND   public_key = '$public_key'
		";
	}

	if (
		$uptime <  $uptime_warning &&
		$uptime >= $uptime_probation
	) {
		$query = "
			UPDATE warnings
			SET
			type         = 'warning',
			created_at   = '$now',
			dismissed_at = NULL,
			message      = 'Warning - Your node $pk_short is starting to fall out of acceptable Casper Association membership criteria. Uptime is $uptime%. Please check the health of your node and make adjustments to fix it. If your node uptime falls below $uptime_probation%, you may become at risk of membership probation.'
			WHERE guid       = '$guid'
			AND   public_key = '$public_key'
		";
	}

	if ($uptime < $uptime_probation) {
		$probation_at = $db->do_select("
			SELECT created_at
			FROM  warnings
			WHERE guid       = '$guid'
			AND   public_key = '$public_key'
			AND (
				type = 'probation' OR
				type = 'suspension'
			)
		");

		$probation_at = $probation_at[0]['created_at'] ?? '';
		$diff         = time() - strtotime($probation_at.' UTC');
		$delta        = $correction_time - $diff;
		$delta        = $delta < 0 ? 0 : $delta;
		$time_left    = $helper->get_timedelta($delta);
		$time_left_ux = '';

		// format time left
		$split    = explode(':', $time_left);
		$s_day    = (int)($split[0] ?? '');
		$s_hour   = (int)($split[1] ?? '');
		$s_minute = (int)($split[2] ?? '');

		if ($s_minute > 0) {
			$time_left_ux = $s_minute.' minute';

			if ($s_minute != 1) {
				$time_left_ux .= 's';
			}
		}

		if ($s_hour > 0) {
			$time_left_ux = $s_hour.' hour';

			if ($s_hour != 1) {
				$time_left_ux .= 's';
			}
		}

		if ($s_day > 0) {
			$time_left_ux = $s_day.' day';

			if ($s_day != 1) {
				$time_left_ux .= 's';
			}
		}

		// already in probation countdown to suspension
		if ($probation_at) {
			if ($delta == 0) {
				// DROP INTO SUSPENSION
				$query = "
					UPDATE warnings
					SET
					type             = 'suspension',
					dismissed_at     = NULL,
					message          = 'Your node $pk_short has fallen outside of acceptable Casper Association membership criteria. Uptime is $uptime%, less than the required $uptime_probation%. Your account is in suspension. Please check the health of your node and make adjustments to fix it.'
					WHERE guid       = '$guid'
					AND   public_key = '$public_key'
				";

				$db->do_query("
					UPDATE all_node_data
					SET   status     = 'suspended'
					WHERE public_key = '$public_key'
					AND   era_id     = $current_era_id
				");

				// insert into suspensions
				$db->do_query("
					INSERT INTO suspensions (
						guid,
						created_at,
						reason
					) VALUES (
						'$guid',
						'$now',
						'uptime'
					)
				");
			}

			else {
				// Still counting down to probation
				$query = "
					UPDATE warnings
					SET
					type             = 'probation',
					dismissed_at     = NULL,
					message          = 'Your node $pk_short has fallen outside of acceptable Casper Association membership criteria. Uptime is $uptime%, less than the required $uptime_probation%. You have $time_left_ux to correct the issue to avoid suspension. Please check the health of your node and make adjustments to fix it.'
					WHERE guid       = '$guid'
					AND   public_key = '$public_key'
				";

				// update all_node_data by public_key and current_era
				$db->do_query("
					UPDATE all_node_data
					SET   status     = 'probation'
					WHERE public_key = '$public_key'
					AND   era_id     = $current_era_id
				");

				// email for user, as per global settings
				$enabled = (bool)$helper->fetch_setting('enabled_probation');

				if (
					$enabled && (
						$status_check == 'online'  ||
						$status_check == 'offline'
					)
				) {
					$subject = 'You have been placed on probation';
					$body    = $helper->fetch_setting('email_probation');

					$user_email = $db->do_select("
						SELECT email
						FROM users
						WHERE guid = '$guid'
					");
					$user_email = $user_email[0]['email'] ?? '';

					elog('PROBATION TRIGGERED');////
					elog($user_email);
					elog($uptime.' '.$status_check.' '.$probation_at.' '.$pk_short);

					if($body && $user_email) {
						$helper->schedule_email(
							'user-alert',
							$user_email,
							$subject,
							$body
						);
					}
				}
			}

		// Now starting probation countdown
		} else {
			$query = "
				UPDATE warnings
				SET
				type             = 'probation',
				created_at       = '$now',
				dismissed_at     = NULL,
				message          = 'Your node $pk_short has fallen outside of acceptable Casper Association membership criteria. Uptime is $uptime%, less than the required $uptime_probation%. You have $time_left_ux to correct the issue to avoid suspension. Please check the health of your node and make adjustments to fix it.'
				WHERE guid       = '$guid'
				AND   public_key = '$public_key'
			";
		}
	}

	if ($query) {
		// elog($query);
		$db->do_query($query);
	}
}
