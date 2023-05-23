<?php
include_once(__DIR__.'/../../core.php');

global $helper, $db;

$ch = curl_init();

curl_setopt(
	$ch, 
	CURLOPT_URL, 
	'http://'.NODE_IP.':8888/status'
);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$port8888_response = curl_exec($ch);

if (curl_errno($ch)) {
	elog('Protocol upgrade CURL error: '.curl_error($ch));
}

try {
	$port8888_json = json_decode($port8888_response);
} catch (Exception $e) {
	elog('Protocol upgrade trusted node CURL error: ');
	elog($e);
	$port8888_json = array();
}

$next_upgrade     = $port8888_json->next_upgrade ?? null;
$activation_point = (int)($next_upgrade->activation_point ?? 0);
$protocol_version = $next_upgrade->protocol_version ?? '';

// also check backup trusted node
if (
	!$protocol_version &&
	defined(BACKUP_NODE_IP) &&
	BACKUP_NODE_IP
) {
	curl_setopt(
		$ch, 
		CURLOPT_URL, 
		'http://'.BACKUP_NODE_IP.':8888/status'
	);

	$port8888_response = curl_exec($ch);

	try {
		$port8888_json = json_decode($port8888_response);
	} catch (Exception $e) {
		elog('Protocol upgrade trusted node CURL error: ');
		elog($e);
		$port8888_json = array();
	}

	$next_upgrade     = $port8888_json->next_upgrade ?? null;
	$activation_point = (int)($next_upgrade->activation_point ?? 0);
	$protocol_version = $next_upgrade->protocol_version ?? '';
}

curl_close($ch);

if ($activation_point && $protocol_version) {
	$check = $db->do_select("
		SELECT id
		FROM upgrades
		WHERE version = '$protocol_version'
	");

	if (!$check) {
		// estimate activation date from activation_point
		$now           = $helper->get_datetime();
		$current_era   = $helper->get_current_era_id();
		$eras_diff     = $activation_point - $current_era;
		$hours         = $eras_diff * 2;
		$activation_at = $helper->get_datetime($hours * 60 * 60);

		// insert
		$db->do_query("
			INSERT INTO upgrades (
				version,
				created_at,
				updated_at,
				visible,
				activate_at,
				activate_era
			) VALUES (
				'$protocol_version',
				'$now',
				'$now',
				1,
				'$activation_at',
				$activation_point
			)
		");
	}
}
