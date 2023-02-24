<?php
/**
 *
 * GET /user/get-esign-doc
 *
 * HEADER Authorization: Bearer
 *
 * @api
 *
 */
class UserGetEsignDoc extends Endpoints {
	function __construct() {
		global $db, $helper;

		require_method('GET');

		$auth        = authenticate_session(1);
		$user_guid   = $auth['guid'] ?? '';

		$url  = $helper->fetch_setting('esign_doc');
		$name = explode('/', $url);
		$name = end($name);

		_exit(
			'success',
			array(
				'url'  => $url,
				'name' => $name
			)
		);
	}
}
new UserGetEsignDoc();