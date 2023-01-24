<?php
/**
 * TOTP multi factor authentication class.
 * Uses spomky-labs/otphp
 */
class Totp {
	function __construct() {
		//
	}

	function __destruct() {
		//
	}

	/**
	 *
	 * Create a TOTP key and save by user guid
	 *
	 * Returns false if no user record found by guid
	 *
	 * @param  string  $guid
	 * @return string  $provisioning_uri  URI used to encode into a QR code
	 *
	 */
	public static function create_totp_key(
		$guid
	) {
		global $helper, $db;

		$query = "
			SELECT email
			FROM users
			WHERE guid = '$guid'
		";
		$email = $db->do_select($query);
		$email = $email[0]['email'] ?? null;

		// if(!$email && $guid != '00000000-0000-0000-4c4c-000000000000') {
		// 	return false;
		// }

		try {
			$totp_instance = OTPHP\TOTP::create();
			$totp_secret = $totp_instance->getSecret();
			$totp_code = $totp_instance->now();
		} catch (\Exception $e) {
			elog('TOTP INSTANCE FAIL');
			elog($e);
			return false;
		}

		$enc_secret = $helper->aes_encrypt($totp_secret);
		$created_at = $helper->get_datetime();
		$hash = hash('sha256', $totp_secret);

		/* deactivate old keys */
		$query = "
			UPDATE totp
			SET active = 0
			WHERE guid = '$guid'
		";
		$db->do_query($query);

		$query = "
			INSERT INTO totp (
				guid,
				secret,
				hash,
				created_at
			) VALUES (
				'$guid',
				'$enc_secret',
				'$hash',
				'$created_at'
			)
		";

		$created = $db->do_query($query);
		$provisioning_uri = self::generate_provisioning_uri($guid);

		return $provisioning_uri;
	}

	/**
	 *
	 * Chech a TOTP code by user guid
	 *
	 * Returns true if totp mfa authentication succeeds
	 * Returns false if no totp record is found or authentication fails
	 *
	 * @param  string  $guid
	 * @param  string  $code
	 * @return bool
	 *
	 */
	public static function check_code(
		$guid, 
		$code
	) {
		global $helper, $db;

		$query = "
			SELECT secret
			FROM totp
			WHERE guid = '$guid'
			ORDER BY created_at DESC
			LIMIT 1
		";
		$enc_secret = $db->do_select($query);
		$enc_secret = $enc_secret[0]['secret'] ?? null;

		if(!$enc_secret) {
			return false;
		}

		try {
			$totp_instance = OTPHP\TOTP::create($helper->aes_decrypt($enc_secret));
			$check_code = $totp_instance->now();
			$valid = hash_equals(
				(string)$code,
				(string)$check_code
			);

			return $valid;
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 *
	 * Generate a provisioning uri for exporting to a QR code
	 *
	 * @param  string  $guid
	 * @return string
	 *
	 */
	public static function generate_provisioning_uri(
		$guid
	) {
		global $helper, $db;

		$query = "
			SELECT email
			FROM users
			WHERE guid = '$guid'
		";
		$email = $db->do_select($query);
		$email = $email[0]['email'] ?? null;

		if(!$email && $guid != '00000000-0000-0000-4c4c-000000000000') {
			return '';
		}

		$query = "
			SELECT secret
			FROM totp
			WHERE guid = '$guid'
			ORDER BY created_at DESC
			LIMIT 1
		";
		$enc_secret = $db->do_select($query);
		$enc_secret = $enc_secret[0]['secret'] ?? null;

		if(!$enc_secret) {
			return '';
		}

		return (
			'otpauth://totp/'.$email.
			'?secret='.$helper->aes_decrypt($enc_secret).
			'&issuer='.APP_NAME
		);
	}

	/**
	 *
	 * Get a valid code
	 *
	 * @param  string  $guid
	 * @return string
	 *
	 */
	public static function get_totp_code(
		$guid
	) {
		global $helper, $db;

		$query = "
			SELECT secret
			FROM totp
			WHERE guid = '$guid'
			ORDER BY created_at DESC
			LIMIT 1
		";
		$enc_secret = $db->do_select($query);
		$enc_secret = $enc_secret[0]['secret'] ?? null;

		if(!$enc_secret) {
			return null;
		}

		try {
			$totp_instance = OTPHP\TOTP::create($helper->aes_decrypt($enc_secret));
			$code = $totp_instance->now();
			return $code;
		} catch (\Exception $e) {
			return null;
		}
	}
}