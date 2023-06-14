<?php
/**
 *
 * One cron to rule them all - intended to run every minute.
 * Controls all crons with one command called from crontab.
 * Meant for only one special instance among many load balanced servers.
 *
 * Add this file in your crontab:
 *
 * * * * * * php /path/to/repo/crontab/cron.php
 *
 * To manually trigger a specific cronjob, specify the name on the cli:
 *
 * $ php crontab/cron.php node-info
 *
 * @var Cron $cron Master cron controller.
 *
 */
include_once(__DIR__.'/../core.php');
include_once(BASE_DIR.'/classes/cron.php');

$target_cron = '';

if (isset($argv)) {
	$target_cron = $argv[1] ?? '';
}

$definition = array(
	array(
		"name"     => "node-info",
		"interval" => 15
	),
	array(
		"name"     => "schedule",
		"interval" => 1
	),
	array(
		"name"     => "garbage",
		"interval" => 15
	),
	array(
		"name"     => "ballots",
		"interval" => 1
	),
	array(
		"name"     => "members",
		"interval" => 1
	),
	array(
		"name"     => "protocol-upgrades",
		"interval" => 5
	),
	array(
		"name"     => "token-price",
		"interval" => 30
	)
);

$cron = new Cron($definition);
$cron->run_crons($target_cron);
