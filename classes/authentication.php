<?php
/**
 * Authentication class for issuing/handling sessions upon successful login.
 *
 * @method string issue_session()
 * @method bool   clear_session()
 * @method bool   extend_session()
 *
 */

class Authentication {
	function __construct() {
		// do nothing yet
	}

	function __destruct() {
		// do nothing yet
	}

	public function issue_session($guid) {
		global $db, $helper;

		$db->do_query("
			DELETE FROM sessions
			WHERE guid = '$guid'
		");

		$created_at = $helper->get_datetime();
		$expires_at = $helper->get_datetime(86400);     // one day
		$limit_at   = $helper->get_datetime(86400 * 7); // one week
		$bearer     = $helper->generate_session_token();

		$db->do_query("
			INSERT INTO sessions (
				guid,
				bearer,
				created_at,
				expires_at,
				limit_at
			) VALUES (
				'$guid',
				'$bearer',
				'$created_at',
				'$expires_at',
				'$limit_at'
			)
		");

		return $bearer;
	}

	public function clear_session($guid) {
		global $db;

		$result = $db->do_query("
			DELETE FROM sessions
			WHERE guid = '$guid'
		");

		if ($result) {
			return true;
		} else {
			return false;
		}
	}

	public function extend_session($guid) {
		global $db, $helper;

		$sesh = $db->do_select("
			SELECT
			created_at,
			expires_at,
			limit_at
			FROM sessions
			WHERE guid = '$guid'
		");

		$sesh = $sesh[0] ?? null;

		if ($sesh) {
			$created_at = $sesh['created_at'] ?? '';
			$expires_at = $sesh['expires_at'] ?? '';
			$limit_at   = $sesh['limit_at']   ?? '';
			$new_exp    = $helper->get_datetime(86400); // 24 hr from now

			if ($new_exp < $limit_at) {
				$result = $db->do_query("
					UPDATE sessions
					SET   expires_at = '$new_exp'
					WHERE guid       = '$guid'
				");

				if ($result) {
					return true;
				}
			}
		}

		return false;
	}
}