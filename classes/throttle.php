<?php
/**
 * Http throttling class intended to mitigate brute force attacks on th API.
 * Especially for endpoints that call the auto-mailer, eg. forgot-password.
 *
 * Instantiating the class immediately causes the throttling to take effect.
 * Exits with code 429 if the client fails based on IP address.
 *
 * @param  string  $real_ip
 */
class Throttle {
	/**
	 * Known endpoints array. Referenced by PHPUnit
	 */
	public const endpoints = array(
		'/user/confirm-registration' => 10,
		'/user/forgot-password' => 5,
		'/user/login' => 6,
		'/user/logout' => 100,
		'/user/me' => 150,
		'/user/name-by-email' => 20,
		'/user/register' => 4,
		'/user/resend-code' => 3,
		'/user/reset-password' => 3,
		'/user/set-password' => 3,
		'/user/verify-set-password' => 10,
		'/user/update-password' => 5,
		'/user/login-mfa' => 8,
		'/user/send-mfa' => 4,
		'/user/confirm-mfa' => 4,
		'/user/update-mfa' => 3,
		'/user/update-totp' => 3,
		'/user/confirm-totp' => 4,
		'/user/update-email' => 3,
		'/user/confirm-update-email' => 3,
		'/user/update-avatar' => 2,
		'/user/get-iplog' => 10,
		'/user/get-warnings' => 20,
		'/user/dismiss-notification' => 10,
		'/user/get-notifications' => 20,
		'/user/get-dashboard' => 20,
		'/user/get-membership' => 15,
		'/user/get-nodes' => 15,
		'/user/get-node-data' => 15,
		'/user/get-my-eras' => 15,
		'/user/get-all-discussions' => 15,
		'/user/get-my-discussions' => 15,
		'/user/get-pinned-discussions' => 15,
		'/user/get-draft-discussions' => 15,
		'/user/get-draft-discussion' => 15,
		'/user/save-draft-discussion' => 15,
		'/user/discard-draft' => 15,
		'/user/pin-discussion' => 20,
		'/user/like-discussion' => 20,
		'/user/post-discussion' => 4,
		'/user/get-discussion' => 20,
		'/user/lock-discussion' => 5,
		'/user/delete-discussion' => 4,
		'/user/edit-discussion' => 6,
		'/user/post-comment' => 5,
		'/user/delete-comment' => 5,
		'/user/edit-comment' => 5,
		'/user/get-active-ballots' => 15,
		'/user/get-finished-ballots' => 15,
		'/user/get-my-votes' => 15,
		'/user/get-ballot' => 20,
		'/user/vote' => 12,
		'/user/get-perks' => 15,
		'/user/get-perk' => 20,
		'/user/get-vote-eligibility' => 15,
		'/user/get-profile' => 20,
		'/user/get-account-validators' => 15,
		'/user/get-general-assemblies' => 15,
		'/user/create-general-assembly' => 2,
		'/user/edit-general-assembly' => 3,
		'/user/cancel-general-assembly' => 3,
		'/user/get-general-assembly-proposed-times' => 15,
		'/user/find-affiliated-nodes' => 15,
		'/user/add-affiliated-node' => 4,
		'/user/verify-node' => 4,
		'/user/download-message' => 7,
		'/user/upload-signature' => 7,
		'/user/upload-letter' => 5,
		'/user/upload-entity-doc' => 15,
		'/user/get-entity-docs' => 30,
		'/user/get-entity-pii' => 30,
		'/user/remove-node' => 4,
		'/user/get-esign-doc' => 5,
		'/user/post-hellosign' => 5,
		'/user/get-available-upgrade' => 20,
		'/user/get-upgrades' => 15,
		'/user/get-upgraded-users' => 15,
		'/user/complete-upgrade' => 5,
		'/user/save-pii' => 5,
		'/user/get-shufti-token' => 4,
		'/user/validate-shufti-signature' => 20,
		'/user/save-shufti-ref' => 4,
		'/user/get-suspension-status' => 12,
		'/user/request-reactivation' => 5,

		'/admin/login' => 5,
		'/admin/login-mfa' => 8,
		'/admin/forgot-password' => 5,
		'/admin/reset-password' => 4,
		'/admin/me' => 150,
		'/admin/logout' => 100,
		'/admin/new-totp' => 4,
		'/admin/update-totp' => 3,
		'/admin/confirm-totp' => 4,
		'/admin/send-mfa' => 4,
		'/admin/confirm-mfa' => 4,
		'/admin/update-mfa' => 3,
		'/admin/update-email' => 3,
		'/admin/confirm-update-email' => 3,
		'/admin/update-password' => 5,
		'/admin/get-user' => 50,
		'/admin/get-users' => 100,
		'/admin/get-iplog' => 100,
		'/admin/update-setting' => 100,
		'/admin/update-avatar' => 4,
		'/admin/get-subscriptions' => 15,
		'/admin/get-merchant-settings' => 15,
		'/admin/get-dashboard' => 20,
		'/admin/get-nodes' => 20,
		'/admin/get-node-data' => 20,
		'/admin/get-profile' => 20,
		'/admin/get-all-discussions' => 20,
		'/admin/get-my-discussions' => 20,
		'/admin/get-pinned-discussions' => 20,
		'/admin/get-draft-discussions' => 20,
		'/admin/get-draft-discussion' => 20,
		'/admin/save-draft-discussion' => 20,
		'/admin/discard-draft' => 20,
		'/admin/pin-discussion' => 20,
		'/admin/like-discussion' => 20,
		'/admin/post-discussion' => 5,
		'/admin/get-discussion' => 5,
		'/admin/lock-discussion' => 5,
		'/admin/delete-discussion' => 5,
		'/admin/edit-discussion' => 5,
		'/admin/post-comment' => 5,
		'/admin/delete-comment' => 5,
		'/admin/censor-comment' => 30,
		'/admin/uncensor-comment' => 30,
		'/admin/edit-comment' => 5,
		'/admin/get-active-ballots' => 20,
		'/admin/get-finished-ballots' => 20,
		'/admin/get-all-votes' => 20,
		'/admin/get-ballot' => 20,
		'/admin/cancel-ballot' => 5,
		'/admin/create-ballot' => 5,
		'/admin/upload-ballot-file' => 5,
		'/admin/get-general-assemblies' => 20,
		'/admin/get-intake' => 20,
		'/admin/download-letter' => 5,
		'/admin/approve-user' => 10,
		'/admin/deny-user' => 10,
		'/admin/remove-user' => 10,
		'/admin/get-teams' => 20,
		'/admin/put-permission' => 10,
		'/admin/get-permissions' => 20,
		'/admin/invite-sub-admin' => 5,
		'/admin/cancel-team-invite' => 10,
		'/admin/accept-team-invite' => 3,
		'/admin/team-invite-check-hash' => 20,
		'/admin/get-global-settings' => 20,
		'/admin/get-contact-recipients' => 20,
		'/admin/add-contact-recipient' => 5,
		'/admin/delete-contact-recipient' => 5,
		'/admin/upload-terms' => 5,
		'/admin/get-emailer-admins' => 20,
		'/admin/add-emailer-admin' => 5,
		'/admin/delete-emailer-admin' => 5,
		'/admin/get-emailer-triggers' => 20,
		'/admin/get-notifications' => 20,
		'/admin/save-notification' => 5,
		'/admin/delete-notification' => 5,
		'/admin/get-notification-users' => 20,
		'/admin/get-user-eras' => 20,
		'/admin/get-perks' => 20,
		'/admin/get-perk' => 20,
		'/admin/save-perk' => 5,
		'/admin/delete-perk' => 5,
		'/admin/upload-perk-image' => 5,
		'/admin/get-available-upgrade' => 20,
		'/admin/get-upgrades' => 20,
		'/admin/save-upgrade' => 5,
		'/admin/get-upgraded-users' => 20,
		'/admin/save-pii' => 4,
		'/admin/get-revoked-users' => 20,
		'/admin/get-historic-revoked-users' => 20,
		'/admin/cmp-check' => 20,
		'/admin/manually-update-user-kyc' => 6,
		'/admin/reinstate-user' => 15,
		'/admin/reset-user-suspension' => 15,
		'/admin/get-demo-users' => 200,

		'/public/ca-kyc-hash' => 30,
		'/public/contact-us' => 4,
		'/public/get-countries' => 60,
		'/public/get-dev-mode' => 60,
		'/public/get-esign-doc' => 5,
		'/public/get-merchant-data' => 15,
		'/public/get-node-data' => 15,
		'/public/get-profile' => 20,
		'/public/get-validators' => 15,
		'/public/get-year' => 60,
		'/public/hellosign-hook' => 5,
		'/public/shufti-status' => 15,
		'/public/subscribe' => 15,
		'/public/reset' => 15
	);

	function __construct(string $real_ip = '127.0.0.1') {
		// forget throttling during dev
		if(
			$real_ip == '127.0.0.1' ||
			$real_ip == 'localhost' ||
			$real_ip == '::1' ||
			$real_ip == '0:0:0:0:0:0:0:1' ||
			DEV_MODE
		) {
			return true;
		}

		global $db;

		$this->now = (int)time();
		$this->ip = $real_ip;
		$this->uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';

		// no need to go any further for unit tests
		if($real_ip == 'unittest') {
			return true;
		}

		// check hit, log hit
		$query = "
			SELECT hit, last_request
			FROM  throttle
			WHERE ip  = '$this->ip'
			AND   uri = '$this->uri'
		";

		$selection = $db->do_select($query);

		if(!$selection) {
			$query = "
				INSERT INTO throttle (
					ip,
					uri
				) VALUES (
					'$this->ip',
					'$this->uri'
				)
			";
			$db->do_query($query);
		}

		$minute = 60;
		$minute_limit = self::endpoints[$this->uri] ?? 30;
		$last_api_request = (int)($selection[0]['last_request'] ?? 0);
		$last_api_diff = $this->now - $last_api_request;
		$minute_throttle = (float)($selection[0]['hit'] ?? 0);
		$new_minute_throttle = $minute_throttle - $last_api_diff;
		$new_minute_throttle = $new_minute_throttle < 0 ? 0 : $new_minute_throttle;
		$new_minute_throttle += $minute / $minute_limit;
		$minute_hits_remaining = floor(($minute - $new_minute_throttle) * $minute_limit / $minute);
		$minute_hits_remaining = $minute_hits_remaining >= 0 ? $minute_hits_remaining : 0;

		if($new_minute_throttle > $minute) {
			_exit(
				"error",
				"Too many requests to this resource",
				429,
				"Too many requests to this resource"
			);
		}

		$db->do_query("
			UPDATE throttle
			SET
			hit          = $new_minute_throttle,
			last_request = $this->now
			WHERE ip     = '$this->ip'
			AND   uri    = '$this->uri'
		");
	}

	function __destruct() {
		// empty for now
	}

	/**
	 * Used by PHPUnit to verify known endpoints are accounted for in the router.
	 *
	 * @return array<string, int> endpoints
	 */
	public static function get_endpoints() {
		return self::endpoints;
	}
}
