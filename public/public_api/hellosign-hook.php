<?php
include_once('../../core.php');
/**
 *
 * GET/POST/PUT /public/hellosign-hook
 *
 * @api
 * @param string $json
 *
 */
class PublicHellosignHook extends Endpoints {
	function __construct(
		$json = ''
	) {
		global $db, $helper;

		require_method(
			array(
				'GET',
				'POST',
				'PUT'
			)
		);

		$json    = $_REQUEST['json'] ?? '{}';
		$data    = json_decode($json, true);
		$api_key = getenv('HELLOSIGN_API_KEY');
		$headers = getallheaders();

		if (!$data || empty($data)) {
			exit('error');
		}

		// event
		$event_type = filter($data['event']['event_type'] ?? '');

		// hellosign test check
		if ($event_type == 'callback_test') {
			exit("Hello API Event Received");
		}

		$md5_header_check = base64_encode(
			hash_hmac(
				'md5', 
				$json, 
				$api_key
			)
		);

		$md5_header = $headers['Content-MD5'] ?? '';

		// elog($md5_header_check);
		// elog($md5_header);

		if ($md5_header != $md5_header_check) {
			exit('error');
		}

		elog('Hellosign: '.$event_type);

		// Valid Request

		if (
			isset($data['signature_request']) && (
				$event_type == 'signature_request_all_signed' ||
				$event_type == 'signature_request_signed'
			)
		) {
			$signature_request_id = filter($data['signature_request']['signature_request_id'] ?? '');
			$filepath = 'hellosign/hellosign_' . $signature_request_id . '.pdf';

			// get user
			$user = $db->do_select("
				SELECT guid
				FROM users
				WHERE hellosign_sig = '$signature_request_id'
			");

			$guid = $user[0]['guid'] ?? null;

			if ($guid) {
				// update record
				$db->do_query("
					UPDATE users
					SET esigned = 1
					WHERE guid = '$guid'
				");
			}
		}

		exit("Hello API Event Received");
	}
}
new PublicHellosignHook();