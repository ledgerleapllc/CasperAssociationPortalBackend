<?php
/**
 *
 * System cron will use curl or wget to ping this endpoint every 60 seconds to handle email scheduler.
 *
 */
include_once(__DIR__.'/../../core.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

global $helper, $db;

$emailer = new PHPMailer(true);
// $emailer->SMTPDebug = SMTP::DEBUG_SERVER;
$emailer->isSMTP();
$emailer->Host = getenv('EMAIL_HOST');
$emailer->Port = getenv('EMAIL_PORT');
$emailer->SMTPKeepAlive = true;
// $emailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
$emailer->SMTPSecure = 'tls';
$emailer->SMTPAuth   = true;
$emailer->Username   = getenv('EMAIL_USER');
$emailer->Password   = getenv('EMAIL_PASS');
$emailer->setFrom(getenv('EMAIL_FROM'), getenv('APP_NAME'));
$emailer->addReplyTo(getenv('EMAIL_FROM'), getenv('APP_NAME'));
$emailer->isHTML(true);

$selection = $db->do_select("
	SELECT *
	FROM schedule
	WHERE complete = 0
	LIMIT 10
");
$selection = $selection ?? array();

foreach ($selection as $s) {
	$sid         = $s['id'] ?? 0;
	$template_id = $s['template_id'] ?? '';
	$subject     = $s['subject'] ?? '';
	$body        = $s['body'] ?? '';
	$link        = $s['link'] ?? '';
	$email       = $s['email'] ?? '';
	$sent_at     = $helper->get_datetime();
	$api_url     = PROTOCOL."://".CORS_SITE;
	$front_url   = PROTOCOL."://".FRONTEND_URL;
	$this_year   = $helper->get_filing_year();

	if ($this_year != FIRST_YEAR) {
		$year_marker = FIRST_YEAR.' - '.$this_year;
	} else {
		$year_marker = FIRST_YEAR;
	}

	if (!$link) {
		$link = $front_url;
	}

	$dir = __DIR__.'/../../templates';

	$template = file_get_contents($dir.'/'.$template_id.'.html');
	$template = str_replace('[SUBJECT]',      $subject,     $template);
	$template = str_replace('[BODY]',         $body,        $template);
	$template = str_replace('[LINK]',         $link,        $template);
	$template = str_replace('[API_URL]',      $api_url,     $template);
	$template = str_replace('[FRONTEND_URL]', $front_url,   $template);
	$template = str_replace('[YEAR_MARKER]',  $year_marker, $template);

	try {
		$emailer->addAddress($email);
		$emailer->Subject = $subject;
		$emailer->Body    = $template;
		$emailer->send();
		elog("SENT: Scheduled '".$template_id."' email ID# ".$sid." sent to: ".$email);

		$query = "
			UPDATE schedule
			SET complete = 1, sent_at = '$sent_at'
			WHERE id = $sid
		";
		$db->do_query($query);
	} catch (Exception $e) {
		elog($e);
		$emailer->getSMTPInstance()->reset();
		elog("FAILED: Scheduled '".$template_id."' email ID# ".$sid);
	}

	$emailer->clearAddresses();
	$emailer->clearAttachments();
}
