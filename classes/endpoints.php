<?php
/**
 *
 * Endpoint base class for defining router endpoints
 *
 */
class Endpoints {
	public static $params;

	function __construct() {
		$method = get_method();

		if ($method == 'GET') {
			foreach($_REQUEST as $key => $val) {
				self::$params[$key] = _request($key);
			}
		}

		if ($method == 'POST' || $method == 'PUT') {
			self::$params = get_params();
		}
	}

	function __destruct() {}
}
new Endpoints();