<?php
/**
 *
 * POST /admin/invite-sub-admin
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param string $email
 *
 */
class AdminInviteSubAdmin extends Endpoints {
	function __construct(
		$email = ''
	) {
		global $db, $helper, $permissions;

		require_method('POST');

		$auth       = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';
		$email      = (string)(parent::$params['email'] ?? '');

		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			_exit(
				'error', 
				'Invalid email address', 
				400, 
				'Invalid email address'
			);
		}

		// invited check
		$already_invited = $db->do_select("
			SELECT *
			FROM team_invites
			WHERE email = '$email'
		");

		if ($already_invited) {
			_exit(
				'error',
				'Member has already been invited',
				400,
				'Member has already been invited'
			);
		}

		// user check
		$user = $db->do_select("
			SELECT guid, email, role
			FROM users
			WHERE email = '$email'
		");

		$user = $user[0] ?? null;

		if ($user) {
			if ($user['role'] != 'user' && $user['role'] != 'test-user') {
				_exit(
					'error',
					'Member already holds a special role in the Casper Association',
					400,
					'Member already holds a special role in the Casper Association'
				);
			}
		}

		$created_at        = $helper->get_datetime();
		$confirmation_code = $helper->generate_hash(6);
		$new_guid          = $helper->generate_guid();

		$db->do_query("
			INSERT INTO team_invites (
				guid,
				email,
				created_at,
				confirmation_code
			) VALUES (
				'$new_guid',
				'$email',
				'$created_at',
				'$confirmation_code'
			)
		");

		$permissions->allowed($new_guid);

		$uri = $helper->aes_encrypt(
			$new_guid.'::'.
			$email.'::'.
			$confirmation_code.'::'.
			(string)time()
		);

		$subject = 'Casper Association Adminstrative Invitation';
		$body = 'You have been invited to join the Casper Association Portal as an administrative team member. Follow the link to your portal below to accept the invitation.';
		$link = PROTOCOL.'://'.getenv('FRONTEND_URL').'/accept-team-invite/'.$uri.'?email='.$email;

		$helper->schedule_email(
			'invitation',
			$email,
			$subject,
			$body,
			$link
		);

		_exit(
			'success',
			'Member invited to join administrative team'
		);
	}
}
new AdminInviteSubAdmin();