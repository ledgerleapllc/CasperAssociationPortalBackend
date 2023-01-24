<?php
include_once('../../core.php');
/**
 *
 * PUT /admin/verify-badge-partner
 *
 * HEADER Authorization: Bearer
 *
 * Verify User's badge partner link status
 *
 * @param string  $user_guid
 *
 */
class AdminVerifyBadgePartner extends Endpoints {
	function __construct(
		$user_guid = ''
	) {
		global $db, $helper;

		require_method('PUT');

		$auth      = authenticate_session(2);
		$guid      = $auth['guid'] ?? '';
		$user_guid = parent::$params['user_guid'] ?? '';

		// get email by user guid
		$query = "
			SELECT email
			FROM users
			WHERE guid = '$user_guid'
		";
		$user_email = $db->do_select($query);
		$user_email = $user_email[0]['email'] ?? '';

		if (!$user_email) {
			_exit(
				'error',
				'Invalid user specified in verifying badge partner',
				400,
				'Invalid user specified in verifying badge partner'
			);
		}

		$query = "
			UPDATE users
			SET badge_partner = 1
			WHERE guid        = '$user_guid'
		";
		$done = $db->do_query($query);

		if($done) {
			_exit(
				'success',
				'Badge partner has been verified'
			);
		}

		_exit(
			'error',
			'There was a problem verifying badge partner at this time',
			500,
			'There was a problem verifying badge partner at this time'
		);
	}
}
new AdminVerifyBadgePartner();