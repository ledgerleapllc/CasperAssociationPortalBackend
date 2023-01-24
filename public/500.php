<?php
header('Content-type:application/json;charset=utf-8');
http_response_code(500);
exit(json_encode(array(
	'status' => 'error',
	'detail' => 'Internal server error'
)));