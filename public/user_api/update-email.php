<?php
/**
 *
 * PUT /user/update-email
 *
 * HEADER Authorization: Bearer
 *
 * Requires MFA code to be sent and confirmed prior to requesting this endpoint.
 * After confirming MFA, user will have 5 minutes to submit request.
 *
 * @api
 * @param string  $new_email
 *
 */
class UserUpdateEmail extends Endpoints {
	function __construct(
		$new_email = ''
	) {
		global $db, $helper;

		require_method('PUT');

		$auth = authenticate_session();
		$guid = $auth['guid'] ?? '';
		$new_email = parent::$params['new_email'] ?? '';

		if(!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
			_exit(
				'error',
				'Invalid email address',
				400,
				'Invalid email address'
			);
		}

		// check new email
		$check_query = "
			SELECT email
			FROM users
			WHERE email = '$new_email'
		";
		$check = $db->do_select($check_query);

		if($check) {
			_exit(
				'error',
				'New email address specified is already in use',
				400,
				'New email address specified is already in use'
			);
		}

		// also check new email in email_change table
		$check_query = "
			SELECT guid
			FROM email_changes
			WHERE new_email = '$new_email'
			AND dead = 0
		";
		$check = $db->do_select($check_query);

		if($check) {
			$fetched_guid = $check[0]['guid'] ?? '';

			if($fetched_guid == $guid) {
				// pass. let system overwrite current process
			} else {
				_exit(
					'error',
					'New email address specified is already in use',
					400,
					'New email address specified is already in use'
				);
			}
		}

		// check mfa allowance
		$mfa_response = $helper->consume_mfa_allowance($guid);

		if(!$mfa_response) {
			_exit(
				'error',
				'Requires MFA confirmation first',
				403,
				'Requires MFA confirmation first'
			);
		}

		// Insert new email_change request. To be confirmed with second mfa_code
		$query = "
			UPDATE email_changes
			SET dead = 1
			WHERE guid = '$guid'
		";
		$db->do_query($query);
		$new_mfa_code = $helper->generate_hash(6);
		$created_at   = $helper->get_datetime();

		$query = "
			INSERT INTO email_changes (
				guid,
				new_email,
				code,
				created_at
			) VALUES (
				'$guid',
				'$new_email',
				'$new_mfa_code',
				'$created_at'
			)
		";
		$ready = $db->do_query($query);

		if($ready) {
			$helper->schedule_email(
				'twofa',
				$new_email,
				APP_NAME.' - Confirm New Email',
				'Please find your confirmation code below to verify your new email address. This code expires in 10 minutes.',
				$new_mfa_code
			);

			_exit(
				'success',
				'Please check your new email address for a confirmation code'
			);
		}

		_exit(
			'error',
			'There was a problem submitting your change email request',
			500,
			'There was a problem submitting your change email request'
		);
	}
}
new UserUpdateEmail();