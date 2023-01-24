<?php
include_once('../../core.php');
/**
 *
 * POST /admin/upload-perk-image
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param file $image
 *
 */
class AdminUploadPerkImage extends Endpoints {
	function __construct(
		$image = ''
	) {
		global $db, $helper, $S3;

		require_method('POST');

		$auth       = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';
		$now        = $helper->get_datetime();

		$name       = $_FILES['file']['name'] ?? '';
		$image      = $_FILES['file']['tmp_name'] ?? '';
		$error      = (string)($_FILES['file']['error'] ?? '');
		$size       = (int)($_FILES['file']['size'] ?? 0);
		$file_ext   = strtolower(pathinfo($name, PATHINFO_EXTENSION));
		$max_size   = 2097152; // 22 bits

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
				'Image file is too large. Please limit to '.$max_size.' bytes',
				400,
				'Image file is too large. Please limit to '.$max_size.' bytes'
			);
		}

		// file types
		$accepted_file_types = array('png', 'jpg', 'jpeg', 'gif');

		if (!in_array($file_ext, $accepted_file_types)) {
			_exit(
				'error',
				'Invalid perk image extension. Accepted file types are JPG, JPEG, PNG, GIF',
				400,
				'Invalid perk image extension'
			);
		}

		// crop
		switch ($file_ext) {
			case 'png':  $img = imagecreatefrompng($image);  break;
			case 'jpg':  $img = imagecreatefromjpeg($image); break;
			case 'jpeg': $img = imagecreatefromjpeg($image); break;
			case 'gif':  $img = imagecreatefromgif($image);  break;
			default:     $img = imagecreatefromjpeg($image); break;
		}

		$width  = imagesx($img);
		$height = imagesy($img);

		if ($width > $height) {
			$width = $height;
		} elseif ($width < $height) {
			$height = $width;
		}

		$thumb = imagecreatetruecolor($width, $height);

		imagecopyresampled(
			$thumb, 
			$img, 
			0, 
			0, 
			0, 
			0, 
			$width, 
			$height, 
			$width, 
			$height
		);

		// draft file S3 hash
		$file_name = 'perk-'.$helper->generate_hash(32).'.'.$file_ext;
		imagejpeg($thumb, $image);

		$s3result  = $S3->putObject([
			'Bucket'     => S3BUCKET,
			'Key'        => 'perks/'.$file_name,
			'SourceFile' => $image
		]);

		$ObjectURL = $s3result['ObjectURL'] ?? null;

		if ($ObjectURL) {
			_exit(
				'success',
				$ObjectURL
			);
		}

		_exit(
			'error',
			'There was a problem uploading perk image at this time. Please contact your admin',
			500,
			'There was a problem uploading perk image at this time. Please contact your admin'
		);
	}
}
new AdminUploadPerkImage();