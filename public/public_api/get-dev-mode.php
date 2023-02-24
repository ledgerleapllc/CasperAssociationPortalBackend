<?php
/**
 *
 * GET /public/get-dev-mode
 *
 * @api
 *
 */
class PublicGetDevMode extends Endpoints {
	function __construct() {
		global $db, $helper;

		require_method('GET');

		_exit(
			'success',
			array(
				"dev_mode" => DEV_MODE
			)
		);
	}
}
new PublicGetDevMode();