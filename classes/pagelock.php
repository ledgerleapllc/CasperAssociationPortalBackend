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
	 * @param  string $page
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
		$kyc_lock  = (int)($helper->fetch_setting('kyc_lock_'.$page));
		$prob_lock = (int)($helper->fetch_setting('prob_lock_'.$page));

		// check KYC
		$kyc_status = $db->do_select("
			SELECT status
			FROM  shufti
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
			FROM user_nodes    AS a
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

	/**
	 *
	 * Pre-analize and return a user's page lock status so we can display lock icons on frontend
	 *
	 * @param  string $guid
	 *
	 */
	public function analyze($guid) {
		global $db, $helper;

		$current_era_id = $helper->get_current_era_id();

		$page_locks = array(
			"nodes" => false,
			"votes" => false,
			"discs" => false,
			"perks" => false
		);

		// fetch settings
		$kyc_lock_nodes  = (bool)(int)($helper->fetch_setting('kyc_lock_nodes'));
		$prob_lock_nodes = (bool)(int)($helper->fetch_setting('prob_lock_nodes'));

		$kyc_lock_votes  = (bool)(int)($helper->fetch_setting('kyc_lock_votes'));
		$prob_lock_votes = (bool)(int)($helper->fetch_setting('prob_lock_votes'));

		$kyc_lock_discs  = (bool)(int)($helper->fetch_setting('kyc_lock_discs'));
		$prob_lock_discs = (bool)(int)($helper->fetch_setting('prob_lock_discs'));

		$kyc_lock_perks  = (bool)(int)($helper->fetch_setting('kyc_lock_perks'));
		$prob_lock_perks = (bool)(int)($helper->fetch_setting('prob_lock_perks'));

		// check KYC
		$kyc_status = $db->do_select("
			SELECT status
			FROM  shufti
			WHERE guid = '$guid'
		");
		$kyc_status = $kyc_status[0]['status'] ?? '';

		// check nodes for probation
		$prob_status = $db->do_select("
			SELECT b.status
			FROM user_nodes    AS a
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

		// based on kyc
		if ($kyc_status != 'approved') {
			$page_locks["nodes"] = $kyc_lock_nodes ? 'kyc-lock' : false;
			$page_locks["votes"] = $kyc_lock_votes ? 'kyc-lock' : false;
			$page_locks["discs"] = $kyc_lock_discs ? 'kyc-lock' : false;
			$page_locks["perks"] = $kyc_lock_perks ? 'kyc-lock' : false;
		}

		// based on node probation
		if ($prob_status) {
			$page_locks["nodes"] = $page_locks["nodes"] || ($prob_lock_nodes ? 'prob-lock' : false);
			$page_locks["votes"] = $page_locks["votes"] || ($prob_lock_votes ? 'prob-lock' : false);
			$page_locks["discs"] = $page_locks["discs"] || ($prob_lock_discs ? 'prob-lock' : false);
			$page_locks["perks"] = $page_locks["perks"] || ($prob_lock_perks ? 'prob-lock' : false);
		}

		return $page_locks;
	}
}