<?php
include_once('../../core.php');
/**
 *
 * GET /admin/get-intake
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class AdminGetIntake extends Endpoints {
	function __construct() {
		global $db, $helper;

		require_method('GET');

		$auth            = authenticate_session(2);
		$user_guid       = $auth['guid'] ?? '';
		$general_intake  = $db->do_select("
			SELECT
			a.guid,
			a.email,
			a.pseudonym,
			a.account_type,
			a.created_at AS registration_date,
			a.letter,
			a.esigned,
			b.verified AS node_verified
			FROM users AS a
			LEFT JOIN user_nodes AS b
			ON a.guid = b.guid
			WHERE a.role LIKE '%user'
			AND a.admin_approved = 0
			AND b.verified IS NOT NULL
		");

		$general_intake  = $general_intake ?? array();

		$id_verification = $db->do_select("
			SELECT
			a.guid,
			a.email,
			a.pseudonym,
			a.account_type,
			a.created_at AS registration_date,
			b.reference_id,
			b.status AS kyc_status,
			b.data,
			b.manual_review,
			b.reviewed_at,
			b.created_at
			FROM users AS a
			JOIN shufti AS b
			ON a.guid = b.guid
			WHERE b.status = 'denied' OR
			      b.status = 'pending'
		");

		$id_verification = $id_verification ?? array();

		_exit(
			'success',
			array(
				"general_intake"  => $general_intake,
				"id_verification" => $id_verification
			)
		);
	}
}
new AdminGetIntake();