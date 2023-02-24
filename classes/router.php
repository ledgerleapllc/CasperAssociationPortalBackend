<?php
/**
 *
 * Router to replace server config
 *
 * @static $role      URI first part for role
 * @static $endpoint  URI second part for endpoint
 * @static $hash_arg  URI third part for hashed argument
 *
 */
class Router {
	public static $role;
	public static $endpoint;
	public static $hash_arg;

	function __construct() {
		$doc_root     = BASE_DIR.'/public';
		$uri          = $_SERVER['REQUEST_URI'] ?? '';
		$explode      = self::separateURI($uri);
		$uri_role     = $explode[0] ?? '';
		$uri_endpoint = $explode[1] ?? '';
		$uri_hash     = $explode[2] ?? '';

		// check role
		if ($uri_role == '') {
			self::noResource();
		}

		if ($uri_role == 'user') {
			self::$role = 'user';
		}

		elseif ($uri_role == 'admin') {
			self::$role = 'admin';
		}

		elseif ($uri_role == 'public') {
			self::$role = 'public';
		}

		else {
			self::notFound();
		}

		if (
			!$uri_endpoint ||
			strlen($uri_endpoint) > 255 ||
			!preg_match('/^[0-9a-zA-Z- _]/', $uri_endpoint)
		) {
			self::notFound();
		} else {
			self::$endpoint = $uri_endpoint;
		}

		// check endpoint
		$file = (string)(
			$doc_root.
			'/'.
			self::$role.
			'_api/'.
			self::$endpoint.
			'.php'
		);

		if (!is_file($file)) {
			self::notFound();
		}

		// check hashed argument
		if (preg_match('/^[0-9a-zA-Z- _]/', $uri_hash)) {
			self::$hash_arg = $uri_hash;
		} else {
			self::$hash_arg = '';
		}

		include_once($file);
	}

	private static function notFound() {
		_exit(
			'error', 
			'Not Found', 
			404, 
			'Not Found'
		);
	}

	private static function noResource() {
		_exit(
			'success', 
			"I'm a teapot", 
			418, 
			'I am a teapot - Resource not specified'
		);
	}

	private static function separateURI($uri) {
		$explode = explode('/', (string)$uri);
		$uri1    = $explode[1] ?? '';
		$uri2    = $explode[2] ?? '';
		$uri3    = $explode[3] ?? '';

		// find '?' and split
		$split_uri1 = explode('?', $uri1)[0];
		$split_uri2 = explode('?', $uri2)[0];
		$split_uri3 = explode('?', $uri3)[0];

		return array(
			$split_uri1,
			$split_uri2,
			$split_uri3
		);
	}

	function __destruct() {}
}
new Router();