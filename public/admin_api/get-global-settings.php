<?php
/**
 *
 * GET /admin/get-global-settings
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class AdminGetGlobalSettings extends Endpoints {
	function __construct() {
		global $db, $helper;

		require_method('GET');

		$auth = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';

		// uptime
		$uptime_warning           = $helper->fetch_setting('uptime_warning');
		$uptime_probation         = $helper->fetch_setting('uptime_probation');
		$uptime_correction_units  = $helper->fetch_setting('uptime_correction_units');
		$uptime_correction_metric = $helper->fetch_setting('uptime_correction_metric');

		// voting lock
		$eras_required_to_vote = $helper->fetch_setting('eras_required_to_vote');
		$eras_since_redmark    = $helper->fetch_setting('eras_since_redmark');

		// redmarks
		$redmark_revoke     = $helper->fetch_setting('redmark_revoke');
		$redmark_calc_size  = $helper->fetch_setting('redmark_calc_size');

		// page lock rules
		$kyc_lock_nodes  = $helper->fetch_setting('kyc_lock_nodes');
		$kyc_lock_discs  = $helper->fetch_setting('kyc_lock_discs');
		$kyc_lock_votes  = $helper->fetch_setting('kyc_lock_votes');
		$kyc_lock_perks  = $helper->fetch_setting('kyc_lock_perks');
		$prob_lock_nodes = $helper->fetch_setting('prob_lock_nodes');
		$prob_lock_discs = $helper->fetch_setting('prob_lock_discs');
		$prob_lock_votes = $helper->fetch_setting('prob_lock_votes');
		$prob_lock_perks = $helper->fetch_setting('prob_lock_perks');

		// terms
		$esign_doc       = $helper->fetch_setting('esign_doc');

		_exit(
			'success',
			array(
				'uptime_warning'           => $uptime_warning,
				'uptime_probation'         => $uptime_probation,
				'uptime_correction_units'  => $uptime_correction_units,
				'uptime_correction_metric' => $uptime_correction_metric,

				'eras_required_to_vote'    => $eras_required_to_vote,
				'eras_since_redmark'       => $eras_since_redmark,

				'redmark_revoke'           => $redmark_revoke,
				'redmark_calc_size'        => $redmark_calc_size,

				'kyc_lock_nodes'           => $kyc_lock_nodes,
				'kyc_lock_discs'           => $kyc_lock_discs,
				'kyc_lock_votes'           => $kyc_lock_votes,
				'kyc_lock_perks'           => $kyc_lock_perks,
				'prob_lock_nodes'          => $prob_lock_nodes,
				'prob_lock_discs'          => $prob_lock_discs,
				'prob_lock_votes'          => $prob_lock_votes,
				'prob_lock_perks'          => $prob_lock_perks,

				'esign_doc'                => $esign_doc
			)
		);
	}
}
new AdminGetGlobalSettings();
