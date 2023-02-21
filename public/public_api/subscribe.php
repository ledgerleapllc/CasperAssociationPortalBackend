<?php
include_once('../../core.php');
/**
 *
 * POST /public/subscribe
 *
 * @api
 * @param string $email
 *
 */
class PublicContactUs extends Endpoints {
	function __construct(
		$email = ''
	) {
		global $db, $helper;

		require_method('POST');

		$email = parent::$params['email'] ?? '';
		$now   = $helper->get_datetime();

		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			_exit(
				'error',
				'Invalid email address',
				400,
				'Invalid email address'
			);
		}

		// check
		$check = $db->do_select("
			SELECT email
			FROM subscriptions
			WHERE email = '$email'
		");

		if (!$check) {
			// check for a guid associating a subscriber to a user
			$guid = $db->do_select("
				SELECT guid
				FROM users
				WHERE email = '$email'
			");

			$guid = $guid[0]['guid'] ?? '';

			$db->do_query("
				INSERT INTO subscriptions (
					guid,
					email,
					created_at,
					source
				) VALUES (
					'$guid',
					'$email',
					'$now',
					'members.casper.network'
				)
			");
		}

		_exit(
			'success',
			'Successfully subscribed'
		);
	}
}
new PublicContactUs();