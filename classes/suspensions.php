<?php
/**
 *
 * Suspensions controller
 *
 */
class Suspensions {
	function __construct() {
		//
	}

	function __destruct() {
		// do nothing yet
	}

	/**
	 *
	 * Fetches suspended users
	 *
	 * @param  string $guid
	 * @return array  $users
	 *
	 */
	public static function get_suspended_users() {
		global $db;

		$users = $db->do_select("
			SELECT guid
			FROM  suspensions
			WHERE reinstated = 0
		");

		$users = $users ?? array();

		return $users;
	}

	/**
	 *
	 * Simply returns if a user is suspended by guid
	 *
	 * @param  string $guid
	 * @return bool
	 *
	 */
	public static function is_suspended($guid) {
		$guids = self::get_suspended_users();

		foreach ($guids as $g) {
			$gg = $g['guid'] ?? '';

			if ($gg == $guid) {
				return true;
			}
		}

		return false;
	}

	/**
	 *
	 * Returns the reason for the suspension, by user guid
	 *
	 * @param  string $guid
	 * @return string $reason
	 *
	 */
	public static function suspension_reason($guid) {
		global $db;

		$reason = $db->do_select("
			SELECT reason
			FROM  suspensions
			WHERE guid       = '$guid'
			AND   reinstated = 0
			ORDER BY created_at DESC
			LIMIT 1
		");

		$reason = $reason[0]['reason'] ?? '';

		return $reason;
	}

	/**
	 *
	 * Returns the decision for the suspension, by user guid
	 *
	 * @param  string $guid
	 * @return string $decision
	 *
	 */
	public static function reinstatement_decision($guid) {
		global $db;

		$decision = $db->do_select("
			SELECT decision
			FROM  suspensions
			WHERE guid       = '$guid'
			ORDER BY created_at DESC
			LIMIT 1
		");

		$decision = $decision[0]['decision'] ?? '';

		return $decision;
	}

	/**
	 *
	 * Returns the reinstatement letter, by user guid
	 *
	 * @param  string $guid
	 * @return string $letter
	 *
	 */
	public static function reinstatement_letter($guid) {
		global $db;

		$letter = $db->do_select("
			SELECT letter
			FROM  suspensions
			WHERE guid       = '$guid'
			AND   reinstated = 0
			ORDER BY created_at DESC
			LIMIT 1
		");

		$letter = $letter[0]['letter'] ?? '';

		return $letter;
	}

	/**
	 *
	 * Simply returns if a user can be re-instated based on stats
	 *
	 * @param  string $guid
	 * @return bool
	 *
	 */
	public static function can_reinstate($guid) {
		global $db;

		$reinstatable = $db->do_select("
			SELECT reinstatable
			FROM suspensions
			WHERE guid = '$guid'
			ORDER BY created_at DESC
			LIMIT 1
		");

		$reinstatable = (bool)($reinstatable[0]['reinstatable'] ?? 0);

		return false;
	}

	/**
	 *
	 * Get user suspension details by guid
	 *
	 * @param  string $guid
	 * @return array
	 *
	 */
	public static function user_reinstatement_stats($guid) {
		global $db, $helper;

		$current_era_id    = $helper->get_current_era_id();
		$uptime_probation  = (float)$helper->fetch_setting('uptime_probation');
		$redmark_revoke    = (int)($helper->fetch_setting('redmark_revoke'));
		$redmark_calc_size = (int)($helper->fetch_setting('redmark_calc_size'));

		$uptime_flag  = 100;
		$redmark_flag = 0;

		$nodes = $db->do_select("
			SELECT public_key
			FROM  user_nodes
			WHERE guid = '$guid'
			AND   verified IS NOT NULL
		");

		$nodes = $nodes ?? array();

		foreach ($nodes as $node) {
			$public_key = $node['public_key'] ?? '';

			// check uptime
			$uptime = $db->do_select("
				SELECT uptime
				FROM  all_node_data
				WHERE public_key = '$public_key'
				AND   era_id     = $current_era_id
			");
			$uptime = (float)($uptime[0]['uptime'] ?? 0);

			if ($uptime < $uptime_flag) {
				$uptime_flag = $uptime;
			}

			// check redmarks
			$total_redmarks = $helper->get_era_data(
				$public_key,
				$redmark_calc_size
			);

			$total_redmarks = (int)($total_redmarks['total_redmarks'] ?? 0);

			if ($total_redmarks > $redmark_flag) {
				$redmark_flag = $total_redmarks;
			}
		}

		$reinstatable = 1;

		if (
			$uptime_flag  <  $uptime_probation ||
			$redmark_flag >= $redmark_revoke
		) {
			$reinstatable = 0;
		}

		// select
		$sid = $db->do_select("
			SELECT id
			FROM suspensions
			WHERE guid = '$guid'
			ORDER BY created_at DESC
			LIMIT 1
		");

		$sid = (int)($sid[0]['id'] ?? 0);

		// update
		$db->do_query("
			UPDATE suspensions
			SET   reinstatable = $reinstatable
			WHERE id           = '$sid'
		");

		return array(
			'uptime'   => $uptime_flag,
			'redmarks' => $redmark_flag
		);
	}

	/**
	 *
	 * Administrative method to reinstate a user by guid
	 *
	 * @param  string $guid
	 * @return bool
	 *
	 */
	public static function reinstate_user($guid) {
		global $db, $helper;

		$now            = $helper->get_datetime();
		$current_era_id = $helper->get_current_era_id();

		$sid = $db->do_select("
			SELECT id
			FROM suspensions
			WHERE guid = '$guid'
			ORDER BY created_at DESC
			LIMIT 1
		");

		$sid = (int)($sid[0]['id'] ?? 0);

		// suspension table
		$db->do_query("
			UPDATE suspensions
			SET
			reinstated    = 1,
			reinstated_at = '$now',
			decision      = 'Yes'
			WHERE id      = '$sid'
		");

		// revert all_node_data status
		$public_keys = $db->do_select("
			SELECT public_key
			FROM user_nodes
			WHERE guid = '$guid'
		");

		$public_keys = $public_keys ?? array();

		foreach ($public_keys as $public_key) {
			$pk = $public_key['public_key'] ?? '';

			$db->do_query("
				UPDATE all_node_data
				SET   status     = 'online'
				WHERE public_key = '$pk'
				AND   era_id     = $current_era_id
			");
		}

		return true;
	}

	/**
	 *
	 * Administrative method to reset a suspension, decision "No"
	 *
	 * @param  string $guid
	 * @return bool
	 *
	 */
	public static function reset_user_suspension($guid) {
		global $db, $helper;

		$now = $helper->get_datetime();

		$sid = $db->do_select("
			SELECT id
			FROM suspensions
			WHERE guid = '$guid'
			ORDER BY created_at DESC
			LIMIT 1
		");

		$sid = (int)($sid[0]['id'] ?? 0);

		// suspension table
		$db->do_query("
			UPDATE suspensions
			SET
			decision = 'No'
			WHERE id = '$sid'
		");

		return true;
	}
}
