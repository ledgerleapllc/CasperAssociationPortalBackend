<?php
include_once('../../core.php');
/**
 *
 * POST /admin/update-avatar
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param file $avatar
 *
 */
class AdminUpdateAvatar extends Endpoints {
	function __construct(
		$avatar = ''
	) {
		global $db, $helper, $S3;

		require_method('POST');

		$auth          = authenticate_session(2);
		$user_guid     = $auth['guid'] ?? '';
		$avatar        = $_FILES['avatar'] ?? null;
		$type          = $avatar['type'] ?? '';
		$name          = $avatar['name'] ?? '';
		$tmp_name      = $avatar['tmp_name'] ?? '';
		$ext           = pathinfo($name, PATHINFO_EXTENSION);
		$ext           = strtolower($ext);
		$error         = $avatar['error'] ?? '';
		$size          = $avatar['size'] ?? '';
		$one_month_ago = $helper->get_datetime(-2629800);
		$max_size      = 1024000;

		if ($size > $max_size) {
			_exit(
				'error', 
				'Avatar image too large. Cannot exceed 1 MB', 
				413, 
				'Avatar image too large. Cannot exceed 1 MB'
			);
		}

		if (
			$type != 'image/png' &&
			$type != 'image/jpg' &&
			$type != 'image/svg+xml'
		) {
			_exit(
				'error', 
				'Invalid avatar image type. Please use one of *.png, *.jpg, *.jpeg, *.svg', 
				415, 
				'Invalid avatar image type. Please use one of *.png, *.jpg, *.jpeg, *.svg'
			);
		}

		if ($name && $tmp_name) {
			$hash_name = $helper->generate_guid().'.'.$ext;

			try {
				$s3result = $S3->putObject([
					'Bucket'     => S3BUCKET,
					'Key'        => 'avatars/'.$hash_name,
					'SourceFile' => $tmp_name
				]);

				$ObjectURL = 'https://'.S3BUCKET.'.s3.'.S3BUCKET_REGION.'.amazonaws.com/avatars/'.$hash_name;
			} catch (Exception $e) {
				elog($e);
				$ObjectURL = null;
			}

			if ($ObjectURL) {
				$query = "
					UPDATE users
					SET avatar_url = '$ObjectURL'
					WHERE guid     = '$user_guid'
				";
				$db->do_query($query);

				// also track limited avatar uploads a user can perfrom in a given month
				$updated_at = $helper->get_datetime();
				$query = "
					INSERT INTO avatar_changes (
						guid,
						updated_at
					) VALUES (
						'$user_guid',
						'$updated_at'
					)
				";
				$db->do_query($query);

				_exit(
					'success',
					'Avatar image updated!'
				);
			}
		}

		_exit(
			'error', 
			'There was a problem updating your avatar at this time. Please try again later', 
			400, 
			'There was a problem updating admins avatar'
		);
	}
}
new AdminUpdateAvatar();