<?php
/**
 *
 * POST /admin/save-perk
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param int    $id
 * @param string $title
 * @param string $content
 * @param string $cta
 * @param string $image
 * @param string $start_time
 * @param string $end_time
 * @param string $status
 * @param bool   $visible
 * @param bool   $setting
 *
 */
class AdminSavePerk extends Endpoints {
	function __construct(
		$id           = 0,
		$title        = '',
		$content      = '',
		$cta          = '',
		$image        = '',
		$start_time   = '',
		$end_time     = '',
		$status       = '',
		$visible      = 0,
		$setting      = 0
	) {
		global $db, $helper;

		require_method('POST');

		$auth       = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';

		$perk_id    = (int)(parent::$params['id'] ?? 0);
		$title      = parent::$params['title'] ?? '';
		$content    = parent::$params['content'] ?? '';
		$cta        = parent::$params['cta'] ?? '';
		$image      = parent::$params['image'] ?? '';
		$start_time = parent::$params['start_time'] ?? '';
		$end_time   = parent::$params['end_time'] ?? '';
		$status     = parent::$params['status'] ?? '';
		$visible    = (bool)(parent::$params['visible'] ?? 0);
		$setting    = (bool)(parent::$params['setting'] ?? 0);
		$created_at = $helper->get_datetime();

		if (strlen($title) > 128) {
			_exit(
				'error',
				'Title too long. Limit to 128 characters',
				400,
				'Notification title too long. Limit to 128 characters'
			);
		}

		if (strlen($content) > 4096) {
			_exit(
				'error',
				'Content too long. Limit to 4096 characters',
				400,
				'Notification message too long. Limit to 4096 characters'
			);
		}

		if (
			$status != 'pending' &&
			$status != 'active' &&
			$status != 'expired'
		) {
			$status = 'pending';
		}

		if (!$start_time) {
			$start_time = null;
		}

		if (!$end_time) {
			$end_time = null;
		}

		$helper->sanitize_input(
			$start_time,
			false,
			Regex::$date_extended['char_limit'],
			Regex::$date_extended['char_limit'],
			Regex::$date_extended['pattern'],
			'Activation date'
		);

		$helper->sanitize_input(
			$end_time,
			false,
			Regex::$date_extended['char_limit'],
			Regex::$date_extended['char_limit'],
			Regex::$date_extended['pattern'],
			'Deactivation date'
		);

		if ($start_time && $end_time) {
			if ($start_time > $end_time) {
				_exit(
					'error',
					'Perk end time cannot be before the start time of the perk',
					400,
					'Perk end time cannot be before the start time of the perk'
				);
			}

			if ($start_time > $created_at) {
				$status = 'pending';
			} else {
				$status = 'active';
			}
		}

		if (strlen($cta) > 256) {
			_exit(
				'error',
				'Call to action URL too long. Limit to 256 characters',
				400,
				'Notification call to action URL too long. Limit to 256 characters'
			);
		}

		$visible = (int)$visible;
		$setting = (int)$setting;

		if ($perk_id == 0) {
			// insert
			$query = "
				INSERT INTO perks (
					creator,
					title,
					content,
					cta,
					image,
					status,
					visible,
					setting,
					created_at,
					updated_at,
					start_time,
					end_time
				) VALUES (
					'$admin_guid',
					'$title',
					'$content',
					'$cta',
					'$image',
					'$status',
					$visible,
					$setting,
					'$created_at',
					'$created_at',
			";

			if ($start_time) {
				$query .= " '$start_time',";
			} else {
				$query .= " NULL,";
			}

			if ($end_time) {
				$query .= " '$end_time')";
			} else {
				$query .= " NULL)";
			}

			// email for users, as per global settings
			$enabled = (bool)$helper->fetch_setting('enabled_new_perk');

			if ($enabled) {
				$subject = 'New Perk Created';
				$body = $helper->fetch_setting('email_new_perk');

				if($body) {
					$user_emails = $db->do_select("
						SELECT
						a.email
						FROM users AS a
						JOIN shufti AS b
						ON a.guid = b.guid
						WHERE a.verified = 1
						AND a.role LIKE '%user%'
						AND a.admin_approved = 1
						AND b.status = 'approved'
					");

					$user_emails = $user_emails ?? array();

					foreach ($user_emails as $user_email) {
						$ue = $user_email['email'] ?? '';

						if ($ue) {
							$helper->schedule_email(
								'user-alert',
								$ue,
								$subject,
								$body
							);
						}
					}
				}
			}

		} else {
			// update
			$query = "
				UPDATE perks
				SET
				title         = '$title',
				content       = '$content',
				cta           = '$cta',
				image         = '$image',
				status        = '$status',
				visible       = $visible,
				setting       = $setting,
			";

			if ($start_time) {
				$query .= " start_time = '$start_time',";
			} else {
				$query .= " start_time = NULL,";
			}

			if ($end_time) {
				$query .= " end_time = '$end_time' ";
			} else {
				$query .= " end_time = NULL ";
			}

			$query .= "
				WHERE id = $perk_id
			";
		}

		$db->do_query($query);

		_exit(
			'success',
			'Saved perk'
		);
	}
}
new AdminSavePerk();
