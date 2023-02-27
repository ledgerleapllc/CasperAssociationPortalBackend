<?php
/**
 *
 * POST /admin/upload-ballot-file
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param file $file
 *
 */
class AdminUploadBallotFile extends Endpoints {
	function __construct(
		$file = ''
	) {
		global $db, $helper, $S3;

		require_method('POST');

		$auth       = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';
		$now        = $helper->get_datetime();

		$name       = $_FILES['file']['name'] ?? '';
		$file       = $_FILES['file']['tmp_name'] ?? '';
		$error      = (string)($_FILES['file']['error'] ?? '');
		$size       = (int)($_FILES['file']['size'] ?? 0);
		$file_ext   = strtolower(pathinfo($name, PATHINFO_EXTENSION));
		$max_size   = 4194304; // 22 bits
		$unit_test  = parent::$params['file'] ?? '';

		// handle errors
		if ($error && $error != '0') {
			_exit(
				'error',
				$error,
				400,
				$error
			);
		}

		if ($size >= $max_size) {
			_exit(
				'error',
				'File is too large. Please limit to '.$max_size.' bytes',
				400,
				'File is too large. Please limit to '.$max_size.' bytes'
			);
		}

		if (
			$unit_test == 'file' &&
			DEV_MODE
		) {
			_exit(
				'success',
				'https://ledgerleap.com/assets/images/favicon.png'
			);
		}

		// file types
		$accepted_file_types = array('pdf', 'png', 'jpg', 'jpeg');

		if (!in_array($file_ext, $accepted_file_types)) {
			_exit(
				'error',
				'Invalid file extension. Accepted file types are PDF, JPG, JPEG, PNG',
				400,
				'Invalid ballot file extension'
			);
		}

		// draft file S3 hash
		$file_name = 'ballot-'.$helper->generate_hash(32).'.'.$file_ext;

		$s3result  = $S3->putObject([
			'Bucket'     => S3BUCKET,
			'Key'        => 'perks/'.$file_name,
			'SourceFile' => $file
		]);

		$ObjectURL = $s3result['ObjectURL'] ?? null;

		if ($ObjectURL) {
			_exit(
				'success',
				array(
					"file_url"  => $ObjectURL,
					"file_name" => $name
				)
			);
		}

		_exit(
			'error',
			'There was a problem uploading ballot file at this time. Please contact your admin',
			500,
			'There was a problem uploading ballot file at this time. Please contact your admin'
		);
	}
}
new AdminUploadBallotFile();
