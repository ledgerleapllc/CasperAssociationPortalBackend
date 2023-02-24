<?php

include_once('../core.php');

// new router
$router = '../classes/router.php';

if (file_exists($router)) {
	include_once($router);
} else {
	header('Content-type:application/json;charset=utf-8');
	http_response_code(404);

	exit(json_encode(array(
		'status' => 404,
		'detail' => 'Not Found'
	)));
}
