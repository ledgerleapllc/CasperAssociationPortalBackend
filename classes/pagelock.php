<?php
/**
 *
 * User page lock controller. For KYC and probation/suspension
 *
 */
class Pagelock {
	function __construct() {
		//
	}

	function __destruct() {
		// do nothing yet
	}

	/**
	 *
	 * Determine if a user is barred from a page due to KYC or probation
	 * Exits with pre defined error codes if a user visits a locked page
	 *
	 * $page = enum('nodes', 'votes', 'discs', 'perks')
	 *
	 * @param  string $guid
	 *
	 */
	public function check(
		$guid = '',
		$page = ''
	) {
		global $db, $helper;

		$current_era_id = $helper->get_current_era_id();

		switch ($page) {
			case 'nodes': $page = 'nodes'; break;
			case 'votes': $page = 'votes'; break;
			case 'discs': $page = 'discs'; break;
			case 'perks': $page = 'perks'; break;
			default:      $page = null;    break;
		}

		if (!$page) {
			return false;
		}

		// fetch settings
		$kyc_lock   = (int)($helper->fetch_setting('kyc_lock_'.$page));
		$prob_lock  = (int)($helper->fetch_setting('prob_lock_'.$page));

		// check KYC
		$kyc_status = $db->do_select("
			SELECT status
			FROM shufti
			WHERE guid = '$guid'
		");
		$kyc_status = $kyc_status[0]['status'] ?? '';

		if (
			$kyc_lock &&
			$kyc_status != 'approved'
		) {
			_exit(
				'error',
				'kyc-lock',
				403,
				"User cannot access $page area due to kyc lock"
			);
		}

		// check nodes for probation
		$prob_status = $db->do_select("
			SELECT b.status
			FROM user_nodes AS a
			JOIN all_node_data AS b
			ON a.public_key = b.public_key
			WHERE a.guid = '$guid'
			AND a.verified IS NOT NULL
			AND b.era_id = $current_era_id
			AND (
				b.status = 'probation' ||
				b.status = 'suspension'
			)
		");
		$prob_status = $prob_status[0]['status'] ?? false;

		if (
			$prob_lock &&
			$prob_status
		) {
			_exit(
				'error',
				'prob-lock',
				403,
				"User cannot access $page area due to node probation status"
			);
		}
	}
}