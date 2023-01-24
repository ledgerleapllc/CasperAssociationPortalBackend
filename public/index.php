<?php

header("Access-Control-Allow-Origin: *");
header('Access-Control-Allow-Headers:  Content-Type, X-Auth-Token, Authorization, Origin');
header('Access-Control-Allow-Methods:  POST, PUT, GET, OPTIONS');

$HTTP_HOST  = $_SERVER['HTTP_HOST'] ?? '';
$DEV_SERVER = strstr($HTTP_HOST, "3000") ? true : false;
$DEV_SERVER = strstr($HTTP_HOST, "gitpod") ? true : false;

if ($DEV_SERVER) {
	function elog2($msg) {
		file_put_contents('php://stdout', print_r($msg, true));
		file_put_contents('php://stdout', print_r("\n", true));
	}
	elog2('------- USING DEV SERVER -------');

	$uri     = $_SERVER['PATH_INFO'] ?? '';
	$explode = explode('/', $uri);
	$first   = $explode[1] ?? '';

	switch ($first) {
		case 'user':  $first = 'user_api/';  break;
		case 'admin': $first = 'admin_api/'; break;
		default:      $first = '';           break;
	}

	$second = $explode[2] ?? '';

	if ($second) {
		$new_uri = $first.$second.'.php';
	} else {
		$new_uri = $first;
	}

	if (
		$first && 
		$second &&
		is_file($_SERVER['DOCUMENT_ROOT'].'/'.$new_uri)
	) {
		include_once($new_uri);
	}
}

header('Content-type:application/json;charset=utf-8');
http_response_code(400);

exit(json_encode(array(
	'status' => 'error',
	'detail' => 'Resource not specified'
)));