<?php
/**
 *
 * Status legend
 * enum('pending','active','done')
 *
 */
include_once(__DIR__.'/../../core.php');

global $helper, $db;

$now = $helper->get_datetime();

// get users that can vote on ballots, and admins
$email_queue = $db->do_select("
	SELECT
	a.email,
	a.role,
	a.pseudonym,
	b.status AS kyc_status
	FROM users AS a
	LEFT JOIN shufti AS b
	ON a.guid = b.guid
	WHERE (
		a.role LIKE '%user%' AND
		b.status = 'approved'
	) OR (
		a.role LIKE '%admin%'
	)
");

$email_queue = $email_queue ?? array();


// do pending ballots first
$pending_ballots = $db->do_select("
	SELECT *
	FROM ballots
	WHERE status = 'pending'
	AND start_time < '$now'
	AND end_time   > '$now'
");
$pending_ballots = $pending_ballots ?? array();

foreach ($pending_ballots as $ballot) {
	$ballot_id = $ballot['id'] ?? 0;
	$creator   = $ballot['guid'] ?? '';
	$title     = $ballot['title'] ?? '';
	$end_time  = $ballot['end_time'] ?? '';

	$db->do_query("
		UPDATE ballots
		SET status = 'active'
		WHERE id = $ballot_id
	");

	// email alert, as per global settings
	$enabled = (bool)$helper->fetch_setting('enabled_vote_started');

	if ($enabled) {
		$body = $helper->fetch_setting('email_vote_started');

		if ($body) {
			foreach ($email_queue as $recipient) {
				$re = $recipient["email"] ?? '';
				$helper->schedule_email(
					'user-alert',
					$re,
					'New vote has started',
					$body,
					''
				);
			}
		}
	}
}


// next collect ballots that need a 24 hr reminder, if applicable
$one_day_from_now = $helper->get_datetime(86400);
$one_day_ago      = $helper->get_datetime(-86400);
$reminder_ballots = $db->do_select("
	SELECT *
	FROM ballots
	WHERE status = 'active'
	AND reminded = 0
	AND end_time < '$one_day_from_now'
");
$reminder_ballots = $reminder_ballots ?? array();

foreach ($reminder_ballots as $ballot) {
	$ballot_id  = $ballot['id'] ?? 0;
	$start_time = $ballot_id['start_time'] ?? '';

	// only send 24 hr reminder if the ballot lifespan is long enough
	if ($start_time < $one_day_ago) {
		// email alert, as per global settings
		$enabled = (bool)$helper->fetch_setting('enabled_vote_reminder');

		if ($enabled) {
			$body = $helper->fetch_setting('email_vote_reminder');

			if ($body) {
				foreach ($email_queue as $recipient) {
					$re = $recipient["email"] ?? '';
					$helper->schedule_email(
						'user-alert',
						$re,
						'Voting ends in 24 hours',
						$body,
						''
					);
				}
			}
		}
	}

	$db->do_query("
		UPDATE ballots
		SET reminded = 1
		WHERE id = $ballot_id
	");
}


// lastly wrap up active ballots
$finished_ballots = $db->do_select("
	SELECT *
	FROM ballots
	WHERE status = 'active'
	AND end_time < '$now'
");
$finished_ballots = $finished_ballots ?? array();

foreach ($finished_ballots as $ballot) {
	$ballot_id = $ballot['id'] ?? 0;
	$creator   = $ballot['guid'] ?? '';
	$title     = $ballot['title'] ?? '';
	$end_time  = $ballot['end_time'] ?? '';

	$db->do_query("
		UPDATE ballots
		SET
		status   = 'done',
		reminded = 1
		WHERE id = $ballot_id
	");
}
