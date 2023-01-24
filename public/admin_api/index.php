<?php
header('Content-type:application/json;charset=utf-8');
http_response_code(400);
exit(json_encode(array(
	'status' => 'error',
	'detail' => 'Resource not specified'
)));