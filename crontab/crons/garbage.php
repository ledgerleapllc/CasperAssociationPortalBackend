<?php
/**
 *
 * System cron will use curl or wget to ping this endpoint for cleanup of old records and junk.
 *
 */
include_once(__DIR__.'/../../core.php');

global $helper;

/* Clear mfa_allowance */


/* Clear totp_logins */


/* Clear password_resets */


/* Clear email schedule */


/* Clear old twofa codes */


/* Clear old throttle records */
