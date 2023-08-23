<?php
/**
 *
 * GET /admin/get-contact-recipients
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class AdminGetContactRecipients extends Endpoints {
	function __construct() {
		global $db, $helper;

		require_method('GET');

		$auth = authenticate_session(2);
		$admin_guid = $auth['guid'] ?? '';

		$contacts = $db->do_select("
			SELECT
			guid,
			email,
			created_at
			FROM contact_recipients
		") ?? array();

		_exit(
			'success',
			$contacts
		);
	}
}
new AdminGetContactRecipients();
