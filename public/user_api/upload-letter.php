<?php
/**
 *
 * POST /user/upload-letter
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param file $letter
 *
 */
class UserUploadLetter extends Endpoints {
	function __construct(
		$letter = ''
	) {
		global $db, $helper, $S3;

		require_method('POST');

		$auth      = authenticate_session(1);
		$user_guid = $auth['guid'] ?? '';
		$now       = $helper->get_datetime();

		$name      = $_FILES['file']['name'] ?? '';
		$letter    = $_FILES['file']['tmp_name'] ?? '';
		$error     = (string)($_FILES['file']['error'] ?? '');
		$size      = (int)($_FILES['file']['size'] ?? 0);
		$max_size  = Regex::$letter['char_limit'];
		$file_ext  = pathinfo($name, PATHINFO_EXTENSION);
		$unit_test = parent::$params['letter'] ?? '';

		if ($error && $error != '0') {
			_exit(
				'error',
				$error,
				400,
				$error
			);
		}

		if ($size > $max_size) {
			_exit(
				'error',
				'Letter file is too large. Please limit to '.$max_size.' bytes',
				400,
				'Letter file is too large. Please limit to '.$max_size.' bytes'
			);
		}

		if (
			$unit_test == 'hey its me again' &&
			DEV_MODE
		) {
			$ObjectURL = FRONTEND_URL.'/not-found';
		} else {
			$content   = filter(file_get_contents($letter));
			$file_name = 'letter-'.$user_guid.'.'.$file_ext;

			$s3result = $S3->putObject([
				'Bucket'     => S3BUCKET,
				'Key'        => 'letters_of_motivation/'.$file_name,
				'SourceFile' => $letter
			]);

			$ObjectURL = $s3result['ObjectURL'] ?? FRONTEND_URL.'/not-found';
		}

		$success = $db->do_query("
			UPDATE users
			SET    letter = '$ObjectURL'
			WHERE  guid   = '$user_guid'
		");

		if (!$success) {
			_exit(
				'error',
				'There was an problem receiving your letter, please try again',
				400,
				'There was an problem receiving your letter, please try again'
			);
		}

		// email for user, as per global settings
		$enabled = (bool)$helper->fetch_setting('enabled_letter_received');

		if ($enabled) {
			$subject = 'You have completed all onboarding steps';
			$body    = $helper->fetch_setting('email_letter_received');

			if($body) {
				$helper->schedule_email(
					'user-alert',
					$auth['email'],
					$subject,
					$body
				);
			}
		}

		// email for admin, as per global settings
		$enabled = (bool)$helper->fetch_setting('enabled_letter_uploaded');

		if ($enabled) {
			$subject      = 'User has uploaded a letter for review';
			$body         = $helper->fetch_setting('email_letter_uploaded');
			$admin_emails = $helper->get_emailer_admins();

			if($body) {
				foreach ($admin_emails as $admin_email) {
					$helper->schedule_email(
						'admin-alert',
						$admin_email,
						$subject,
						$body
					);
				}
			}
		}

		_exit(
			'success',
			$name
		);
	}
}
new UserUploadLetter();
