<?php
/**
 *
 * GET/POST/PUT /public/shufti-status
 *
 * @api
 * @param string $reference
 * @param string $event
 *
 */
class PublicShuftiStatus extends Endpoints {
	function __construct(
		$reference = '',
		$event     = ''
	) {
		global $db, $helper;

		require_method(
			array(
				'GET',
				'POST',
				'PUT'
			)
		);

		$reference = parent::$params['reference'] ?? '';
		$event     = parent::$params['event'] ?? '';
		$test_hash = parent::$params['test_hash'] ?? '';

		elog('SHUFTI reference: '.$reference.' - '.$event);

		$record    = $db->do_select("
			SELECT *
			FROM shufti
			WHERE reference_id = '$reference'
		");

		$record = $record[0] ?? null;

		if ($record) {
			// fetch user email
			$user_guid  = $record['guid'] ?? '';
			$user_email = $db->do_select("
				SELECT email
				FROM users 
				WHERE guid = '$user_guid'
			");
			$user_email = $user_email[0]['email'] ?? '';

			$events = array(
				'request.deleted',
				'request.timeout',
				'request.cancelled',
				'verification.accepted',
				'verification.declined',
				'verification.status.changed'
			);

			if (in_array($event, $events)) {
				$ch = curl_init();

				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

				curl_setopt(
					$ch, 
					CURLOPT_URL, 
					'https://api.shuftipro.com/status'
				);

				curl_setopt(
					$ch, 
					CURLOPT_HTTPHEADER, 
					array(
						'Content-Type: application/json'
					)
				);

				curl_setopt(
					$ch, 
					CURLOPT_USERPWD, 
					getenv('SHUFTI_CLIENT_ID').
					":".
					getenv('SHUFTI_CLIENT_SECRET')
				);

				curl_setopt(
					$ch, 
					CURLOPT_POSTFIELDS, 
					json_encode(
						array(
							'reference' => $reference
						)
					)
				);

				// integration test case
				if (
					$test_hash &&
					$test_hash == hash(
						'sha256', 
						getenv('SHUFTI_CLIENT_ID').
						":".
						getenv('SHUFTI_CLIENT_SECRET')
					)
				) {
					$response = '{"event":"verification.accepted","verification_result":{"document":{"clear":1,"match":1,"result":1},"address":{"clear":1,"match":1,"result":1},"background_checks":1}}';
				} else {
					$response = curl_exec($ch);
				}

				curl_close($ch);
				$response_enc = $helper->aes_encrypt($response);
				$json         = json_decode($response);
				// elog($json);

				// get datapoints - event, id/address/backgrounds checks
				$updated_at         = $helper->get_datetime();
				$parsed_event       = $json->event ?? '';
				$v_result           = $json->verification_result ?? null;
				$id_check_a         = (array)($v_result->document ?? array());
				$address_check_a    = (array)($v_result->address ?? array());
				$background_check   = (int)($v_result->background_checks ?? 0);

				$id_check         = empty($id_check_a) ? 0 : 1;
				$address_check    = empty($address_check_a) ? 0 : 1;

				foreach ($id_check_a as $key => $val) {
					if ($val === 0) {
						$id_check = 0;
					}
				}

				foreach ($address_check_a as $key => $val) {
					if ($val === 0) {
						$address_check = 0;
					}
				}

				if (
					$parsed_event == 'request.deleted' ||
					$parsed_event == 'request.cancelled'
				) {
					$db->do_query("
						DELETE FROM shufti
						WHERE reference_id = '$reference'
					");
				} else 

				if ($parsed_event == 'verification.accepted') {
					$db->do_query("
						UPDATE shufti
						SET
						status             = 'approved',
						data               = '$response_enc',
						declined_reason    = '',
						id_check           = $id_check,
						address_check      = $address_check,
						background_check   = $background_check,
						updated_at         = '$updated_at'
						WHERE reference_id = '$reference'
					");

					// auto email on accepted
					if ($user_email) {
						$helper->schedule_email(
							'user-alert',
							$user_email,
							'Your KYC has been approved',
							'Good news, your KYC in the Casper Association portal was approved. You now have access to all member areas.'
						);
					}
				} else 

				if ($parsed_event == 'verification.declined') {
					$declined_reason = $json->declined_reason ?? '';

					$db->do_query("
						UPDATE shufti
						SET 
						status             = 'denied',
						data               = '$response_enc',
						declined_reason    = '$declined_reason',
						id_check           = $id_check,
						address_check      = $address_check,
						background_check   = $background_check,
						updated_at         = '$updated_at'
						WHERE reference_id = '$reference'
					");

					// auto email on denied
					if ($user_email) {
						$helper->schedule_email(
							'user-alert',
							$user_email,
							'Your KYC has been denied',
							'We are sorry, your KYC in the Casper Association portal was denied. Your admin has been notified and we are looking into the cause. You may need to re-submit your documents.'
						);
					}
				} else 

				if ($parsed_event == 'verification.status.changed') {
					// do nothing
				} else 

				if ($parsed_event == 'request.timeout') {
					$db->do_query("
						UPDATE shufti
						SET
						status             = NULL,
						data               = '$response_enc',
						declined_reason    = '',
						id_check           = 0,
						address_check      = 0,
						background_check   = 0,
						updated_at         = '$updated_at'
						WHERE reference_id = '$reference'
					");
				}

				_exit(
					'success',
					'Shufti record updated'
				);
			}

			_exit(
				'success',
				'Shufti record pending'
			);
		}

		_exit(
			'success',
			'Shufti record not created yet'
		);
	}
}
new PublicShuftiStatus();