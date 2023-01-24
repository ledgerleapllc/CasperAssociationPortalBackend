<?php
include_once('../../core.php');
/**
 *
 * PUT /admin/manually-update-user-kyc
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param string $guid
 * @param string $status  enum('denied', 'approved', 'reset')
 * @param string $declined_reason
 *
 */
class AdminManuallyUpdateUserKyc extends Endpoints {
	function __construct(
		$guid            = '',
		$status          = '',
		$declined_reason = ''
	) {
		global $db, $helper;

		require_method('PUT');

		$auth       = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';
		$guid       = parent::$params['guid'] ?? '';
		$status     = parent::$params['status'] ?? null;
		$reason     = parent::$params['declined_reason'] ?? null;
		$now        = $helper->get_datetime();

		if (strlen($reason) > 2048) {
			_exit(
				'error',
				'Declined reason too long. Please limit to 2048 chars',
				400,
				'Declined reason too long. Please limit to 2048 chars'
			);
		}

		$check = $db->do_select("
			SELECT
			guid, status, manual_review, reviewed_by
			FROM shufti
			WHERE guid = '$guid'
		");

		$guid_check    = $check[0]['guid'] ?? null;
		$status_check  = $check[0]['status'] ?? null;
		$manual_review = $check[0]['manual_review'] ?? null;
		$reviewed_by   = $check[0]['reviewed_by'] ?? '';

		if ($status_check == 'approved') {
			// check auth level to override
			$admin_role = $db->do_select("
				SELECT role
				FROM users
				WHERE guid = '$reviewed_by'
			");
			$admin_role = $admin_role[0]['role'] ?? '';

			if (
				$admin_role   == 'admin' &&
				$auth['role'] == 'sub-admin'
			) {
				_exit(
					'error',
					'User has already been manually approved by an admin',
					403,
					'User has already been manually approved by an admin'
				);
			}
		}

		// filter
		switch ($status) {
			case 'reset':    $status = false;      break;
			case 'approved': $status = 'approved'; break;
			case 'denied':   $status = 'denied';   break;
			default: null; break;
		}

		if ($status === null) {
			_exit(
				'error',
				'Invalid update status. Must be one of: approved, denied, or reset',
				400,
				'Invalid update status. Must be one of: approved, denied, or reset'
			);
		}

		// fetch user email for notifcations
		$user_email = $db->do_select("
			SELECT email
			FROM users
			WHERE guid = '$guid'
		");
		$user_email = $user_email[0]['email'] ?? '';

		// update existing user shufti record
		if ($guid_check) {
			if ($status == 'approved') {
				// update
				$db->do_query("
					UPDATE shufti
					SET
					manual_review    = 1,
					reviewed_at      = '$now',
					reviewed_by      = '$admin_guid',
					status           = 'approved',
					id_check         = 1,
					address_check    = 1,
					background_check = 1,
					declined_reason  = ''
					WHERE guid       = '$guid'
				");

				// email on manual approved
				if ($user_email) {
					$helper->schedule_email(
						'user-alert',
						$user_email,
						'Your KYC has been approved',
						'Good news, your KYC in the Casper Association portal was approved. You now have access to all member areas.'
					);
				}
			} else

			if ($status == 'denied') {
				// update
				$db->do_query("
					UPDATE shufti
					SET
					manual_review    = 1,
					reviewed_at      = '$now',
					reviewed_by      = '$admin_guid',
					status           = 'denied',
					id_check         = 0,
					address_check    = 0,
					background_check = 0,
					declined_reason  = '$reason'
					WHERE guid       = '$guid'
				");

				// email on manual approved
				if ($user_email) {
					$helper->schedule_email(
						'user-alert',
						$user_email,
						'Your KYC has been denied',
						'We are sorry, your KYC in the Casper Association portal was denied. An admin has been notified and may be able to perform a manual review. Otherwise, your KYC will be reset, and you will have to re-submit documents again to become a member of the Casper Association Portal. <br>Reason: '.$reason
					);
				}
			}

			else {
				// reset
				$db->do_query("
					UPDATE shufti
					SET
					manual_review    = 0,
					reviewed_at      = NULL,
					reviewed_by      = NULL,
					status           = NULL,
					cmp_checked      = 0,
					id_check         = 0,
					address_check    = 0,
					background_check = 0,
					declined_reason  = '$reason',
					updated_at       = '$now'
					WHERE guid       = '$guid'
				");

				// email on manual approved
				if ($user_email) {
					$helper->schedule_email(
						'user-alert',
						$user_email,
						'Your KYC needed to be reset',
						'Your KYC needed to be reset. We have attached the reason below. You will have to re-submit documents again to become a member of the Casper Association Portal. <br>Reason: '.$reason
					);
				}

			}

			_exit(
				'success',
				'User KYC manually updated'
			);
		}

		// user does not have a shufti record yet. insert new one
		else {
			$generated_reference_id = 'SHUFTI_'.$guid.'bypass'.$helper->generate_hash(10);

			if ($status == 'approved') {
				$db->do_query("
					INSERT INTO shufti (
						guid,
						reference_id,
						status,
						manual_review,
						id_check,
						address_check,
						background_check,
						reviewed_at,
						reviewed_by,
						created_at,
						updated_at
					) VALUES (
						'$guid',
						'$generated_reference_id',
						'approved',
						1,
						1,
						1,
						1,
						'$now',
						'$admin_guid',
						'$now',
						'$now'
					)
				");
			} else

			if ($status == 'denied') {
				$db->do_query("
					INSERT INTO shufti (
						guid,
						reference_id,
						status,
						manual_review,
						id_check,
						address_check,
						background_check,
						declined_reason,
						reviewed_at,
						reviewed_by,
						created_at,
						updated_at
					) VALUES (
						'$guid',
						'$generated_reference_id',
						'denied',
						1,
						0,
						0,
						0,
						'$reason',
						'$now',
						'$admin_guid',
						'$now',
						'$now'
					)
				");
			}

			_exit(
				'success',
				'User KYC manually updated'
			);
		}

		_exit(
			'error',
			'There was a problem updating user KYC data',
			500,
			'There was a problem updating user KYC data'
		);
	}
}
new AdminManuallyUpdateUserKyc();