<?php
/**
 *
 * Page permissions controller
 *
 */
class Permissions {
	/** 
	 * Known endpoints array.
	 */
	public const endpoints = array(
		"membership"      => array(
			"get-membership"
		),
		"nodes"           => array(
			"get-nodes"
		),
		"eras"            => array(
			"get-my-eras"
		),
		"discussions"     => array(
			"get-all-discussions",
			"get-my-discussions",
			"get-pinned-discussions",
			"get-draft-discussions",
			"get-draft-discussion",
			"discard-draft",
			"save-draft-discussion",
			"get-discussion",
			"pin-discussion",
			"like-discussion",
			"lock-discussion",
			"post-discussion",
			"delete-discussion",
			"edit-discussion",
			"post-comment",
			"delete-comment",
			"edit-comment"
		),
		"ballots"         => array(
			"get-active-ballots",
			"get-finished-ballots",
			"get-general-assemblies",
			"create-general-assembly",
			"edit-general-assembly",
			"get-general-assembly-proposed-times",
			"get-my-votes",
			"get-all-votes",
			"get-ballot",
			"create-ballot",
			"vote"
		),
		"perks"           => array(
			"get-perks",
			"get-perk",
			"save-perk",
			"upload-perk-image"
		),

		// admin
		"intake"          => array(
			"get-intake",
			"download-letter",
			"approve-user",
			"deny-user",
			"ban-user",
			"remove-user"
		),
		"users"           => array(
			"get-users",
			"get-user"
		),
		"teams"           => array(
			"get-teams",
			"invite-sub-admin",
			"put-permission",
			"cancel-team-invite"
		),
		"global_settings" => array(
			"get-global-settings",
			"update-setting"
		)
	);

	function __construct() {
		//
	}

	function __destruct() {
		// do nothing yet
	}

	/**
	 *
	 * Fetches permissions array by user
	 *
	 * @param  string $guid
	 * @return array  $permissions
	 *
	 */
	private static function get_permissions($guid) {
		global $db;

		$permissions = $db->do_select("
			SELECT *
			FROM permissions
			WHERE guid = '$guid'
		");

		if (!$permissions) {
			$db->do_query("
				INSERT INTO permissions (
					guid
				) VALUES (
					'$guid'
				)
			");

			$permissions = $db->do_select("
				SELECT *
				FROM permissions
				WHERE guid = '$guid'
			");
		}

		$permissions = $permissions[0];
		unset($permissions['guid']);

		return $permissions;
	}

	/**
	 *
	 * Determine if a user is able to access the requested endpoint
	 *
	 * @param  string $guid
	 * @return bool   $allowed
	 *
	 */
	public function allowed($guid) {
		$allowed = self::get_permissions($guid);
		$uri     = $_SERVER['REQUEST_URI'] ?? '/';
		$explode = explode('/', $uri);
		$uri     = end($explode);
		$noparam = explode('?', $uri);
		$uri     = $noparam[0];

		foreach ($allowed as $endpoint => $value) {
			if (
				(int)$value === 0 &&
				in_array($uri, self::endpoints[$endpoint])
			) {
				return false;
			}
		}

		return true;
	}
}