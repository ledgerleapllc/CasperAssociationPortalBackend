<?php
/**
 *
 * GET /public/get-year
 *
 * @api
 *
 */
class PublicGetYear extends Endpoints {
	function __construct() {
		global $db, $helper;

		require_method('GET');

		$year = $helper->get_filing_year();

		_exit(
			'success',
			$year
		);
	}
}
new PublicGetYear();