<?php
include_once('../../core.php');
/**
 *
 * POST /admin/confirm-update-email
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param string  $mfa_code
 *
 */
class AdminConfirmUpdateEmail extends Endpoints {
	function __construct(
		$mfa_code = ''
	) {
		global $db, $helper;

		require_method('POST');

		$auth       = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';
		$mfa_code   = parent::$params['mfa_code'] ?? '';

		// fetch email_change record
		$query = "
			SELECT *
			FROM email_changes
			WHERE guid  = '$admin_guid'
			AND code    = '$mfa_code'
			AND success = 0
			AND dead    = 0
		";
		$selection = $db->do_select($query);
		$selection = $selection[0] ?? null;
		$new_email = $selection['new_email'] ?? null;

		if($selection && $new_email) {
			// Check timestamp
			$expire_time = $helper->get_datetime(-600); // 10 minutes ago
			$then_time   = $selection['created_at'] ?? '';

			if($expire_time > $then_time) {
				// Disable if expired
				$query = "
					UPDATE email_changes
					SET dead   = 1
					WHERE guid = '$admin_guid'
				";
				$db->do_query($query);

				_exit(
					'error',
					'Email change request has expired. Please try again',
					403,
					'Email change request has expired. Please try again'
				);
			}

			// Mark email change request as success
			$query = "
				UPDATE email_changes
				SET success = 1
				WHERE guid  = '$admin_guid'
				AND code    = '$mfa_code'
			";
			$db->do_query($query);

			// Sweep disable all email change requests
			$query = "
				UPDATE email_changes
				SET dead   = 1
				WHERE guid = '$admin_guid'
			";
			$db->do_query($query);

			// Finally change user's email address
			$query = "
				UPDATE users
				SET email  = '$new_email'
				WHERE guid = '$admin_guid'
			";
			$db->do_query($query);

			_exit(
				'success',
				'Successfully changed your email address'
			);
		}

		_exit(
			'error',
			'Invalid email change request confirmation code',
			403,
			'Invalid email change request confirmation code'
		);
	}
}
new AdminConfirmUpdateEmail();