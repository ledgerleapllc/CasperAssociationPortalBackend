<?php
include_once('../../core.php');
/**
 *
 * POST /user/update-avatar
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param file $avatar
 *
 */
class UserUpdateAvatar extends Endpoints {
	function __construct(
		$avatar = ''
	) {
		global $db, $helper, $S3;

		require_method('POST');

		$auth          = authenticate_session(1);
		$user_guid     = $auth['guid'] ?? '';
		$avatar        = $_FILES['avatar'] ?? null;
		$type          = $avatar['type'] ?? '';
		$name          = $avatar['name'] ?? '';
		$tmp_name      = $avatar['tmp_name'] ?? '';
		$ext           = pathinfo($name, PATHINFO_EXTENSION);
		$ext           = strtolower($ext);
		$error         = $avatar['error'] ?? '';
		$size          = (float)($avatar['size'] ?? 0);
		$one_month_ago = $helper->get_datetime(-2629800);
		$max_size      = 1024000;
		$max_attempts  = 5;
		$unit_test     = parent::$params['avatar'] ?? '';

		// do spam check first
		$query = "
			SELECT count(guid)
			FROM avatar_changes
			WHERE guid = '$user_guid'
			AND updated_at > '$one_month_ago'
		";
		$check = $db->do_select($query);
		$check = (int)($check[0]['count(guid)'] ?? 0);

		if ($check >= $max_attempts) {
			_exit(
				'error', 
				'You can only change your avatar image '.$max_attempts.' times per month.', 
				429, 
				'You can only change your avatar image '.$max_attempts.' times per month.'
			);
		}

		if ($size > $max_size) {
			_exit(
				'error', 
				'Avatar image too large. Cannot exceed 1 MB', 
				413, 
				'Avatar image too large. Cannot exceed 1 MB'
			);
		}

		if (
			$unit_test == 'avatar' &&
			DEV_MODE
		) {
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
					'Bucket' => S3BUCKET,
					'Key' => 'avatars/'.$hash_name,
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
					WHERE guid = '$user_guid'
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
			'There was a problem updating your avatar at this time. Please try again later'
		);
	}
}
new UserUpdateAvatar();