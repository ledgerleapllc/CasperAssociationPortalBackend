<?php
/**
 *
 * POST /public/contact-us
 *
 * @api
 * @param string $name
 * @param string $email
 * @param string $message
 *
 */
class PublicContactUs extends Endpoints {
	function __construct(
		$name    = '',
		$email   = '',
		$message = ''
	) {
		global $db, $helper;

		require_method('POST');

		$name    = parent::$params['name'] ?? '';
		$email   = parent::$params['email'] ?? '';
		$message = (string)(parent::$params['message'] ?? '');

		if(strlen($name) > 255) {
			_exit(
				'error',
				'Name too long. Please limit to 255 characters',
				400,
				'Name too long. Please limit to 255 characters'
			);
		}

		if(strlen($email) > 255) {
			_exit(
				'error',
				'Email too long. Please limit to 255 characters',
				400,
				'Email too long. Please limit to 255 characters'
			);
		}

		if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			_exit(
				'error',
				'Invalid email address',
				400,
				'Invalid email address'
			);
		}

		if(strlen($message) > 2048) {
			_exit(
				'error',
				'Message too long. Please limit to 2048 characters',
				400,
				'Message too long. Please limit to 2048 characters'
			);
		}

		// get contact recipients
		$recipients = $helper->get_contact_recipients();
		$subject    = 'Contact form receipt';
		$body       = 'Name: '.$name.'<br/>';
		$body      .= 'Email: '.$email.'<br/><br/>';
		$body      .= 'Message: '.$message.'<br/><br/>';

		foreach ($recipients as $recipient) {
			$helper->schedule_email(
				'admin-alert',
				$recipient,
				$subject,
				$body
			);
		}

		_exit(
			'success',
			'Contact message sent!'
		);
	}
}
new PublicContactUs();