<?php
/**
 *
 * POST /admin/upload-terms
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param file $doc
 *
 */
class AdminUploadTerms extends Endpoints {
	function __construct(
		$doc = ''
	) {
		global $db, $helper, $S3;

		require_method('POST');

		$auth      = authenticate_session(2);
		$now       = $helper->get_datetime();
		$name      = $_FILES['file']['name'] ?? '';
		$doc       = $_FILES['file']['tmp_name'] ?? '';
		$error     = (string)($_FILES['file']['error'] ?? '');
		$size      = (int)($_FILES['file']['size'] ?? 0);
		$file_ext  = pathinfo($name, PATHINFO_EXTENSION);
		$max_size  = 10000000;
		$unit_test = parent::$params['doc'] ?? '';

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

		$file_name = (
			'terms-'.
			str_replace(':', '-', str_replace(' ', '-', $now)).'.'.
			$file_ext
		);

		if (
			$unit_test == 'doc' &&
			DEV_MODE
		) {
			_exit(
				'success',
				'Successfully uploaded terms of service document (dev test)'
			);
		}

		// set mime type
		if (
			$file_ext == 'jpg' ||
			$file_ext == 'jpeg'
		) {
			$mime_type = 'image/jpeg';
		}

		if ($file_ext == 'png') {
			$mime_type = 'image/png';
		}

		if ($file_ext == 'gif') {
			$mime_type = 'image/gif';
		}

		if ($file_ext == 'pdf') {
			$mime_type = 'application/pdf';
		}

		if (
			$file_ext == 'doc' ||
			$file_ext == 'docx'
		) {
			$mime_type = 'application/msword';
		}

		if ($file_ext == 'txt') {
			$mime_type = 'text/plain';
		}

		$s3result = $S3->putObject([
			'Bucket'      => S3BUCKET,
			'Key'         => 'documents/'.$file_name,
			'SourceFile'  => $doc,
			'ContentType' => $mime_type
		]);

		$ObjectURL = $s3result['ObjectURL'] ?? FRONTEND_URL.'/not-found';

		$helper->apply_setting('esign_doc', $ObjectURL);

		// also save clone for cors pdf embedder
		//// no longer cloning to /public/documents
		/*
		$content = file_get_contents($doc);

		try {
			file_put_contents(
				BASE_DIR.'/public/documents/terms-of-service.'.$file_ext,
				$content
			);
		} catch (Exception $e) {
			elog('Admin denied access to cloning Terms of Service document to the cors dir - public/documents. terms-of-service public link is broken.');
		}
		*/

		_exit(
			'success',
			'Successfully uploaded terms of service document'
		);
	}
}
new AdminUploadTerms();
