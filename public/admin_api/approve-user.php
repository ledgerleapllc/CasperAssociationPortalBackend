<?php
include_once('../../core.php');
/**
 *
 * POST /admin/approve-user
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param string  $guid
 *
 */
class AdminApproveUser extends Endpoints {
	function __construct(
		$guid = ''
	) {
		global $db, $helper;

		require_method('POST');

		$auth       = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';
		$user_guid  = (string)(parent::$params['guid'] ?? '');

		$db->do_query("
			UPDATE users
			SET admin_approved = 1
			WHERE guid = '$user_guid'
			AND role LIKE '%user'
		");

		// email for user, as per global settings
		$enabled = (bool)$helper->fetch_setting('enabled_letter_approved');

		if ($enabled) {
			$subject = 'Your letter of motivation has been approved';
			$body = $helper->fetch_setting('email_letter_approved');

			$user_email = $db->do_select("
				SELECT email
				FROM users
				WHERE guid = '$user_guid'
			");
			$user_email = $user_email[0]['email'] ?? '';

			if($body && $user_email) {
				$helper->schedule_email(
					'user-alert',
					$user_email,
					$subject,
					$body
				);
			}
		}

		_exit(
			'success',
			'User approved by association'
		);
	}
}
new AdminApproveUser();