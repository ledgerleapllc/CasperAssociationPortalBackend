<?php
/**
 *
 * POST /user/upload-entity-doc
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param file $doc
 *
 */
class UserUploadEntityDoc extends Endpoints {
	function __construct(
		$doc = ''
	) {
		global $db, $helper, $S3;

		require_method('POST');

		$auth      = authenticate_session(1);
		$user_guid = $auth['guid'] ?? '';
		$now       = $helper->get_datetime();

		$name      = $_FILES['file']['name'] ?? '';
		$doc       = $_FILES['file']['tmp_name'] ?? '';
		$error     = (string)($_FILES['file']['error'] ?? '');
		$size      = (int)($_FILES['file']['size'] ?? 0);
		$max_size  = Regex::$letter['char_limit'];
		$file_ext  = pathinfo($name, PATHINFO_EXTENSION);
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
				'Document is too large. Please limit to '.$max_size.' bytes',
				400,
				'Document is too large. Please limit to '.$max_size.' bytes'
			);
		}

		if (
			$unit_test == 'CobraKai' &&
			DEV_MODE
		) {
			$ObjectURL = FRONTEND_URL.'/not-found';
		} else {
			$file_name = 'entity-doc-'.$helper->generate_hash().'.'.$file_ext;

			$s3result = $S3->putObject([
				'Bucket'     => S3BUCKET,
				'Key'        => 'documents/'.$file_name,
				'SourceFile' => $doc
			]);

			$ObjectURL = $s3result['ObjectURL'] ?? FRONTEND_URL.'/not-found';
		}

		$filtered_name = filter($name);

		$success = $db->do_query("
			INSERT INTO entity_docs (
				user_guid,
				file_name,
				file_url,
				created_at
			) VALUES (
				'$user_guid',
				'$filtered_name',
				'$ObjectURL',
				'$now'
			)
		");

		if (!$success) {
			_exit(
				'error',
				'There was a problem receiving your letter, please try again',
				400,
				'There was a problem receiving your letter, please try again'
			);
		}

		_exit(
			'success',
			'Entity document uploaded'
		);
	}
}
new UserUploadEntityDoc();
