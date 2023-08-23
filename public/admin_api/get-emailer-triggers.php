<?php
/**
 *
 * GET /admin/get-emailer-triggers
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class AdminGetEmailerTriggers extends Endpoints {
	function __construct() {
		global $helper;

		require_method('GET');

		$auth       = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';

		$email_letter_uploaded  = $helper->fetch_setting('email_letter_uploaded');
		$email_kyc_needs_review = $helper->fetch_setting('email_kyc_needs_review');
		$email_welcome          = $helper->fetch_setting('email_welcome');
		$email_node_verified    = $helper->fetch_setting('email_node_verified');
		$email_letter_received  = $helper->fetch_setting('email_letter_received');
		$email_letter_approved  = $helper->fetch_setting('email_letter_approved');
		$email_letter_denied    = $helper->fetch_setting('email_letter_denied');
		$email_new_perk         = $helper->fetch_setting('email_new_perk');
		$email_vote_started     = $helper->fetch_setting('email_vote_started');
		$email_vote_reminder    = $helper->fetch_setting('email_vote_reminder');
		$email_probation        = $helper->fetch_setting('email_probation');
		$email_revoked          = $helper->fetch_setting('email_revoked');
		$email_warning          = $helper->fetch_setting('email_warning');

		$enabled_letter_uploaded  = (int)$helper->fetch_setting('enabled_letter_uploaded');
		$enabled_kyc_needs_review = (int)$helper->fetch_setting('enabled_kyc_needs_review');
		$enabled_welcome          = (int)$helper->fetch_setting('enabled_welcome');
		$enabled_node_verified    = (int)$helper->fetch_setting('enabled_node_verified');
		$enabled_letter_received  = (int)$helper->fetch_setting('enabled_letter_received');
		$enabled_letter_approved  = (int)$helper->fetch_setting('enabled_letter_approved');
		$enabled_letter_denied    = (int)$helper->fetch_setting('enabled_letter_denied');
		$enabled_new_perk         = (int)$helper->fetch_setting('enabled_new_perk');
		$enabled_vote_started     = (int)$helper->fetch_setting('enabled_vote_started');
		$enabled_vote_reminder    = (int)$helper->fetch_setting('enabled_vote_reminder');
		$enabled_probation        = (int)$helper->fetch_setting('enabled_probation');
		$enabled_revoked          = (int)$helper->fetch_setting('enabled_revoked');
		$enabled_warning          = (int)$helper->fetch_setting('enabled_warning');
		$reinstatement_contact    = $helper->fetch_setting('reinstatement_contact');

		$triggers = array(
			"email_letter_uploaded"    => $email_letter_uploaded,
			"email_kyc_needs_review"   => $email_kyc_needs_review,
			"email_welcome"            => $email_welcome,
			"email_node_verified"      => $email_node_verified,
			"email_letter_received"    => $email_letter_received,
			"email_letter_approved"    => $email_letter_approved,
			"email_letter_denied"      => $email_letter_denied,
			"email_new_perk"           => $email_new_perk,
			"email_vote_started"       => $email_vote_started,
			"email_vote_reminder"      => $email_vote_reminder,
			"email_probation"          => $email_probation,
			"email_revoked"            => $email_revoked,
			"email_warning"            => $email_warning,

			"enabled_letter_uploaded"  => $enabled_letter_uploaded,
			"enabled_kyc_needs_review" => $enabled_kyc_needs_review,
			"enabled_welcome"          => $enabled_welcome,
			"enabled_node_verified"    => $enabled_node_verified,
			"enabled_letter_received"  => $enabled_letter_received,
			"enabled_letter_approved"  => $enabled_letter_approved,
			"enabled_letter_denied"    => $enabled_letter_denied,
			"enabled_new_perk"         => $enabled_new_perk,
			"enabled_vote_started"     => $enabled_vote_started,
			"enabled_vote_reminder"    => $enabled_vote_reminder,
			"enabled_probation"        => $enabled_probation,
			"enabled_revoked"          => $enabled_revoked,
			"enabled_warning"          => $enabled_warning,

			"reinstatement_contact"    => $reinstatement_contact
		);

		// elog($triggers);

		_exit(
			'success',
			$triggers
		);
	}
}
new AdminGetEmailerTriggers();
