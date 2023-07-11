<?php
/**
 *
 * POST /user/upload-signature
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param file $signature
 *
 */
class UserUploadSignature extends Endpoints {
	function __construct(
		$signature = ''
	) {
		global $db, $helper, $casper_sig, $S3;

		require_method('POST');

		$auth      = authenticate_session(1);
		$user_guid = $auth['guid'] ?? '';
		$now       = $helper->get_datetime();

		$name      = $_FILES['file']['name'] ?? '';
		$signature = $_FILES['file']['tmp_name'] ?? '';
		$error     = (string)($_FILES['file']['error'] ?? '');
		$size      = (int)($_FILES['file']['size'] ?? 0);

		if ($error && $error != '0') {
			_exit(
				'error',
				$error,
				400,
				$error
			);
		}

		if ($size > 256) {
			_exit(
				'error',
				'Signature file is too large',
				400,
				'Signature file is too large'
			);
		}

		$hash = 'abc123';

		if ($signature) {
			$hash = file_get_contents($signature);
		}

		if (!ctype_xdigit($hash)) {
			_exit(
				'error',
				'Signature file is invalid',
				400,
				'Signature file is invalid'
			);
		}

		$sig_message = $db->do_select("
			SELECT sig_message
			FROM   users
			WHERE  guid = '$user_guid'
		");

		$sig_message  = $sig_message[0]['sig_message'] ?? '';
		$validator_id = '';

		$validator_ids = $db->do_select("
			SELECT public_key
			FROM   user_nodes
			WHERE  guid     = '$user_guid'
			AND    verified IS NULL
		");

		$validator_ids = $validator_ids ?? array();
		$VERIFIED      = false;

		foreach ($validator_ids as $v) {
			$validator_id = $v['public_key'];

			try {
				$VERIFIED = $casper_sig->verify(
					$hash,
					$validator_id,
					$sig_message
				);

				if ($VERIFIED) {
					break;
				}
			} catch (\Exception $e) {
				$VERIFIED = false;
			}
		}

		if (DEV_MODE) {
			$VERIFIED = true;
		}

		if (!$VERIFIED) {
			_exit(
				'error',
				'Signature file failed to verify',
				400,
				'Signature file failed to verify'
			);
		}

		// verified
		$db->do_query("
			UPDATE user_nodes
			SET    verified   = '$now'
			WHERE  guid       = '$user_guid'
			AND    public_key = '$validator_id'
		");

		// upload hash to S3
		$file_name = 'sig-'.$user_guid.'-'.$validator_id;

		if ($signature) {
			$s3result = $S3->putObject([
				'Bucket'     => S3BUCKET,
				'Key'        => 'signatures/'.$file_name,
				'SourceFile' => $signature
			]);
		}

		// email for user, as per global settings
		$enabled = (bool)$helper->fetch_setting('enabled_node_verified');

		if ($enabled) {
			$subject = 'Your node has been verified';
			$body = $helper->fetch_setting('email_node_verified');

			if($body) {
				$helper->schedule_email(
					'user-alert',
					$auth['email'],
					$subject,
					$body
				);
			}
		}

		_exit(
			'success',
			$name
		);
	}
}
new UserUploadSignature();
