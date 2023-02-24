<?php
/**
 *
 * GET /public/get-countries
 *
 * @api
 *
 */
class PublicGetCountries extends Endpoints {
	function __construct() {
		global $db, $helper;

		require_method('GET');

		$countries = Helper::$countries;

		_exit(
			'success',
			$countries
		);
	}
}
new PublicGetCountries();