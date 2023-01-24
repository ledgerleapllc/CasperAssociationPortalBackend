<?php
include_once('../../core.php');
/**
 *
 * GET /public/reset
 *
 * @api
 *
 */
class ResetDatabase {
	function __construct() {
		global $db;

		require_method('GET');

		$users = $db->do_select("
			SELECT guid, email
			FROM   users 
			WHERE  role LIKE '%user'
			OR     role = 'sub-admin'
		");

		$users = $users ?? array();

		foreach ($users as $user) {
			$guid  = $user['guid']  ?? '';
			$email = $user['email'] ?? '';

			$db->do_query("
				DELETE FROM authorized_devices
				WHERE guid = '$guid'
			");

			$db->do_query("
				DELETE FROM avatar_changes
				WHERE guid = '$guid'
			");

			$db->do_query("
				DELETE FROM email_changes
				WHERE guid = '$guid'
			");

			$db->do_query("
				DELETE FROM login_attempts
				WHERE guid = '$guid'
			");

			$db->do_query("
				DELETE FROM mfa_allowance
				WHERE guid = '$guid'
			");

			$db->do_query("
				DELETE FROM password_resets
				WHERE guid = '$guid'
			");

			$db->do_query("
				DELETE FROM sessions
				WHERE guid = '$guid'
			");

			$db->do_query("
				DELETE FROM shufti
				WHERE guid = '$guid'
			");

			$db->do_query("
				DELETE FROM totp_logins
				WHERE guid = '$guid'
			");

			$db->do_query("
				DELETE FROM totp
				WHERE guid = '$guid'
			");

			$db->do_query("
				DELETE FROM twofa
				WHERE guid = '$guid'
			");

			$db->do_query("
				DELETE FROM user_nodes
				WHERE guid = '$guid'
			");

			$db->do_query("
				DELETE FROM warnings
				WHERE guid = '$guid'
			");
		}

		$db->do_query("
			DELETE FROM assembly_times
		");

		$db->do_query("
			DELETE FROM ballots
		");

		$db->do_query("
			DELETE FROM contact_recipients
		");

		$db->do_query("
			DELETE FROM discussion_comments
		");

		$db->do_query("
			DELETE FROM discussion_drafts
		");

		$db->do_query("
			DELETE FROM discussion_likes
		");

		$db->do_query("
			DELETE FROM discussion_pins
		");

		$db->do_query("
			DELETE FROM discussions
		");

		$db->do_query("
			DELETE FROM emailer_admins
		");

		$db->do_query("
			DELETE FROM entities
		");

		$db->do_query("
			DELETE FROM entity_docs
		");

		$db->do_query("
			DELETE FROM general_assemblies
		");

		$db->do_query("
			DELETE FROM notifications
		");

		$db->do_query("
			DELETE FROM perks
		");

		$db->do_query("
			DELETE FROM permissions
		");

		$db->do_query("
			DELETE FROM schedule
		");

		$db->do_query("
			DELETE FROM subscriptions
		");

		$db->do_query("
			DELETE FROM suspensions
		");

		$db->do_query("
			DELETE FROM team_invites
		");

		$db->do_query("
			DELETE FROM throttle
		");

		$db->do_query("
			DELETE FROM upgrades
		");

		$db->do_query("
			DELETE FROM user_entity_relations
		");

		$db->do_query("
			DELETE FROM user_upgrades
		");

		$db->do_query("
			DELETE FROM user_notifications
		");

		$db->do_query("
			DELETE FROM votes
		");

		$db->do_query("
			DELETE FROM users
			WHERE role LIKE '%user'
			OR    role = 'sub-admin'
		");

		_exit(
			'success',
			'DB reset complete'
		);
	}
}

new ResetDatabase();