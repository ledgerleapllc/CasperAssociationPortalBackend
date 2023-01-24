<?php
include_once('../../core.php');
/**
 *
 * POST /user/post-hellosign
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class UserPostHellosign extends Endpoints {
	function __construct() {
		global $db, $helper;

		require_method('POST');

		$auth       = authenticate_session(1);
		$user_guid  = $auth['guid'] ?? '';
		$user       = $helper->get_user($user_guid);

		// precheck
		$check = $db->do_select("
			SELECT
			esigned, hellosign_sig
			FROM users
			WHERE guid = '$user_guid'
		");

		$esigned       = (int)($check[0]['esigned'] ?? 0);
		$hellosign_sig = $check[0]['hellosign_sig'] ?? null;

		if ($esigned == 1) {
			_exit(
				'error',
				'Signature already complete',
				400,
				'Signature already complete'
			);
		}

		$client_key = getenv('HELLOSIGN_API_KEY');
		$client_id  = getenv('HELLOSIGN_CLIENT_ID');

		$client     = new \HelloSign\Client($client_key);
		$request    = new \HelloSign\TemplateSignatureRequest;

		if (DEV_MODE) {
			$db->do_query("
				UPDATE users
				SET
				esigned       = 1,
				hellosign_sig = 'abc123'
				WHERE guid    = '$user_guid'
			");

			_exit(
				'success',
				array(
					"signature_request_id" => 'abc123',
					"sign_url"             => 'dev-pass',
				)
			);
		}

		$request->enableTestMode();
		$request->setTemplateId('80392797521f1adb88743f75ea04203a6504ef81');
		$request->setSubject('Member Agreement');

		$request->setSigner(
			'Member', 
			$user['email'], 
			$user['pii_data']['first_name'].' '.
			$user['pii_data']['last_name']
		);

		$request->setCustomFieldValue(
			'FullName', 
			$user['pii_data']['first_name'].' '.
			$user['pii_data']['last_name']
		);

		$request->setCustomFieldValue(
			'FullName2', 
			$user['pii_data']['first_name'].' '.
			$user['pii_data']['last_name']
		);

		$request->setClientId($client_id);

		$initial = strtoupper(
			substr($user['pii_data']['first_name'], 0, 1).
			substr($user['pii_data']['last_name'], 0, 1)
		);

		$request->setCustomFieldValue('Initial', $initial);

		$embedded_request = new \HelloSign\EmbeddedSignatureRequest(
			$request, 
			$client_id
		);

		$response = $client->createEmbeddedSignatureRequest($embedded_request);

		$signature_request_id = $response->getId();
		$signatures           = $response->getSignatures();
		$signature_id         = $signatures[0]->getId();
		$response             = $client->getEmbeddedSignUrl($signature_id);
		$sign_url             = $response->getSignUrl();

		// attach signature_request_id to user
		$db->do_query("
			UPDATE users
			SET   hellosign_sig = '$signature_request_id'
			WHERE guid          = '$user_guid'
		");

		_exit(
			'success',
			array(
				"signature_request_id" => $signature_request_id,
				"sign_url"             => $sign_url,
			)
		);
	}
}
new UserPostHellosign();