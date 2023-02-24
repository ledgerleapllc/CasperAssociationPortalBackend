<?php
/**
 *
 * PUT /admin/save-pii
 *
 * HEADER Authorization: Bearer
 *
 * @api
 * @param string $first_name
 * @param string $middle_name
 * @param string $last_name
 * @param string $dob
 * @param string $phone
 *
 */
class AdminSavePii extends Endpoints {
	function __construct(
		$first_name  = '',
		$middle_name = '',
		$last_name   = '',
		$dob         = '',
		$phone       = ''
	) {
		global $db, $helper;

		require_method('PUT');

		$auth        = authenticate_session(2);
		$admin_guid  = $auth['guid'] ?? '';
		$first_name  = parent::$params['first_name'] ?? '';
		$middle_name = parent::$params['middle_name'] ?? '';
		$last_name   = parent::$params['last_name'] ?? '';
		$dob         = parent::$params['dob'] ?? '';
		$phone       = parent::$params['phone'] ?? '';
		$updated_at  = $helper->get_datetime();

		if (!$first_name) {
			$first_name = null;
		}

		if (!$middle_name) {
			$middle_name = null;
		}

		if (!$last_name) {
			$last_name = null;
		}

		if (!$dob) {
			$dob = null;
		}

		if (!$phone) {
			$phone = null;
		}

		$helper->sanitize_input(
			$first_name,
			false,
			1,
			Regex::$human_name['char_limit'],
			Regex::$human_name['pattern'],
			'First name'
		);

		$helper->sanitize_input(
			$middle_name,
			false,
			1,
			Regex::$human_name['char_limit'],
			Regex::$human_name['pattern'],
			'Middle name'
		);

		$helper->sanitize_input(
			$last_name,
			false,
			1,
			Regex::$human_name['char_limit'],
			Regex::$human_name['pattern'],
			'Last name'
		);

		$helper->sanitize_input(
			$dob,
			false,
			Regex::$date['char_limit'],
			Regex::$date['char_limit'],
			Regex::$date['pattern'],
			'DOB'
		);

		$helper->sanitize_input(
			$phone,
			false,
			6,
			20,
			"/^[0-9)( _-]+$/",
			'Phone number'
		);

		$user = $helper->get_user($admin_guid);
		$pii  = $user['pii_data'] ?? array();

		if ($first_name) {
			$pii['first_name']  = $first_name;
		}

		if ($middle_name) {
			$pii['middle_name'] = $middle_name;
		}

		if ($last_name) {
			$pii['last_name']   = $last_name;
		}

		if ($dob) {
			$pii['dob']         = $dob;
		}

		if ($phone) {
			$pii['phone']       = $phone;
		}

		$enc_pii = $helper->encrypt_pii($pii);

		$db->do_query("
			UPDATE users
			SET pii_data = '$enc_pii'
			WHERE guid   = '$admin_guid'
		");
		
		_exit(
			'success',
			'Saved personal information'
		);
	}
}
new AdminSavePii();